<?php
// api/attachment.php - Stream a concern attachment from the database
//   GET concern=ID            → view inline (images, PDF)
//   GET concern=ID&download=1 → download with the original file name
// Access: the student who owns the concern, or any administrator.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

function attachmentError(int $code, string $message): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $message;
    exit;
}

if (sessionExpired() || !isLoggedIn()) {
    attachmentError(401, 'Please sign in to view this attachment.');
}
$user = getCurrentUser();
// Images load in parallel with the page; do not hold the session lock while streaming
session_write_close();

$concernId = (int)($_GET['concern'] ?? 0);
if ($concernId <= 0) {
    attachmentError(400, 'Missing attachment reference.');
}

// Ownership check first (no binary data read for people who may not see it)
if ($user['role'] === 'admin') {
    $allowed = true;
} else {
    $own = $pdo->prepare("SELECT 1 FROM concerns WHERE concern_id = :cid AND student_id = :sid");
    $own->execute(['cid' => $concernId, 'sid' => (int)$user['id']]);
    $allowed = (bool)$own->fetchColumn();
}
$meta = $allowed ? getConcernAttachmentMeta($pdo, $concernId) : null;
if (!$meta) {
    // Same answer for "not yours" and "does not exist", so ids cannot be probed
    attachmentError(404, 'Attachment not found.');
}

$etag = '"' . $meta['sha256'] . '"';
$download = !empty($_GET['download']) || !in_array($meta['mime_type'], ['image/jpeg', 'image/png', 'application/pdf'], true);
$name = $meta['original_name'];
$asciiName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'attachment';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime($meta['uploaded_at'])) . ' GMT');
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

$stmt = $pdo->prepare("SELECT file_data FROM concern_attachments WHERE concern_id = :cid");
$stmt->execute(['cid' => $concernId]);
$data = $stmt->fetchColumn();
if ($data === false) {
    attachmentError(404, 'Attachment not found.');
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $meta['mime_type']);
header('Content-Length: ' . strlen($data));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline')
    . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($name));
if (attachmentIsImage($meta['mime_type'])) {
    // An image needs nothing else; this stops any embedded content from running if opened directly
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
}
echo $data;
exit;
