<?php
// actions/profile_photo_action.php - Upload or remove the signed-in user's profile photo
//   POST action=upload (file: photo) | action=remove
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth();
$user = getCurrentUser();
$type = $user['role'] === 'admin' ? 'admin' : 'student';
$redirect = $type === 'admin' ? '../admin/profile.php' : '../student/profile.php';

// A file larger than post_max_size empties $_POST (and with it the CSRF token)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    setFlash('error', 'The photo is too large. Please choose a smaller one.');
    header("Location: {$redirect}");
    exit;
}
requirePostWithCsrf($redirect);

if (($_POST['action'] ?? '') === 'remove') {
    removeProfilePhoto($pdo, $type, (int)$user['id']);
    setFlash('success', 'Your profile photo was removed.');
} else {
    $jpeg = processProfilePhoto($_FILES['photo'] ?? null, $error);
    if ($jpeg === null) {
        setFlash('error', $error);
    } else {
        saveProfilePhoto($pdo, $type, (int)$user['id'], $jpeg);
        setFlash('success', 'Your profile photo was updated.');
    }
}
header("Location: {$redirect}");
exit;
