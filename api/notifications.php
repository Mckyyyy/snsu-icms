<?php
// api/notifications.php - In-app notifications for the logged-in user
//   GET                       → { unread, latest }
//   POST action=mark_read     → mark one notification (id) read
//   POST action=mark_all_read → mark all read
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (sessionExpired() || !isLoggedIn()) {
    jsonResponse(['status' => 'error', 'message' => 'Authentication required.'], 401);
}
$user = getCurrentUser();
$role = $user['role'];
$uid  = (int)$user['id'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $latest = getUserNotifications($pdo, $role, $uid, 1)[0] ?? null;
    jsonResponse([
        'status' => 'success',
        'unread' => countUnreadNotifications($pdo, $role, $uid),
        'latest' => $latest && !$latest['is_read']
            ? ['title' => $latest['title'], 'message' => $latest['message'], 'type' => $latest['type']]
            : null,
    ]);
}

requireCsrfJson();

$action = $_POST['action'] ?? '';
if ($action === 'mark_all_read') {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_type = :r AND recipient_id = :u AND is_read = 0")
        ->execute(['r' => $role, 'u' => $uid]);
} elseif ($action === 'mark_read') {
    // recipient filter ensures users can only touch their own notifications
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = :id AND recipient_type = :r AND recipient_id = :u")
        ->execute(['id' => (int)($_POST['id'] ?? 0), 'r' => $role, 'u' => $uid]);
} else {
    jsonResponse(['status' => 'error', 'message' => 'Unknown action.'], 400);
}

jsonResponse(['status' => 'success', 'unread' => countUnreadNotifications($pdo, $role, $uid)]);
