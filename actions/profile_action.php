<?php
// actions/profile_action.php - Handles updating student/admin profile
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth();
$user = getCurrentUser();

$redirect = ($user['role'] === 'admin') ? '../admin/profile.php' : '../student/profile.php';
requirePostWithCsrf($redirect);

$name     = trim($_POST['name'] ?? '');
// Password changes go through change_password.php (requires the current password)

if (empty($name)) {
    setFlash('error', 'Name cannot be empty.');
    header("Location: {$redirect}");
    exit;
}

if ($user['role'] === 'student') {
    $phone = trim($_POST['phone'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE students SET name = :name, phone = :phone WHERE student_id = :id");
    $stmt->execute(['name' => $name, 'phone' => $phone, 'id' => $user['id']]);
    
    $_SESSION['user_name'] = $name;
    setFlash('success', 'Profile information updated successfully!');
} else {
    // Admin
    $stmt = $pdo->prepare("UPDATE administrators SET name = :name WHERE admin_id = :id");
    $stmt->execute(['name' => $name, 'id' => $user['id']]);
    
    $_SESSION['user_name'] = $name;
    setFlash('success', 'Administrator profile updated successfully!');
}

header("Location: {$redirect}");
exit;
