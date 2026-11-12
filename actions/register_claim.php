<?php
// actions/register_claim.php - Claim a masterlist profile from register.php (JSON).
//   Step 1  POST action=verify, claim_ref, student_no, csrf_token
//           -> checks the student number only; the password step unlocks only after this succeeds
//   Step 2  POST claim_ref, student_no, password, password_confirm, csrf_token
//        (+ email, only when the masterlist record has no email; otherwise the record's email is used)
//
// The visitor picked their name in the live search (register_lookup.php gave a session-bound claim_ref).
// To prove it's really them they must type their own student number, which must match the Registrar's
// record for that name. Wrong numbers count toward the shared login limiter (5 per name per 15 minutes),
// so the hidden digits cannot be guessed. On success the account is active and verified at once.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Cache-Control: no-store');

$fail = fn(string $message, string $field = '', int $code = 422) => jsonResponse(['status' => 'error', 'message' => $message, 'field' => $field], $code);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !isValidCsrf($_POST['csrf_token'] ?? null)) {
    $fail('Your session expired. Please reload the page and try again.', '', 403);
}
if (isLoggedIn()) {
    $fail('You are already signed in.', '', 409);
}

$ref = (string)($_POST['claim_ref'] ?? '');
$masterlistId = $_SESSION['register_claim_refs'][$ref] ?? null;
$entry = null;
if ($masterlistId) {
    $s = $pdo->prepare("SELECT * FROM student_masterlist WHERE masterlist_id = :id AND archived_at IS NULL");
    $s->execute(['id' => $masterlistId]);
    $entry = $s->fetch() ?: null;
}
if (!$entry) {
    $fail('Please search for your name again.');
}

$limiterKey = 'claim:' . $entry['student_no'];
$wait = loginLockoutRemaining($pdo, $limiterKey);
if ($wait > 0) {
    $fail('Too many incorrect student numbers for this name. Try again in ' . max(1, (int)ceil($wait / 60)) . ' minute(s), or ask the Registrar / MIS Office for help.', 'student_no', 429);
}

$studentNo = strtoupper(trim((string)($_POST['student_no'] ?? '')));
// The Registrar's email on file wins; the student types one only when the record has none
$recordEmail = mb_strtolower(trim((string)$entry['email']), 'UTF-8');
$email     = $recordEmail !== '' ? $recordEmail : mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
$password  = (string)($_POST['password'] ?? '');
$confirm   = (string)($_POST['password_confirm'] ?? '');

if ($studentNo === '') {
    $fail('Enter your student number to confirm it is you.', 'student_no');
}
if (!hash_equals(strtoupper($entry['student_no']), $studentNo)) {
    recordFailedLogin($pdo, $limiterKey);
    unset($_SESSION['register_claim_verified'][$ref]);
    $fail("That student number doesn't match the Registrar's record for this name. Check your student ID or registration form.", 'student_no');
}
if (($_POST['action'] ?? '') === 'verify') {
    clearFailedLogins($pdo, $limiterKey);
    $_SESSION['register_claim_verified'][$ref] = true;
    jsonResponse(['status' => 'ok', 'verified' => true]);
}
// No password is accepted until the student number was verified in step 1
if (empty($_SESSION['register_claim_verified'][$ref])) {
    $fail('Please verify your student number first.', 'student_no');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $fail('Please enter a valid email address.', 'email');
}
if ($err = validatePasswordPolicy($password)) {
    $fail($err, 'password');
}
if (!hash_equals($password, $confirm)) {
    $fail('Passwords do not match.', 'password_confirm');
}

$taken = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_no = :n");
$taken->execute(['n' => $entry['student_no']]);
if ((int)$taken->fetchColumn() > 0) {
    $fail('This profile has already been claimed. Use Forgot Password if you cannot sign in.');
}
if (emailIsTaken($pdo, $email)) {
    $fail($recordEmail !== ''
        ? 'The email on your record is already used by another account. Please ask the Registrar / MIS Office for help.'
        : 'That email address is already registered. Use Forgot Password if you cannot sign in.', 'email');
}

$department = in_array($entry['department'], SNSU_DEPARTMENTS, true) ? $entry['department'] : SNSU_DEPARTMENTS[0];
foreach (SNSU_DEPARTMENTS as $d) { // tolerate "College of Engineering" vs. "college of engineering"
    if (strcasecmp((string)$entry['department'], $d) === 0) {
        $department = $d;
    }
}
$name = displayName($entry['name']);

try {
    $pdo->prepare("
        INSERT INTO students (student_no, name, email, password, department, program, status,
                              registration_source, masterlist_verified, registration_note, approved_at, created_at)
        VALUES (:no, :name, :email, :password, :department, :program, 'Active',
                'self', 1, 'Claimed their masterlist profile.', NOW(), NOW())
    ")->execute([
        'no'         => $entry['student_no'],
        'name'       => $name,
        'email'      => $email,
        'password'   => password_hash($password, PASSWORD_DEFAULT),
        'department' => $department,
        'program'    => trim((string)$entry['program']) !== '' ? $entry['program'] : 'Not specified',
    ]);
} catch (PDOException $e) {
    error_log('[ICMS] Profile claim failed: ' . $e->getMessage());
    $fail('Registration failed. The email or student number may already be in use.');
}

$newId = (int)$pdo->lastInsertId();
clearFailedLogins($pdo, $limiterKey);
unset($_SESSION['register_claim_refs'][$ref], $_SESSION['register_claim_verified'][$ref]);
showRecoveryCodeNext(issueRecoveryCode($pdo, $newId), $name,
    'Your account is ready. Before you sign in, save this recovery code: it lets you reset your password yourself if you forget it.',
    'index.php');
setFlash('success', "Welcome, {$name}! Your profile is claimed and verified. Sign in with your email or student number.");
jsonResponse(['status' => 'ok', 'redirect' => 'recovery_code.php']);
