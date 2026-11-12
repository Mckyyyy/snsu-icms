<?php
// actions/user_action.php - User management (admin only)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$currentUser = getCurrentUser();
requirePostWithCsrf('../admin/users.php');

$action = $_POST['action'] ?? '';
$type   = ($_POST['type'] ?? 'student') === 'admin' ? 'admin' : 'student';
$id     = (int)($_POST['id'] ?? 0);
$isSelf = $type === 'admin' && $id === (int)$currentUser['id'];

if ($action === 'add') {
    $role       = ($_POST['role'] ?? 'student') === 'admin' ? 'admin' : 'student';
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $generated  = $password === '';
    if ($generated) {
        $password = generateTemporaryPassword();
    }
    $department = trim($_POST['department'] ?? 'College of Computing & Information Sciences');

    if ($name === '' || $email === '') {
        setFlash('error', 'Please provide a Name and Email.');
        header('Location: ../admin/users.php');
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Please enter a valid email address.');
        header('Location: ../admin/users.php');
        exit;
    }
    if ($err = validatePasswordPolicy($password)) {
        setFlash('error', $err);
        header('Location: ../admin/users.php');
        exit;
    }
    if (emailIsTaken($pdo, $email)) {
        setFlash('error', 'That email address is already registered.');
        header('Location: ../admin/users.php');
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        if ($role === 'student') {
            $studentNo = trim($_POST['student_no'] ?? '');
            if ($studentNo === '') {
                $studentNo = 'SNSU-' . date('Y') . '-' . random_int(10000, 99999);
            }
            $pdo->prepare("
                INSERT INTO students (student_no, name, email, password, must_change_password, department, program, status, created_at)
                VALUES (:student_no, :name, :email, :password, 1, :department, :program, 'Active', NOW())
            ")->execute([
                'student_no' => $studentNo,
                'name'       => $name,
                'email'      => $email,
                'password'   => $hashedPassword,
                'department' => $department,
                'program'    => trim($_POST['program'] ?? 'BS Information Technology'),
            ]);
        } else {
            $pdo->prepare("
                INSERT INTO administrators (name, email, password, must_change_password, department, role, status, created_at)
                VALUES (:name, :email, :password, 1, :department, :role, 'Active', NOW())
            ")->execute([
                'name'       => $name,
                'email'      => $email,
                'password'   => $hashedPassword,
                'department' => $department,
                'role'       => trim($_POST['admin_role'] ?? 'Administrator'),
            ]);
        }
        $_SESSION['temp_password_notice'] = ['name' => $name, 'email' => $email, 'password' => $password];
        logAdminActivity($pdo, (int)$currentUser['id'], 'user_created', $role, (int)$pdo->lastInsertId(), "{$name} <{$email}>");
        setFlash('success', "New {$role} account ({$name}) created. They must change the password on first login.");
    } catch (PDOException $e) {
        setFlash('error', 'Failed to create user. Email or Student Number may already be registered.');
    }
} elseif ($action === 'toggle_status') {
    if ($isSelf) {
        setFlash('error', 'You cannot deactivate your own administrator account.');
    } else {
        $table = $type === 'student' ? 'students' : 'administrators';
        $pk    = $type === 'student' ? 'student_id' : 'admin_id';
        $stmt  = $pdo->prepare("SELECT status FROM {$table} WHERE {$pk} = :id AND archived_at IS NULL");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $newStatus = $row['status'] === 'Active' ? 'Inactive' : 'Active';
            $pdo->prepare("UPDATE {$table} SET status = :st WHERE {$pk} = :id")->execute(['st' => $newStatus, 'id' => $id]);
            setFlash('success', ucfirst($type) . " account status updated to {$newStatus}.");
            logAdminActivity($pdo, (int)$currentUser['id'], $newStatus === 'Active' ? 'user_activated' : 'user_deactivated', $type, $id, null);
        }
    }
} elseif (in_array($action, ['archive', 'delete'], true)) {
    // Accounts are never deleted: archiving signs them out for good (Inactive) and hides them from the
    // user lists, while their concerns, responses and history stay intact.
    $table = $type === 'student' ? 'students' : 'administrators';
    $pk    = $type === 'student' ? 'student_id' : 'admin_id';
    $n = $pdo->prepare("SELECT CONCAT(name, ' <', email, '>') FROM {$table} WHERE {$pk} = :id AND archived_at IS NULL");
    $n->execute(['id' => $id]);
    $label = $n->fetchColumn() ?: null;
    if ($label === null) {
        setFlash('warning', 'That account is already archived.');
    } elseif ($isSelf) {
        setFlash('error', 'You cannot archive your own administrator account.');
    } elseif ($type === 'admin' && (int)$pdo->query("SELECT COUNT(*) FROM administrators WHERE status = 'Active' AND archived_at IS NULL")->fetchColumn() <= 1) {
        setFlash('error', 'Cannot archive the only remaining active administrator.');
    } else {
        $pdo->prepare("UPDATE {$table} SET status = 'Inactive', archived_at = NOW(), archived_by = :a WHERE {$pk} = :id")
            ->execute(['a' => $currentUser['id'], 'id' => $id]);
        if ($type === 'admin') {
            // Open tickets assigned to an archived administrator go back to the shared queue
            $pdo->prepare("UPDATE concerns SET assigned_admin_id = NULL WHERE assigned_admin_id = :id AND status_id IN (1, 2)")->execute(['id' => $id]);
        }
        logAdminActivity($pdo, (int)$currentUser['id'], 'user_archived', $type, $id, $label);
        setFlash('success', ($type === 'student' ? 'Student' : 'Administrator') . ' account archived. It can no longer sign in; restore it from the Archived tab.');
    }
} elseif ($action === 'restore') {
    $table = $type === 'student' ? 'students' : 'administrators';
    $pk    = $type === 'student' ? 'student_id' : 'admin_id';
    $pdo->prepare("UPDATE {$table} SET status = 'Active', archived_at = NULL, archived_by = NULL WHERE {$pk} = :id AND archived_at IS NOT NULL")
        ->execute(['id' => $id]);
    logAdminActivity($pdo, (int)$currentUser['id'], 'user_restored', $type, $id, null);
    setFlash('success', ($type === 'student' ? 'Student' : 'Administrator') . ' account restored and active again.');
}

header('Location: ../admin/users.php' . (in_array($action, ['archive', 'delete', 'restore'], true) && ($_POST['return_tab'] ?? '') === 'archived' ? '?status=Archived' : ''));
exit;
