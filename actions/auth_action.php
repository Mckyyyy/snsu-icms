<?php
// actions/auth_action.php - Login processor (Use case: Login / Secure Login)
// Single sign-in form with automatic role detection, brute-force lockout and forced password change.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requirePostWithCsrf('../index.php');

$identifier = trim($_POST['identifier'] ?? '');
$password   = $_POST['password'] ?? ''; // never trim passwords

function backToLogin(string $type, string $message): void {
    setFlash($type, $message);
    header('Location: ../index.php');
    exit;
}

if ($identifier === '' || $password === '') {
    backToLogin('error', 'Please enter both your identifier (email/student number) and password.');
}

// ---- Brute-force protection -----------------------------------------------
$lockedFor = loginLockoutRemaining($pdo, $identifier);
if ($lockedFor > 0) {
    $mins = (int)ceil($lockedFor / 60);
    backToLogin('error', "Too many failed login attempts. Please try again in {$mins} minute(s), or use Forgot Password.");
}

/**
 * Start an authenticated session for a verified account row.
 */
function startUserSession(PDO $pdo, string $role, array $row): void {
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    clearFailedLogins($pdo, $_POST['identifier'] ?? '');

    if ($role === 'student') {
        $_SESSION['user_id']      = $row['student_id'];
        $_SESSION['student_no']   = $row['student_no'];
        $_SESSION['user_program'] = $row['program'];
        $pdo->prepare("UPDATE students SET last_login_at = NOW() WHERE student_id = :id")->execute(['id' => $row['student_id']]);
    } else {
        $_SESSION['user_id']      = $row['admin_id'];
        $_SESSION['user_program'] = $row['role'];
        $pdo->prepare("UPDATE administrators SET last_login_at = NOW() WHERE admin_id = :id")->execute(['id' => $row['admin_id']]);
    }
    $_SESSION['role']                 = $role;
    $_SESSION['user_name']            = $row['name'];
    $_SESSION['user_email']           = $row['email'];
    $_SESSION['user_department']      = $row['department'];
    $_SESSION['must_change_password'] = (int)($row['must_change_password'] ?? 0) === 1;
    $_SESSION['last_activity']        = time();

    if ($_SESSION['must_change_password']) {
        setFlash('warning', 'You are using a temporary password. Please set a new password to continue.');
        header('Location: ../change_password.php');
        exit;
    }

    if ($role === 'student') {
        setFlash('success', "Welcome back, {$row['name']}!");
        header('Location: ../student/dashboard.php');
    } else {
        setFlash('success', "Welcome back, Administrator {$row['name']}!");
        header('Location: ../admin/dashboard.php');
    }
    exit;
}

function findStudent(PDO $pdo, string $identifier): ?array {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE email = :email OR student_no = :student_no LIMIT 1");
    $stmt->execute(['email' => $identifier, 'student_no' => $identifier]);
    return $stmt->fetch() ?: null;
}

function findAdmin(PDO $pdo, string $identifier): ?array {
    $stmt = $pdo->prepare("SELECT * FROM administrators WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $identifier]);
    return $stmt->fetch() ?: null;
}

// ---- Automatic role detection ----------------------------------------------
// One sign-in form for everyone: the account type is found from the credentials.
// Emails are unique across students and administrators, and student numbers only
// exist for students, so an identifier can match at most one account. If old data
// ever has the same email in both tables, the account whose password matches wins.
$candidates = [];
if ($s = findStudent($pdo, $identifier)) {
    $candidates[] = ['student', $s];
}
if ($a = findAdmin($pdo, $identifier)) {
    $candidates[] = ['admin', $a];
}
// The tab the person chose is tried first; the other role is still checked so a
// sign-in on the wrong tab simply opens the correct portal.
if (($_POST['role'] ?? '') === 'admin') {
    $candidates = array_reverse($candidates);
}

foreach ($candidates as [$role, $row]) {
    if (!password_verify($password, $row['password'])) {
        continue;
    }
    if ($role === 'student' && $row['status'] === 'Pending') {
        backToLogin('info', 'Your account is waiting for administrator approval. You will receive an email once it is approved.');
    }
    if ($row['status'] !== 'Active') {
        backToLogin('error', $role === 'admin'
            ? 'This administrator account is deactivated. Please contact the system administrator.'
            : 'This student account is inactive. Please contact the registrar or the MIS Office.');
    }
    // Transparently upgrade old hashes (e.g. cost 10 → current default)
    if (password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
        $table = $role === 'admin' ? 'administrators' : 'students';
        $pk    = $role === 'admin' ? 'admin_id' : 'student_id';
        $pdo->prepare("UPDATE {$table} SET password = :p WHERE {$pk} = :id")
            ->execute(['p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $row[$pk]]);
    }
    startUserSession($pdo, $role, $row);
}

// Same message whether the account exists or not (no account enumeration)
recordFailedLogin($pdo, $identifier);
backToLogin('error', 'Incorrect email, student number or password. Please try again.');
