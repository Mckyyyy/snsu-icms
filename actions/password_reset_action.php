<?php
// actions/password_reset_action.php
//   request       (public) : email a one-time reset link (SMTP configured) or notify admins (offline)
//   complete_link (public) : set a new password from a valid emailed link
//   recovery      (public) : student number/email + recovery code -> new password (no email, no admin)
//   reset     (admin)   : issue a temporary password; user must change it at next login
//   dismiss   (admin)   : close a request without resetting
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

$action = $_POST['action'] ?? '';

// ---------------------------------------------------------------- public
const RESET_LINK_TTL_MINUTES = 60;

if ($action === 'request') {
    requirePostWithCsrf('../forgot_password.php');

    $identifier = trim($_POST['identifier'] ?? '');
    // From register.php's "existing account" window: an opaque ref from actions/register_lookup.php
    // stands in for the student number, which the visitor only saw masked
    $ref = (string)($_POST['account_ref'] ?? '');
    if ($ref !== '') {
        $sid = $_SESSION['register_lookup_refs'][$ref] ?? null;
        $identifier = '';
        if ($sid) {
            $n = $pdo->prepare("SELECT student_no FROM students WHERE student_id = :id");
            $n->execute(['id' => $sid]);
            $identifier = (string)$n->fetchColumn();
        }
    }
    $emailMode  = mailCanDeliver();
    // Same generic answer whether or not the account exists (prevents account enumeration)
    $generic = $emailMode
        ? 'If an account matches what you entered, a password reset link has been sent to its email address (it expires in ' . RESET_LINK_TTL_MINUTES . ' minutes). No email after a few minutes? Use your recovery code, or the administrator will help you.'
        : 'If an account matches what you entered, the administrator has been notified. Students can also reset it right away with their recovery code (Forgot Password → "I have my recovery code").';

    if ($identifier === '' || loginLockoutRemaining($pdo, 'reset:' . $identifier) > 0) {
        setFlash('info', $generic);
        header('Location: ../index.php');
        exit;
    }
    // Count each request toward the shared limiter so this form can't be spammed
    recordFailedLogin($pdo, 'reset:' . $identifier);

    $s = $pdo->prepare("SELECT student_id AS id, name, email, student_no AS ref FROM students WHERE email = :e OR student_no = :n LIMIT 1");
    $s->execute(['e' => $identifier, 'n' => $identifier]);
    $account = $s->fetch();
    $type = 'student';
    if (!$account) {
        $a = $pdo->prepare("SELECT admin_id AS id, name, email, email AS ref FROM administrators WHERE email = :e LIMIT 1");
        $a->execute(['e' => $identifier]);
        $account = $a->fetch();
        $type = 'admin';
    }

    if ($account && $emailMode) {
        // ---- Self-service: one-time link by email (only the hash is stored)
        $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_type = :t AND user_id = :id AND used_at IS NULL")
            ->execute(['t' => $type, 'id' => $account['id']]);
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("
            INSERT INTO password_reset_tokens (user_type, user_id, token_hash, expires_at)
            VALUES (:t, :id, :h, NOW() + INTERVAL " . RESET_LINK_TTL_MINUTES . " MINUTE)
        ")->execute(['t' => $type, 'id' => $account['id'], 'h' => hash('sha256', $token)]);

        $queued = queueEmail($pdo, $account['email'], $account['name'], 'Reset your ICMS password', [
            "Hi {$account['name']},",
            'We received a request to reset the password of your account. Click the button below to choose a new password. The link can be used once and expires in ' . RESET_LINK_TTL_MINUTES . ' minutes.',
            'If you did not request this, you can safely ignore this email — your password will not change.',
        ], appUrl('reset_password.php?token=' . $token), 'Choose a new password');
        // Send it now and check that it really left. If the server cannot send mail (e.g. SMTP
        // blocked by the web host), the request goes to the administrators instead.
        $delivered = $queued && sendQueuedEmailNow($pdo, (int)$pdo->lastInsertId());
        if (!$delivered) {
            $emailMode = false;
        }
    }
    if ($account && !$emailMode) {
        // ---- Offline fallback: admin-assisted temporary password
        $pending = $pdo->prepare("SELECT COUNT(*) FROM password_reset_requests WHERE user_type = :t AND user_id = :id AND status = 'Pending'");
        $pending->execute(['t' => $type, 'id' => $account['id']]);
        if ((int)$pending->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO password_reset_requests (user_type, user_id) VALUES (:t, :id)")
                ->execute(['t' => $type, 'id' => $account['id']]);
            notifyAllAdmins($pdo, null, 'password_reset', 'Password reset request',
                ucfirst($type) . " {$account['name']} ({$account['ref']}) requested a password reset.");
        }
    }

    setFlash('info', $generic);
    header('Location: ../index.php');
    exit;
}

// ------------------------------------------------- recovery code (students)
if ($action === 'recovery') {
    $back = '../forgot_password.php?tab=code';
    requirePostWithCsrf($back);

    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $code       = (string)($_POST['recovery_code'] ?? '');
    $password   = (string)($_POST['password'] ?? '');
    $confirm    = (string)($_POST['password_confirm'] ?? '');
    $fail = function (string $message) use ($back, $identifier) {
        $_SESSION['recovery_old_identifier'] = mb_substr($identifier, 0, 150);
        setFlash('error', $message);
        header("Location: {$back}");
        exit;
    };
    $limiterKey = 'recovery:' . $identifier;

    if ($identifier === '' || trim($code) === '') {
        $fail('Enter your student number (or email) and your recovery code.');
    }
    if (($wait = loginLockoutRemaining($pdo, $limiterKey)) > 0) {
        $fail('Too many incorrect attempts. Try again in ' . max(1, (int)ceil($wait / 60)) . ' minute(s), or ask the MIS Office for a temporary password.');
    }
    if ($err = validatePasswordPolicy($password)) {
        $fail($err);
    }
    if (!hash_equals($password, $confirm)) {
        $fail('The new passwords do not match.');
    }

    $s = $pdo->prepare("
        SELECT student_id, name, email, student_no, recovery_code_hash, archived_at
        FROM students WHERE email = :e OR student_no = :n LIMIT 1
    ");
    $s->execute(['e' => mb_strtolower($identifier), 'n' => strtoupper($identifier)]);
    $st = $s->fetch();
    // One answer for every failure (unknown account, archived, no code, wrong code)
    if (!$st || $st['archived_at'] !== null || !recoveryCodeMatches($st['recovery_code_hash'], $code)) {
        recordFailedLogin($pdo, $limiterKey);
        $fail('The student number / email or the recovery code is not correct. Check the code you saved (it looks like ABCD-2345-EFGH).');
    }

    $sid = (int)$st['student_id'];
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE students SET password = :p, must_change_password = 0 WHERE student_id = :id")
        ->execute(['p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $sid]);
    $newCode = issueRecoveryCode($pdo, $sid); // the used code stops working
    $pdo->prepare("UPDATE password_reset_requests SET status = 'Completed', handled_at = NOW() WHERE user_type = 'student' AND user_id = :id AND status = 'Pending'")
        ->execute(['id' => $sid]);
    $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_type = 'student' AND user_id = :id AND used_at IS NULL")
        ->execute(['id' => $sid]);
    $pdo->commit();

    foreach ([$limiterKey, $st['email'], $st['student_no']] as $key) {
        clearFailedLogins($pdo, $key);
    }
    notifyUser($pdo, 'student', $sid, null, 'password_reset', 'Password changed',
        'Your password was changed using your recovery code. If this was not you, contact the MIS Office right away.');
    queueEmail($pdo, $st['email'], $st['name'], 'Your ICMS password was changed', [
        "Hi {$st['name']},",
        'Your password was just changed using your recovery code, and a new recovery code was issued.',
        'If this was not you, contact the MIS Office right away.',
    ], appUrl('index.php'), 'Sign in');

    showRecoveryCodeNext($newCode, $st['name'],
        'Your password has been changed. Your old recovery code no longer works. Save this new one.',
        'index.php');
    setFlash('success', 'Password changed. Sign in with your new password.');
    header('Location: ../recovery_code.php');
    exit;
}

/**
 * Look up a valid (unused, unexpired) reset token row, or null.
 */
function findResetToken(PDO $pdo, string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT * FROM password_reset_tokens
        WHERE token_hash = :h AND used_at IS NULL AND expires_at > NOW()
    ");
    $stmt->execute(['h' => hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

if ($action === 'complete_link') {
    $token = (string)($_POST['token'] ?? '');
    requirePostWithCsrf('../reset_password.php?token=' . urlencode($token));

    $row = findResetToken($pdo, $token);
    if (!$row) {
        setFlash('error', 'This reset link is invalid or has expired. Please request a new one.');
        header('Location: ../forgot_password.php');
        exit;
    }
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['new_password_confirm'] ?? '';
    if ($err = validatePasswordPolicy($new)) {
        setFlash('error', $err);
        header('Location: ../reset_password.php?token=' . urlencode($token));
        exit;
    }
    if (!hash_equals($new, $confirm)) {
        setFlash('error', 'Passwords do not match.');
        header('Location: ../reset_password.php?token=' . urlencode($token));
        exit;
    }

    $table = $row['user_type'] === 'admin' ? 'administrators' : 'students';
    $pk    = $row['user_type'] === 'admin' ? 'admin_id' : 'student_id';
    $cols  = $row['user_type'] === 'admin' ? 'email' : 'email, student_no';

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE {$table} SET password = :p, must_change_password = 0 WHERE {$pk} = :id")
        ->execute(['p' => password_hash($new, PASSWORD_DEFAULT), 'id' => $row['user_id']]);
    $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_type = :t AND user_id = :id AND used_at IS NULL")
        ->execute(['t' => $row['user_type'], 'id' => $row['user_id']]);
    $pdo->commit();

    $u = $pdo->prepare("SELECT {$cols} FROM {$table} WHERE {$pk} = :id");
    $u->execute(['id' => $row['user_id']]);
    foreach ((array)$u->fetch() as $ident) {
        clearFailedLogins($pdo, (string)$ident);
    }

    setFlash('success', 'Your password has been reset. You can now sign in with your new password.');
    header('Location: ../index.php');
    exit;
}

// ---------------------------------------------------------------- admin
requireAdmin();
$admin = getCurrentUser();
requirePostWithCsrf('../admin/users.php');

$type = ($_POST['type'] ?? 'student') === 'admin' ? 'admin' : 'student';
$id   = (int)($_POST['id'] ?? 0);
$table = $type === 'student' ? 'students' : 'administrators';
$pk    = $type === 'student' ? 'student_id' : 'admin_id';

if ($action === 'reset') {
    if ($type === 'admin' && $id === (int)$admin['id']) {
        setFlash('error', 'Use your Profile page to change your own password.');
        header('Location: ../admin/users.php');
        exit;
    }
    $u = $pdo->prepare("SELECT name, email" . ($type === 'student' ? ', student_no' : '') . " FROM {$table} WHERE {$pk} = :id");
    $u->execute(['id' => $id]);
    $target = $u->fetch();
    if (!$target) {
        setFlash('error', 'User not found.');
        header('Location: ../admin/users.php');
        exit;
    }

    $temp = generateTemporaryPassword();
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE {$table} SET password = :p, must_change_password = 1 WHERE {$pk} = :id")
        ->execute(['p' => password_hash($temp, PASSWORD_DEFAULT), 'id' => $id]);
    $pdo->prepare("
        UPDATE password_reset_requests SET status = 'Completed', handled_by = :a, handled_at = NOW()
        WHERE user_type = :t AND user_id = :id AND status = 'Pending'
    ")->execute(['a' => $admin['id'], 't' => $type, 'id' => $id]);
    $pdo->commit();
    clearFailedLogins($pdo, $target['email']);
    if (!empty($target['student_no'])) {
        clearFailedLogins($pdo, $target['student_no']);
    }

    // Security notice (never includes the password itself)
    queueEmail($pdo, $target['email'], $target['name'], 'Your password was reset by an administrator', [
        "Hi {$target['name']},",
        'An administrator reset the password of your ICMS account. Obtain the temporary password from the administrator in person; you will be asked to choose a new password when you sign in.',
        'If you did not request this, contact the MIS Office immediately.',
    ], appUrl('index.php'), 'Sign in');

    // Shown once on the Users page in a persistent panel (toasts disappear too quickly)
    $_SESSION['temp_password_notice'] = ['name' => $target['name'], 'email' => $target['email'], 'password' => $temp];
    logAdminActivity($pdo, (int)$admin['id'], 'password_reset', $type, $id, "{$target['name']} <{$target['email']}>");
    setFlash('success', "Temporary password generated for {$target['name']}.");
} elseif ($action === 'dismiss') {
    $pdo->prepare("
        UPDATE password_reset_requests SET status = 'Dismissed', handled_by = :a, handled_at = NOW()
        WHERE request_id = :rid AND status = 'Pending'
    ")->execute(['a' => $admin['id'], 'rid' => (int)($_POST['request_id'] ?? 0)]);
    logAdminActivity($pdo, (int)$admin['id'], 'reset_request_dismissed', 'request', (int)($_POST['request_id'] ?? 0), null);
    setFlash('info', 'Reset request dismissed.');
}

header('Location: ../admin/users.php');
exit;
