<?php
// actions/register_action.php - Student self-registration processor
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requirePostWithCsrf('../register.php');

if (isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$data = [
    'student_no' => strtoupper(trim($_POST['student_no'] ?? '')),
    'name'       => trim(preg_replace('/\s+/', ' ', $_POST['name'] ?? '')),
    'email'      => mb_strtolower(trim($_POST['email'] ?? ''), 'UTF-8'),
    'department' => trim($_POST['department'] ?? ''),
    'program'    => trim($_POST['program'] ?? ''),
];
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

function registerFail(array $data, string $message): void {
    $_SESSION['register_old'] = $data;
    setFlash('error', $message);
    header('Location: ../register.php');
    exit;
}

if (in_array('', $data, true) || $password === '') {
    registerFail($data, 'Please complete all required fields.');
}
if (!preg_match('/^[A-Z0-9-]{4,50}$/', $data['student_no'])) {
    registerFail($data, 'Student number may only contain letters, numbers and dashes (e.g. SNSU-2025-01234).');
}
if (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 150) {
    registerFail($data, 'Please enter your full name.');
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 150) {
    registerFail($data, 'Please enter a valid email address.');
}
if (!in_array($data['department'], SNSU_DEPARTMENTS, true)) {
    registerFail($data, 'Please choose a valid college / department.');
}
if (mb_strlen($data['program']) > 150) {
    registerFail($data, 'Degree program name is too long.');
}
if ($err = validatePasswordPolicy($password)) {
    registerFail($data, $err);
}
if (!hash_equals($password, $confirm)) {
    registerFail($data, 'Passwords do not match.');
}

$dupNo = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_no = :n");
$dupNo->execute(['n' => $data['student_no']]);
if ((int)$dupNo->fetchColumn() > 0) {
    registerFail($data, 'That student number is already registered. Use Forgot Password if you cannot sign in.');
}
if (emailIsTaken($pdo, $data['email'])) {
    registerFail($data, 'That email address is already registered. Use Forgot Password if you cannot sign in.');
}

// Verify against the Registrar's masterlist: a match is approved at once, anything else waits for an administrator
$check  = checkRegistrationAgainstMasterlist($pdo, $data['student_no'], $data['name'], $data['email']);
$status = $check['verified'] ? 'Active' : 'Pending';

try {
    $pdo->prepare("
        INSERT INTO students (student_no, name, email, password, department, program, status,
                              registration_source, masterlist_verified, registration_note, approved_at, created_at)
        VALUES (:student_no, :name, :email, :password, :department, :program, :status,
                'self', :verified, :note, :approved_at, NOW())
    ")->execute($data + [
        'password'    => password_hash($password, PASSWORD_DEFAULT),
        'status'      => $status,
        'verified'    => $check['verified'] ? 1 : 0,
        'note'        => $check['note'],
        'approved_at' => $check['verified'] ? date('Y-m-d H:i:s') : null,
    ]);
} catch (PDOException $e) {
    // Unique-key race between the checks above and the insert
    error_log('[ICMS] Registration failed: ' . $e->getMessage());
    registerFail($data, 'Registration failed. The email or student number may already be in use.');
}

// Every new account gets a recovery code, shown once on the next page
showRecoveryCodeNext(issueRecoveryCode($pdo, (int)$pdo->lastInsertId()), $data['name'],
    $check['verified']
        ? 'Your account is ready. Save this recovery code: it lets you reset your password yourself if you forget it.'
        : 'Your account is waiting for administrator approval. Save this recovery code now: it lets you reset your password yourself later.',
    'index.php');

if ($check['verified']) {
    setFlash('success', "Account created for {$data['name']}! Your student number was verified, so you can sign in now with your email or student number.");
    header('Location: ../recovery_code.php');
    exit;
}

// Waiting for approval: tell the administrators
try {
    notifyAllAdmins($pdo, null, 'registration', 'New registration to approve',
        "{$data['name']} ({$data['student_no']}) registered. {$check['note']}");
    queueEmailToAdmins($pdo, "[ICMS] Registration waiting for approval: {$data['student_no']}", [
        "{$data['name']} ({$data['student_no']}, {$data['email']}) created a student account.",
        "Check: {$check['note']}",
        'Verify that this person is an enrolled student, then approve or reject the account on the Users page.',
    ], appUrl('admin/users.php'), 'Review registration');
} catch (Throwable $e) {
    error_log('[ICMS] Registration notice failed: ' . $e->getMessage());
}

setFlash('info', "Account created for {$data['name']}. Your student number is not yet on the Registrar's list, so an administrator will verify and approve your account first. You will receive an email once it is approved.");
header('Location: ../recovery_code.php');
exit;
