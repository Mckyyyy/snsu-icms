<?php
// actions/registration_action.php - Administrators approve or reject self-registered student accounts
//
// approve: the account becomes Active and the student is emailed.
// reject:  the registration is kept in registration_rejections (who, when, why). The Pending account
//          itself is released (it has no concerns, since it could never sign in) so the student number
//          and email stay free for the real owner. The student is emailed with the optional reason.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$admin = getCurrentUser();
requirePostWithCsrf('../admin/users.php');

$action    = $_POST['action'] ?? '';
$studentId = (int)($_POST['student_id'] ?? 0);
$back      = '../admin/users.php';

$s = $pdo->prepare("SELECT * FROM students WHERE student_id = :id AND status = 'Pending'");
$s->execute(['id' => $studentId]);
$student = $s->fetch();
if (!$student) {
    setFlash('warning', 'That registration was already handled.');
    header("Location: {$back}");
    exit;
}

if ($action === 'approve') {
    $pdo->prepare("UPDATE students SET status = 'Active', approved_by = :a, approved_at = NOW() WHERE student_id = :id AND status = 'Pending'")
        ->execute(['a' => $admin['id'], 'id' => $studentId]);

    // Optionally remember the student on the masterlist so the list stays complete
    if (!empty($_POST['add_to_masterlist'])) {
        $pdo->prepare("
            INSERT INTO student_masterlist (student_no, name, email, department, program, imported_by)
            VALUES (:no, :name, :email, :dept, :prog, :a)
            ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), department = VALUES(department), program = VALUES(program)
        ")->execute(['no' => $student['student_no'], 'name' => $student['name'], 'email' => $student['email'],
                     'dept' => $student['department'], 'prog' => $student['program'], 'a' => $admin['id']]);
    }

    logAdminActivity($pdo, (int)$admin['id'], 'student_approved', 'student', $studentId, "{$student['name']} ({$student['student_no']})");
    sendRegistrationApprovedEmail($pdo, $student);
    setFlash('success', "{$student['name']}'s account is approved. They can sign in now.");
} elseif ($action === 'reject') {
    $reason = trim(mb_substr((string)($_POST['reason'] ?? ''), 0, 500));
    $pdo->beginTransaction();
    $pdo->prepare("
        INSERT INTO registration_rejections (student_no, name, email, department, program, registered_at, registration_note, reason, rejected_by)
        VALUES (:no, :name, :email, :dept, :prog, :reg, :note, :reason, :a)
    ")->execute(['no' => $student['student_no'], 'name' => $student['name'], 'email' => $student['email'],
                 'dept' => $student['department'], 'prog' => $student['program'], 'reg' => $student['created_at'],
                 'note' => $student['registration_note'], 'reason' => $reason !== '' ? $reason : null, 'a' => $admin['id']]);
    $pdo->prepare("DELETE FROM students WHERE student_id = :id AND status = 'Pending'")->execute(['id' => $studentId]);
    $pdo->commit();
    logAdminActivity($pdo, (int)$admin['id'], 'student_rejected', 'student', $studentId,
        "{$student['name']} ({$student['student_no']})" . ($reason !== '' ? " - {$reason}" : ''));
    try {
        queueEmail($pdo, $student['email'], $student['name'], 'Your ICMS registration was not approved', array_filter([
            "Hello {$student['name']},",
            "Your registration with student number {$student['student_no']} could not be verified, so the account was not approved.",
            $reason !== '' ? "Reason: {$reason}" : null,
            'If you are an enrolled student, please contact the MIS Office or the Registrar, then register again.',
        ]));
    } catch (Throwable $e) {
        error_log('[ICMS] Rejection email failed: ' . $e->getMessage());
    }
    setFlash('success', "The registration of {$student['name']} was rejected. It is kept in the Rejected Registrations record.");
} else {
    setFlash('error', 'Unknown action.');
}

header("Location: {$back}");
exit;
