<?php
// api/messages.php - Real-time messaging between administrators and students (JSON)
//
//   GET  action=summary   [recent=1]                      → unread count, newest unread message (+ admin: recent chats)
//   GET  action=sync      conv, after, sig, visible, list  → long-poll: returns as soon as anything changes (≤ 20 s)
//   GET  action=history   conv, before                    → older messages
//   GET  action=students  q                               → admin: search active students to message
//   POST action=open      student_id                      → admin: get/create the conversation with a student
//   POST action=send      conv, body                      → store a message
//   POST action=typing    conv, typing (1|0)              → typing indicator signal
//
// Students can only ever reach their own conversation (the conv parameter is ignored for them).
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (sessionExpired() || !isLoggedIn()) {
    jsonResponse(['status' => 'error', 'message' => 'Authentication required.'], 401);
}
if (!empty($_SESSION['must_change_password'])) {
    jsonResponse(['status' => 'error', 'message' => 'Please change your password first.'], 403);
}

$user    = getCurrentUser();
$role    = $user['role'] === 'admin' ? 'admin' : 'student';
$uid     = (int)$user['id'];
$isAdmin = $role === 'admin';
$method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action  = $_REQUEST['action'] ?? '';

/**
 * Resolve the conversation the caller may access. Students always get their own;
 * admins must name one. Returns [conversationId, info] or sends a 404.
 */
function resolveConversation(PDO $pdo, string $role, int $uid, int $requested, bool $create = true): array {
    $convId = $role === 'student' ? chatConversationForStudent($pdo, $uid, $create) : $requested;
    $info = $convId ? chatConversationInfo($pdo, $convId) : null;
    if (!$info) {
        jsonResponse(['status' => 'error', 'message' => 'Conversation not found.'], 404);
    }
    return [$convId, $info];
}

// --------------------------------------------------------------------- GET
if ($method === 'GET') {
    if ($action === 'summary') {
        chatTouchPresence($pdo, $role, $uid);
        $out = [
            'status' => 'success',
            'unread' => chatUnreadCount($pdo, $role, $uid),
            'latest' => chatLatestUnread($pdo, $role, $uid),
        ];
        if ($isAdmin && !empty($_GET['recent'])) {
            $out['recent'] = chatAdminConversations($pdo, $uid, 6);
        }
        jsonResponse($out);
    }

    if ($action === 'history') {
        [$convId] = resolveConversation($pdo, $role, $uid, (int)($_GET['conv'] ?? 0));
        $messages = chatMessages($pdo, $convId, null, (int)($_GET['before'] ?? 0), CHAT_PAGE_SIZE, [$role, $uid]);
        jsonResponse(['status' => 'success', 'messages' => $messages, 'has_more' => count($messages) === CHAT_PAGE_SIZE]);
    }

    if ($action === 'students') {
        if (!$isAdmin) {
            jsonResponse(['status' => 'error', 'message' => 'Not allowed.'], 403);
        }
        $q = trim((string)($_GET['q'] ?? ''));
        jsonResponse(['status' => 'success', 'students' => chatSearchStudents($pdo, mb_substr($q, 0, 80))]);
    }

    if ($action === 'sync') {
        $init      = !empty($_GET['init']);   // first load of a conversation: latest page, answer at once
        $after     = max(0, (int)($_GET['after'] ?? 0));
        $clientSig = (string)($_GET['sig'] ?? '');
        $visible   = !empty($_GET['visible']);
        $withList  = $isAdmin && !empty($_GET['list']);
        $wait      = !empty($_GET['wait']);
        // Last change marker the browser has (edits / unsends / reactions after it are sent as "changed")
        $since     = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/', (string)($_GET['rev'] ?? '')) ? (string)$_GET['rev'] : null;
        $viewer    = [$role, $uid];

        $convId = 0;
        $info   = null;
        if ($role === 'student' || (int)($_GET['conv'] ?? 0) > 0) {
            [$convId, $info] = resolveConversation($pdo, $role, $uid, (int)($_GET['conv'] ?? 0));
        }

        // Release the session lock so sends / typing / page loads are not blocked while we wait.
        session_write_close();
        set_time_limit(60);
        ignore_user_abort(false);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $deadline     = microtime(true) + ($wait ? CHAT_LONG_POLL_SECONDS : 0);
        $lastPresence = 0.0;
        while (true) {
            if (microtime(true) - $lastPresence >= 10) {
                chatTouchPresence($pdo, $role, $uid);
                $lastPresence = microtime(true);
            }
            if ($convId && $visible) {
                chatMarkRead($pdo, $convId, $role);
            }
            $messages = !$convId ? [] : ($init ? chatMessages($pdo, $convId, null, 0, CHAT_PAGE_SIZE, $viewer) : chatMessages($pdo, $convId, $after, 0, 200, $viewer));
            $rev = $convId ? chatConversationRev($pdo, $convId) : '';
            $changed = (!$init && $convId && $since !== null && $rev > $since) ? chatChangedMessages($pdo, $convId, $since, $viewer) : [];
            $state = [
                'rev'       => $rev,
                'seen_upto' => $convId ? chatSeenUpTo($pdo, $convId, $role) : 0,
                'peer'      => $convId ? chatPeerState($pdo, $convId, $role, $info['student_id']) : null,
                'unread'    => chatUnreadCount($pdo, $role, $uid),
                'list'      => $withList ? chatAdminConversations($pdo, $uid) : null,
            ];
            $sig = md5(json_encode($state));
            if ($init || $messages || $changed || $sig !== $clientSig || microtime(true) >= $deadline) {
                break;
            }
            usleep(700000);
            // Whitespace is valid before JSON; sending it lets PHP notice a closed connection and stop.
            echo ' ';
            flush();
            if (connection_aborted()) {
                exit;
            }
        }

        echo json_encode([
            'status'       => 'success',
            'conversation' => $info,
            'messages'     => $messages,
            'changed'      => $changed,
            'has_more'     => $init && count($messages) === CHAT_PAGE_SIZE,
            'sig'          => $sig,
            'now'          => time(),
            'retry_ms'     => CHAT_POLL_DELAY_MS, // pause before the next sync (shared hosting)
        ] + $state);
        exit;
    }

    jsonResponse(['status' => 'error', 'message' => 'Unknown action.'], 400);
}

// --------------------------------------------------------------------- POST
requireCsrfJson();

if ($action === 'typing') {
    [$convId] = resolveConversation($pdo, $role, $uid, (int)($_POST['conv'] ?? 0));
    chatSetTyping($pdo, $role, $uid, !empty($_POST['typing']) ? $convId : null);
    jsonResponse(['status' => 'success']);
}

if ($action === 'open') {
    if (!$isAdmin) {
        jsonResponse(['status' => 'error', 'message' => 'Not allowed.'], 403);
    }
    $convId = chatConversationForStudent($pdo, (int)($_POST['student_id'] ?? 0));
    if (!$convId) {
        jsonResponse(['status' => 'error', 'message' => 'Student not found.'], 404);
    }
    jsonResponse(['status' => 'success', 'conversation' => chatConversationInfo($pdo, $convId)]);
}

if ($action === 'send') {
    [$convId, $info] = resolveConversation($pdo, $role, $uid, (int)($_POST['conv'] ?? 0));
    $body = trim(str_replace("\r\n", "\n", (string)($_POST['body'] ?? '')));
    if ($body === '') {
        jsonResponse(['status' => 'error', 'message' => 'Type a message first.'], 422);
    }
    if (mb_strlen($body) > CHAT_MAX_LENGTH) {
        jsonResponse(['status' => 'error', 'message' => 'Messages can be at most ' . CHAT_MAX_LENGTH . ' characters.'], 422);
    }
    if ($isAdmin && $info['status'] !== 'Active') {
        jsonResponse(['status' => 'error', 'message' => 'This student account is not active.'], 422);
    }
    // Simple flood guard: 30 messages per minute per account
    $recent = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_type = :t AND sender_id = :u AND created_at >= NOW() - INTERVAL 1 MINUTE");
    $recent->execute(['t' => $role, 'u' => $uid]);
    if ((int)$recent->fetchColumn() >= 30) {
        jsonResponse(['status' => 'error', 'message' => 'You are sending messages too quickly. Please wait a moment.'], 429);
    }

    // Reply: the quoted message must be in this conversation and not unsent
    $replyTo = (int)($_POST['reply_to'] ?? 0) ?: null;
    if ($replyTo) {
        $quoted = chatMessageForUser($pdo, $replyTo, $role, $uid);
        if (!$quoted || (int)$quoted['conversation_id'] !== $convId || $quoted['unsent_at']) {
            jsonResponse(['status' => 'error', 'message' => 'The message you are replying to is no longer available.'], 422);
        }
    }

    touchSession(); // sending is real activity
    chatTouchPresence($pdo, $role, $uid);
    $message = chatSendMessage($pdo, $convId, $role, $uid, $body, $replyTo);
    // Replying means the incoming messages have been seen
    chatMarkRead($pdo, $convId, $role);
    jsonResponse(['status' => 'success', 'message' => $message]);
}

// ---------------------------------------------------------------- message actions
if (in_array($action, ['edit', 'unsend', 'delete', 'react', 'forward'], true)) {
    $msg = chatMessageForUser($pdo, (int)($_POST['id'] ?? 0), $role, $uid);
    if (!$msg) {
        jsonResponse(['status' => 'error', 'message' => 'Message not found.'], 404);
    }
    $msgId  = (int)$msg['message_id'];
    $own    = chatIsOwnMessage($msg, $role, $uid);
    $viewer = [$role, $uid];
    touchSession();

    if ($action === 'edit') {
        if (!$own) {
            jsonResponse(['status' => 'error', 'message' => 'You can only edit your own messages.'], 403);
        }
        if ($msg['unsent_at']) {
            jsonResponse(['status' => 'error', 'message' => 'This message was unsent.'], 422);
        }
        if (time() - strtotime($msg['created_at']) > CHAT_EDIT_WINDOW) {
            jsonResponse(['status' => 'error', 'message' => 'Messages can only be edited within ' . (CHAT_EDIT_WINDOW / 60) . ' minutes of sending.'], 422);
        }
        $body = trim(str_replace("\r\n", "\n", (string)($_POST['body'] ?? '')));
        if ($body === '') {
            jsonResponse(['status' => 'error', 'message' => 'A message cannot be empty. Use Unsend to remove it.'], 422);
        }
        if (mb_strlen($body) > CHAT_MAX_LENGTH) {
            jsonResponse(['status' => 'error', 'message' => 'Messages can be at most ' . CHAT_MAX_LENGTH . ' characters.'], 422);
        }
        if ($body !== $msg['body']) {
            $pdo->prepare("UPDATE messages SET body = :b, edited_at = NOW(), updated_at = NOW(3) WHERE message_id = :m")->execute(['b' => $body, 'm' => $msgId]);
        }
        jsonResponse(['status' => 'success', 'message' => chatMessageById($pdo, $msgId, $viewer)]);
    }

    if ($action === 'unsend') {
        if (!$own) {
            jsonResponse(['status' => 'error', 'message' => 'You can only unsend your own messages.'], 403);
        }
        if (!$msg['unsent_at']) {
            // Removed for everyone: the text is erased, reactions go with it
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE messages SET body = '', unsent_at = NOW(), updated_at = NOW(3) WHERE message_id = :m")->execute(['m' => $msgId]);
            $pdo->prepare("DELETE FROM message_reactions WHERE message_id = :m")->execute(['m' => $msgId]);
            // Quotes of this message must refresh too
            $pdo->prepare("UPDATE messages SET updated_at = NOW(3) WHERE reply_to_id = :m")->execute(['m' => $msgId]);
            $pdo->commit();
        }
        jsonResponse(['status' => 'success', 'message' => chatMessageById($pdo, $msgId, $viewer)]);
    }

    if ($action === 'delete') {
        // "Remove for you": hidden for this account only
        $pdo->prepare("INSERT IGNORE INTO message_hidden (message_id, user_type, user_id) VALUES (:m, :t, :u)")->execute(['m' => $msgId, 't' => $role, 'u' => $uid]);
        jsonResponse(['status' => 'success', 'id' => $msgId]);
    }

    if ($action === 'react') {
        if ($msg['unsent_at']) {
            jsonResponse(['status' => 'error', 'message' => 'This message was unsent.'], 422);
        }
        $emoji = (string)($_POST['emoji'] ?? '');
        if ($emoji !== '' && !in_array($emoji, CHAT_REACTIONS, true)) {
            jsonResponse(['status' => 'error', 'message' => 'Unknown reaction.'], 422);
        }
        $cur = $pdo->prepare("SELECT emoji FROM message_reactions WHERE message_id = :m AND user_type = :t AND user_id = :u");
        $cur->execute(['m' => $msgId, 't' => $role, 'u' => $uid]);
        $current = $cur->fetchColumn();
        if ($emoji === '' || $emoji === $current) {      // tapping your own reaction again removes it
            $pdo->prepare("DELETE FROM message_reactions WHERE message_id = :m AND user_type = :t AND user_id = :u")->execute(['m' => $msgId, 't' => $role, 'u' => $uid]);
        } else {
            $pdo->prepare("INSERT INTO message_reactions (message_id, user_type, user_id, emoji) VALUES (:m, :t, :u, :e)
                           ON DUPLICATE KEY UPDATE emoji = :e2, created_at = NOW()")
                ->execute(['m' => $msgId, 't' => $role, 'u' => $uid, 'e' => $emoji, 'e2' => $emoji]);
        }
        chatTouchMessage($pdo, $msgId);
        jsonResponse(['status' => 'success', 'message' => chatMessageById($pdo, $msgId, $viewer)]);
    }

    if ($action === 'forward') {
        if (!$isAdmin) {
            jsonResponse(['status' => 'error', 'message' => 'Not allowed.'], 403);
        }
        if ($msg['unsent_at']) {
            jsonResponse(['status' => 'error', 'message' => 'This message was unsent.'], 422);
        }
        // Student messages are private to their conversation: only administration messages can be forwarded
        if ($msg['sender_type'] !== 'admin') {
            jsonResponse(['status' => 'error', 'message' => "Students' messages can't be forwarded to other students."], 403);
        }
        $toConv = (int)($_POST['to_conv'] ?? 0);
        if (!$toConv && (int)($_POST['to_student'] ?? 0)) {
            $toConv = (int)chatConversationForStudent($pdo, (int)$_POST['to_student']);
        }
        $target = $toConv ? chatConversationInfo($pdo, $toConv) : null;
        if (!$target) {
            jsonResponse(['status' => 'error', 'message' => 'Conversation not found.'], 404);
        }
        if ($target['status'] !== 'Active') {
            jsonResponse(['status' => 'error', 'message' => 'This student account is not active.'], 422);
        }
        $message = chatSendMessage($pdo, $toConv, $role, $uid, $msg['body'], null, $msgId);
        jsonResponse(['status' => 'success', 'message' => $message, 'conversation' => $target]);
    }
}

jsonResponse(['status' => 'error', 'message' => 'Unknown action.'], 400);
