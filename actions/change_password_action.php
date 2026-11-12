<?php
// actions/change_password_action.php - Verify current password and set a new one
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth();
$user = getCurrentUser();
requirePostWithCsrf('../change_password.php');

$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';
$confirm = $_POST['new_password_confirm'] ?? '';

$table = $user['role'] === 'admin' ? 'administrators' : 'students';
$pk    = $user['role'] === 'admin' ? 'admin_id' : 'student_id';

function changeFail(string $msg): void {
    setFlash('error', $msg);
    header('Location: ../change_password.php');
    exit;
}

$row = $pdo->prepare("SELECT password FROM {$table} WHERE {$pk} = :id");
$row->execute(['id' => $user['id']]);
$hash = $row->fetchColumn();

if ($hash === false || !password_verify($current, $hash)) {
    changeFail('Your current password is incorrect.');
}
if ($err = validatePasswordPolicy($new)) {
    changeFail($err);
}
if (!hash_equals($new, $confirm)) {
    changeFail('New passwords do not match.');
}
if (password_verify($new, $hash)) {
    changeFail('Your new password must be different from the current one.');
}

$pdo->prepare("UPDATE {$table} SET password = :p, must_change_password = 0 WHERE {$pk} = :id")
    ->execute(['p' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user['id']]);

$_SESSION['must_change_password'] = false;
session_regenerate_id(true);

setFlash('success', 'Your password has been updated.');
header('Location: ../' . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
exit;
