<?php
// includes/messages.php - Real-time messaging between administrators and students
//
// Model: one conversation per student, handled by a shared admin inbox (any active
// administrator can read and reply; each reply records which admin sent it).
// Presence and typing signals live in `chat_presence`; the browser keeps them fresh
// through api/messages.php (long-polling, see action=sync).

const CHAT_ONLINE_WINDOW = 45;    // seconds since last activity that still count as "online"
const CHAT_TYPING_WINDOW = 5;     // seconds a typing signal stays valid without a refresh
const CHAT_MAX_LENGTH    = 2000;  // characters per message
const CHAT_PAGE_SIZE     = 40;    // messages per history page

/** The other side of a conversation. */
function chatPeerRole(string $role): string {
    return $role === 'admin' ? 'student' : 'admin';
}

/** Mark the user as online now. */
function chatTouchPresence(PDO $pdo, string $role, int $uid): void {
    $pdo->prepare("
        INSERT INTO chat_presence (user_type, user_id, last_seen_at) VALUES (:t, :u, NOW())
        ON DUPLICATE KEY UPDATE last_seen_at = NOW()
    ")->execute(['t' => $role, 'u' => $uid]);
}

/** Mark the user as offline (sign-out). */
function chatSetOffline(PDO $pdo, string $role, int $uid): void {
    $pdo->prepare("UPDATE chat_presence SET last_seen_at = NULL, typing_conversation_id = NULL, typing_at = NULL WHERE user_type = :t AND user_id = :u")
        ->execute(['t' => $role, 'u' => $uid]);
}

/** Start (conversation id) or stop (null) the typing signal. */
function chatSetTyping(PDO $pdo, string $role, int $uid, ?int $conversationId): void {
    $pdo->prepare("
        INSERT INTO chat_presence (user_type, user_id, last_seen_at, typing_conversation_id, typing_at)
        VALUES (:t, :u, NOW(), :c1, IF(:c2 IS NULL, NULL, NOW(3)))
        ON DUPLICATE KEY UPDATE last_seen_at = NOW(), typing_conversation_id = :c3, typing_at = IF(:c4 IS NULL, NULL, NOW(3))
    ")->execute(['t' => $role, 'u' => $uid, 'c1' => $conversationId, 'c2' => $conversationId, 'c3' => $conversationId, 'c4' => $conversationId]);
}

/** Conversation id for a student, created on first use. Null if the student does not exist. */
function chatConversationForStudent(PDO $pdo, int $studentId, bool $create = true): ?int {
    $find = $pdo->prepare("SELECT conversation_id FROM conversations WHERE student_id = :s");
    $find->execute(['s' => $studentId]);
    $id = $find->fetchColumn();
    if ($id || !$create) {
        return $id ? (int)$id : null;
    }
    $exists = $pdo->prepare("SELECT 1 FROM students WHERE student_id = :s");
    $exists->execute(['s' => $studentId]);
    if (!$exists->fetchColumn()) {
        return null;
    }
    $pdo->prepare("INSERT IGNORE INTO conversations (student_id) VALUES (:s)")->execute(['s' => $studentId]);
    $find->execute(['s' => $studentId]);
    return (int)$find->fetchColumn();
}

/** Conversation header details (student identity). */
function chatConversationInfo(PDO $pdo, int $conversationId): ?array {
    $stmt = $pdo->prepare("
        SELECT c.conversation_id, c.student_id, s.name, s.student_no, s.email, s.program, s.status
        FROM conversations c JOIN students s ON s.student_id = c.student_id
        WHERE c.conversation_id = :c
    ");
    $stmt->execute(['c' => $conversationId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id'         => (int)$row['conversation_id'],
        'student_id' => (int)$row['student_id'],
        'name'       => $row['name'],
        'initials'   => userInitials($row['name']),
        'student_no' => $row['student_no'],
        'email'      => $row['email'],
        'program'    => $row['program'],
        'status'     => $row['status'],
    ];
}

// ---------------------------------------------------------------- message actions
const CHAT_EDIT_WINDOW = 15 * 60;                              // seconds a sender may edit a message
// Allowed reactions (one per person per message), written as escapes to keep source files emoji-free:
// thumbs up, red heart, laughing, surprised, crying, angry
const CHAT_REACTIONS   = ["\u{1F44D}", "\u{2764}\u{FE0F}", "\u{1F606}", "\u{1F62E}", "\u{1F622}", "\u{1F620}"];

/** Shape a DB row for the browser. */
function chatFormatMessage(array $m): array {
    $unsent = !empty($m['unsent_at']);
    $out = [
        'id'          => (int)$m['message_id'],
        'sender_type' => $m['sender_type'],
        'sender_id'   => (int)$m['sender_id'],
        'sender_name' => $m['sender_name'] ?? ($m['sender_type'] === 'admin' ? 'Administrator' : 'Student'),
        'body'        => $unsent ? '' : $m['body'],
        'ts'          => strtotime($m['created_at']),
        'read'        => $m['read_at'] !== null,
        'edited'      => !$unsent && !empty($m['edited_at']),
        'unsent'      => $unsent,
        'forwarded'   => !empty($m['forwarded_from_id']),
        'reply'       => null,
        'reactions'   => [],
    ];
    if (!empty($m['reply_to_id'])) {
        $replyUnsent = !empty($m['r_unsent_at']);
        $out['reply'] = [
            'id'          => (int)$m['reply_to_id'],
            'sender_type' => $m['r_sender_type'] ?? null,
            'sender_id'   => isset($m['r_sender_id']) ? (int)$m['r_sender_id'] : null,
            'sender_name' => $m['r_sender_name'] ?? null,
            'body'        => ($replyUnsent || !isset($m['r_body'])) ? '' : mb_substr($m['r_body'], 0, 160),
            'unsent'      => $replyUnsent,
            'missing'     => !isset($m['r_sender_type']),
        ];
    }
    return $out;
}

/** SELECT shared by every message query: the message, its sender and the message it replies to. */
function chatMessageSelect(): string {
    return "
        SELECT m.message_id, m.conversation_id, m.sender_type, m.sender_id, m.body, m.created_at, m.read_at,
               m.reply_to_id, m.forwarded_from_id, m.edited_at, m.unsent_at, m.updated_at,
               CASE WHEN m.sender_type = 'admin' THEN a.name ELSE s.name END AS sender_name,
               r.sender_type AS r_sender_type, r.sender_id AS r_sender_id, r.body AS r_body, r.unsent_at AS r_unsent_at,
               CASE WHEN r.sender_type = 'admin' THEN ra.name ELSE rs.name END AS r_sender_name
        FROM messages m
        LEFT JOIN administrators a ON m.sender_type = 'admin' AND a.admin_id = m.sender_id
        LEFT JOIN students s ON m.sender_type = 'student' AND s.student_id = m.sender_id
        LEFT JOIN messages r ON r.message_id = m.reply_to_id
        LEFT JOIN administrators ra ON r.sender_type = 'admin' AND ra.admin_id = r.sender_id
        LEFT JOIN students rs ON r.sender_type = 'student' AND rs.student_id = r.sender_id
        LEFT JOIN message_hidden h ON h.message_id = m.message_id AND h.user_type = :vt AND h.user_id = :vu ";
}

/** Add the reaction summary ([{emoji, count, mine, names}]) to formatted messages. */
function chatAttachReactions(PDO $pdo, array $messages, ?array $viewer): array {
    $ids = array_values(array_filter(array_map(fn($m) => $m['unsent'] ? null : $m['id'], $messages)));
    if (!$ids) {
        return $messages;
    }
    $in = implode(',', array_map('intval', $ids));
    $rows = $pdo->query("
        SELECT r.message_id, r.emoji, r.user_type, r.user_id,
               CASE WHEN r.user_type = 'admin' THEN a.name ELSE s.name END AS name
        FROM message_reactions r
        LEFT JOIN administrators a ON r.user_type = 'admin' AND a.admin_id = r.user_id
        LEFT JOIN students s ON r.user_type = 'student' AND s.student_id = r.user_id
        WHERE r.message_id IN ({$in})
        ORDER BY r.created_at, r.emoji
    ")->fetchAll();
    $by = [];
    foreach ($rows as $r) {
        $mid = (int)$r['message_id'];
        $e = $r['emoji'];
        $by[$mid][$e] ??= ['emoji' => $e, 'count' => 0, 'mine' => false, 'names' => []];
        $by[$mid][$e]['count']++;
        $by[$mid][$e]['names'][] = $r['name'] ?? 'Someone';
        if ($viewer && $r['user_type'] === $viewer[0] && (int)$r['user_id'] === $viewer[1]) {
            $by[$mid][$e]['mine'] = true;
        }
    }
    foreach ($messages as &$m) {
        $m['reactions'] = array_values($by[$m['id']] ?? []);
    }
    return $messages;
}

/**
 * Messages of a conversation as seen by $viewer ([role, id]; messages they deleted "for me" are left out).
 * $afterId given → newer than that id (ascending); otherwise the latest page before $beforeId (ascending).
 */
function chatMessages(PDO $pdo, int $conversationId, ?int $afterId = null, int $beforeId = 0, int $limit = CHAT_PAGE_SIZE, ?array $viewer = null): array {
    $limit = max(1, min(200, $limit));
    $params = ['c' => $conversationId, 'vt' => $viewer[0] ?? 'nobody', 'vu' => $viewer[1] ?? 0];
    $where = "WHERE m.conversation_id = :c AND h.message_id IS NULL ";
    if ($afterId !== null) {
        $stmt = $pdo->prepare(chatMessageSelect() . $where . "AND m.message_id > :id ORDER BY m.message_id ASC LIMIT {$limit}");
        $stmt->execute($params + ['id' => $afterId]);
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare(chatMessageSelect() . $where . "AND m.message_id < :id ORDER BY m.message_id DESC LIMIT {$limit}");
        $stmt->execute($params + ['id' => $beforeId > 0 ? $beforeId : PHP_INT_MAX]);
        $rows = array_reverse($stmt->fetchAll());
    }
    return chatAttachReactions($pdo, array_map('chatFormatMessage', $rows), $viewer);
}

/** Messages edited, unsent or reacted to since $since (the "rev" the browser last received). */
function chatChangedMessages(PDO $pdo, int $conversationId, string $since, ?array $viewer): array {
    $stmt = $pdo->prepare(chatMessageSelect() . "WHERE m.conversation_id = :c AND h.message_id IS NULL AND m.updated_at > :since ORDER BY m.message_id LIMIT 200");
    $stmt->execute(['c' => $conversationId, 'vt' => $viewer[0] ?? 'nobody', 'vu' => $viewer[1] ?? 0, 'since' => $since]);
    return chatAttachReactions($pdo, array_map('chatFormatMessage', $stmt->fetchAll()), $viewer);
}

/** Change marker of a conversation: latest edit / unsend / reaction time. */
function chatConversationRev(PDO $pdo, int $conversationId): string {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(updated_at), '1970-01-01 00:00:00.000') FROM messages WHERE conversation_id = :c");
    $stmt->execute(['c' => $conversationId]);
    return (string)$stmt->fetchColumn();
}

/** Mark a message as changed so every open chat picks the change up. */
function chatTouchMessage(PDO $pdo, int $messageId): void {
    $pdo->prepare("UPDATE messages SET updated_at = NOW(3) WHERE message_id = :m")->execute(['m' => $messageId]);
}

/**
 * A message the user may act on: in a conversation they can see (students: their own only).
 * Returns the raw row (with conversation_id) or null.
 */
function chatMessageForUser(PDO $pdo, int $messageId, string $role, int $uid): ?array {
    $stmt = $pdo->prepare("
        SELECT m.*, c.student_id FROM messages m JOIN conversations c ON c.conversation_id = m.conversation_id
        WHERE m.message_id = :m
    ");
    $stmt->execute(['m' => $messageId]);
    $row = $stmt->fetch();
    if (!$row || ($role === 'student' && (int)$row['student_id'] !== $uid)) {
        return null;
    }
    return $row;
}

/** One formatted message as $viewer sees it. */
function chatMessageById(PDO $pdo, int $messageId, ?array $viewer): ?array {
    $stmt = $pdo->prepare(chatMessageSelect() . "WHERE m.message_id = :id");
    $stmt->execute(['id' => $messageId, 'vt' => $viewer[0] ?? 'nobody', 'vu' => $viewer[1] ?? 0]);
    $row = $stmt->fetch();
    return $row ? chatAttachReactions($pdo, [chatFormatMessage($row)], $viewer)[0] : null;
}

function chatIsOwnMessage(array $row, string $role, int $uid): bool {
    return $row['sender_type'] === $role && (int)$row['sender_id'] === $uid;
}

/** Save a message and bump the conversation. Returns the stored message. */
function chatSendMessage(PDO $pdo, int $conversationId, string $role, int $uid, string $body, ?int $replyTo = null, ?int $forwardedFrom = null): array {
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO messages (conversation_id, sender_type, sender_id, body, reply_to_id, forwarded_from_id) VALUES (:c, :t, :u, :b, :r, :f)")
            ->execute(['c' => $conversationId, 't' => $role, 'u' => $uid, 'b' => $body, 'r' => $replyTo, 'f' => $forwardedFrom]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE conversations SET last_message_id = :m, last_message_at = NOW() WHERE conversation_id = :c")
            ->execute(['m' => $id, 'c' => $conversationId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    chatSetTyping($pdo, $role, $uid, null);
    return chatMessageById($pdo, $id, [$role, $uid]);
}

/** Mark the other side's messages in a conversation as read. */
function chatMarkRead(PDO $pdo, int $conversationId, string $readerRole): void {
    $pdo->prepare("UPDATE messages SET read_at = NOW() WHERE conversation_id = :c AND sender_type = :t AND read_at IS NULL")
        ->execute(['c' => $conversationId, 't' => chatPeerRole($readerRole)]);
}

/** Highest id among my side's messages that the other side has read. */
function chatSeenUpTo(PDO $pdo, int $conversationId, string $role): int {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(message_id), 0) FROM messages WHERE conversation_id = :c AND sender_type = :t AND read_at IS NOT NULL");
    $stmt->execute(['c' => $conversationId, 't' => $role]);
    return (int)$stmt->fetchColumn();
}

/**
 * Online / typing state of the other side of a conversation.
 * Students see the administration as a whole (online if any admin is).
 */
function chatPeerState(PDO $pdo, int $conversationId, string $role, int $studentId): array {
    $online = CHAT_ONLINE_WINDOW;
    $typing = CHAT_TYPING_WINDOW;
    if ($role === 'admin') {
        $stmt = $pdo->prepare("
            SELECT last_seen_at,
                   (last_seen_at >= NOW() - INTERVAL {$online} SECOND) AS online,
                   (typing_conversation_id = :c AND typing_at >= NOW(3) - INTERVAL {$typing} SECOND) AS typing
            FROM chat_presence WHERE user_type = 'student' AND user_id = :s
        ");
        $stmt->execute(['c' => $conversationId, 's' => $studentId]);
        $p = $stmt->fetch() ?: [];
        $isOnline = !empty($p['online']);
        return [
            'online'      => $isOnline,
            'last_seen'   => (!$isOnline && !empty($p['last_seen_at'])) ? strtotime($p['last_seen_at']) : null,
            'typing'      => !empty($p['typing']),
            'typing_name' => null,
        ];
    }

    $last = $pdo->query("SELECT MAX(last_seen_at) FROM chat_presence WHERE user_type = 'admin'")->fetchColumn();
    $isOnline = (bool)$pdo->query("SELECT COUNT(*) FROM chat_presence WHERE user_type = 'admin' AND last_seen_at >= NOW() - INTERVAL {$online} SECOND")->fetchColumn();
    $stmt = $pdo->prepare("
        SELECT a.name FROM chat_presence p JOIN administrators a ON a.admin_id = p.user_id
        WHERE p.user_type = 'admin' AND p.typing_conversation_id = :c AND p.typing_at >= NOW(3) - INTERVAL {$typing} SECOND
        ORDER BY p.typing_at DESC LIMIT 1
    ");
    $stmt->execute(['c' => $conversationId]);
    $typingName = $stmt->fetchColumn();
    return [
        'online'      => $isOnline,
        'last_seen'   => (!$isOnline && $last) ? strtotime($last) : null,
        'typing'      => $typingName !== false,
        'typing_name' => $typingName !== false ? $typingName : null,
    ];
}

/** Conversation list for the admin inbox (most recent first). */
function chatAdminConversations(PDO $pdo, int $adminId, int $limit = 100): array {
    $limit = max(1, min(300, $limit));
    $online = CHAT_ONLINE_WINDOW;
    $typing = CHAT_TYPING_WINDOW;
    $rows = $pdo->query("
        SELECT c.conversation_id, c.student_id, s.name, s.student_no, c.last_message_id, c.last_message_at,
               m.body AS last_body, m.sender_type AS last_sender_type, m.sender_id AS last_sender_id,
               m.unsent_at AS last_unsent_at, m.forwarded_from_id AS last_forwarded, st.name AS last_student_name,
               a.name AS last_admin_name,
               (SELECT COUNT(*) FROM messages u WHERE u.conversation_id = c.conversation_id AND u.sender_type = 'student' AND u.read_at IS NULL AND u.unsent_at IS NULL) AS unread,
               COALESCE(p.last_seen_at >= NOW() - INTERVAL {$online} SECOND, 0) AS online,
               COALESCE(p.typing_conversation_id = c.conversation_id AND p.typing_at >= NOW(3) - INTERVAL {$typing} SECOND, 0) AS typing
        FROM conversations c
        JOIN students s ON s.student_id = c.student_id
        LEFT JOIN messages m ON m.message_id = c.last_message_id
        LEFT JOIN administrators a ON m.sender_type = 'admin' AND a.admin_id = m.sender_id
        LEFT JOIN students st ON m.sender_type = 'student' AND st.student_id = m.sender_id
        LEFT JOIN chat_presence p ON p.user_type = 'student' AND p.user_id = c.student_id
        WHERE c.last_message_id IS NOT NULL
        ORDER BY c.last_message_at DESC, c.conversation_id DESC
        LIMIT {$limit}
    ")->fetchAll();

    return array_map(static function (array $r) use ($adminId): array {
        $firstName = fn(?string $n, string $fallback) => trim(explode(' ', preg_replace('/^(Dr|Engr|Prof|Mr|Ms|Mrs|Atty)\.?\s+/i', '', (string)$n))[0]) ?: $fallback;
        $who = $r['last_sender_type'] === 'admin'
            ? ((int)$r['last_sender_id'] === $adminId ? 'You' : $firstName($r['last_admin_name'], 'Admin'))
            : $firstName($r['last_student_name'], 'Student');
        if (!empty($r['last_unsent_at'])) {
            $preview = $who . ' unsent a message';
        } elseif ($r['last_sender_type'] === 'admin') {
            $preview = $who . ($r['last_forwarded'] ? ' forwarded: ' : ': ') . $r['last_body'];
        } else {
            $preview = (string)$r['last_body'];
        }
        return [
            'id'         => (int)$r['conversation_id'],
            'student_id' => (int)$r['student_id'],
            'name'       => $r['name'],
            'initials'   => userInitials($r['name']),
            'student_no' => $r['student_no'],
            'preview'    => mb_substr(preg_replace('/\s+/', ' ', $preview), 0, 90),
            'last_id'    => (int)$r['last_message_id'],
            'last_from'  => $r['last_sender_type'],
            'ts'         => $r['last_message_at'] ? strtotime($r['last_message_at']) : null,
            'unread'     => (int)$r['unread'],
            'online'     => (bool)$r['online'],
            'typing'     => (bool)$r['typing'],
        ];
    }, $rows);
}

/** Unread incoming messages for the navbar / sidebar badges. */
function chatUnreadCount(PDO $pdo, string $role, int $uid): int {
    if ($role === 'admin') {
        return (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE sender_type = 'student' AND read_at IS NULL AND unsent_at IS NULL")->fetchColumn();
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages m JOIN conversations c ON c.conversation_id = m.conversation_id
        WHERE c.student_id = :s AND m.sender_type = 'admin' AND m.read_at IS NULL AND m.unsent_at IS NULL
    ");
    $stmt->execute(['s' => $uid]);
    return (int)$stmt->fetchColumn();
}

/** Newest unread incoming message (for pop-up notifications), or null. */
function chatLatestUnread(PDO $pdo, string $role, int $uid): ?array {
    $select = "
        SELECT m.message_id, m.conversation_id, m.sender_type, m.sender_id, m.body, m.created_at, m.read_at,
               m.edited_at, m.unsent_at, m.forwarded_from_id,
               CASE WHEN m.sender_type = 'admin' THEN a.name ELSE s.name END AS sender_name
        FROM messages m
        JOIN conversations c ON c.conversation_id = m.conversation_id
        LEFT JOIN administrators a ON m.sender_type = 'admin' AND a.admin_id = m.sender_id
        LEFT JOIN students s ON m.sender_type = 'student' AND s.student_id = m.sender_id ";
    if ($role === 'admin') {
        $stmt = $pdo->query($select . "WHERE m.sender_type = 'student' AND m.read_at IS NULL AND m.unsent_at IS NULL ORDER BY m.message_id DESC LIMIT 1");
    } else {
        $stmt = $pdo->prepare($select . "WHERE c.student_id = :s AND m.sender_type = 'admin' AND m.read_at IS NULL AND m.unsent_at IS NULL ORDER BY m.message_id DESC LIMIT 1");
        $stmt->execute(['s' => $uid]);
    }
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return chatFormatMessage($row) + ['conversation_id' => (int)$row['conversation_id']];
}

/** Active students matching a search (admin "New message" picker). */
function chatSearchStudents(PDO $pdo, string $q, int $limit = 12): array {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.name, s.student_no, s.program, c.conversation_id
        FROM students s LEFT JOIN conversations c ON c.student_id = s.student_id
        WHERE s.status = 'Active' AND (s.name LIKE :q1 OR s.student_no LIKE :q2 OR s.email LIKE :q3)
        ORDER BY s.name LIMIT {$limit}
    ");
    $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like]);
    return array_map(static fn(array $r): array => [
        'student_id'      => (int)$r['student_id'],
        'name'            => $r['name'],
        'initials'        => userInitials($r['name']),
        'student_no'      => $r['student_no'],
        'program'         => $r['program'],
        'conversation_id' => $r['conversation_id'] ? (int)$r['conversation_id'] : null,
    ], $stmt->fetchAll());
}
