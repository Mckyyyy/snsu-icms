<?php
// api/avatar.php - Stream a profile photo from the database
//   GET type=student|admin&id=N&v=VERSION
// Access: administrators see every photo; a student sees their own photo and the administrators'.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

function avatarNotFound(int $code = 404): void {
    http_response_code($code);
    header('Cache-Control: no-store');
    exit;
}

if (sessionExpired() || !isLoggedIn()) {
    avatarNotFound(401);
}
$user = getCurrentUser();
session_write_close();

$type = ($_GET['type'] ?? '') === 'admin' ? 'admin' : 'student';
$id   = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    avatarNotFound(400);
}
$allowed = $user['role'] === 'admin' || $type === 'admin' || (int)$user['id'] === $id;
if (!$allowed) {
    avatarNotFound(403);
}

$stmt = $pdo->prepare("SELECT mime_type, sha256, photo_data FROM profile_photos WHERE owner_type = :t AND owner_id = :id");
$stmt->execute(['t' => $type, 'id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    avatarNotFound();
}

$etag = '"' . substr($row['sha256'], 0, 32) . '"';
header('Cache-Control: private, max-age=31536000, immutable'); // the URL changes when the photo changes
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Type: ' . $row['mime_type']);
header('Content-Length: ' . strlen($row['photo_data']));
echo $row['photo_data'];
