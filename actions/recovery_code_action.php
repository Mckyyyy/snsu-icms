<?php
// actions/recovery_code_action.php - A signed-in student generates a new recovery code (Profile page).
// The current password is required, so someone at an unlocked computer cannot take over the account.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$back = '../student/profile.php#recovery';
requirePostWithCsrf($back);
$user = getCurrentUser();

$s = $pdo->prepare("SELECT password, name FROM students WHERE student_id = :id");
$s->execute(['id' => $user['id']]);
$st = $s->fetch();
if (!$st || !password_verify((string)($_POST['current_password'] ?? ''), $st['password'])) {
    setFlash('error', 'Your current password is not correct, so no new recovery code was made.');
    header("Location: {$back}");
    exit;
}

showRecoveryCodeNext(issueRecoveryCode($pdo, (int)$user['id']), $st['name'],
    'Here is your new recovery code. Any code you saved before no longer works.',
    'student/profile.php');
header('Location: ../recovery_code.php');
exit;
