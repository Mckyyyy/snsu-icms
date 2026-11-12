<?php
// actions/withdraw_action.php - Student withdraws their own concern
//
// Rules: owner only, only while the concern is still Pending (before staff start
// working on it), a reason is required. The record is kept with status "Withdrawn"
// so it stays in the audit trail and reports; it simply stops being tracked.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();

$concernId = (int)($_POST['concern_id'] ?? 0);
$back = "../student/concern_details.php?id={$concernId}";
requirePostWithCsrf($back);

const WITHDRAW_REASONS = [
    'resolved_itself' => 'The issue has already been fixed',
    'mistake'         => 'Submitted by mistake',
    'duplicate'       => 'Duplicate of another concern',
    'other'           => 'Other reason',
];

$reasonKey = (string)($_POST['reason'] ?? '');
$details   = trim((string)($_POST['details'] ?? ''));
if (!array_key_exists($reasonKey, WITHDRAW_REASONS)) {
    setFlash('error', 'Please choose why you are withdrawing this concern.');
    header("Location: {$back}");
    exit;
}
if ($reasonKey === 'other' && mb_strlen($details) < 5) {
    setFlash('error', 'Please describe the reason (at least 5 characters).');
    header("Location: {$back}");
    exit;
}
$reason = WITHDRAW_REASONS[$reasonKey] . ($details !== '' ? ': ' . $details : '');
$reason = mb_substr($reason, 0, 255);

$stmt = $pdo->prepare("SELECT * FROM concerns WHERE concern_id = :id AND student_id = :sid");
$stmt->execute(['id' => $concernId, 'sid' => $user['id']]);
$concern = $stmt->fetch();

if (!$concern) {
    setFlash('error', 'Concern not found.');
    header('Location: ../student/my_concerns.php');
    exit;
}
if ((int)$concern['status_id'] !== 1) {
    setFlash('error', 'Only Pending concerns can be withdrawn. This one is already being handled; please message the administrator instead.');
    header("Location: {$back}");
    exit;
}

try {
    $pdo->beginTransaction();
    // status_id = 1 in the WHERE guards against an admin changing it at the same moment
    $up = $pdo->prepare("
        UPDATE concerns SET status_id = 4, withdrawn_at = NOW(), withdraw_reason = :r, sla_alert_level = 'none'
        WHERE concern_id = :id AND status_id = 1
    ");
    $up->execute(['r' => $reason, 'id' => $concernId]);
    if ($up->rowCount() === 0) {
        throw new RuntimeException('Status changed before the withdrawal was saved.');
    }

    logConcernAudit($pdo, $concernId, 'student', (int)$user['id'], 'status_changed', 'Pending', 'Withdrawn');
    logConcernAudit($pdo, $concernId, 'student', (int)$user['id'], 'withdrawn', null, $reason);

    // Tell whoever owns it (or every admin when unassigned) so nobody keeps working on it
    $title = "Withdrawn: {$concern['ticket_id']}";
    $msg   = concernSenderName($concern, $user['name']) . " withdrew \"{$concern['title']}\". Reason: {$reason}";
    if ($concern['assigned_admin_id']) {
        notifyUser($pdo, 'admin', (int)$concern['assigned_admin_id'], $concernId, 'withdrawn', $title, $msg);
    } else {
        notifyAllAdmins($pdo, $concernId, 'withdrawn', $title, $msg);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ICMS] Withdraw failed: ' . $e->getMessage());
    setFlash('error', 'This concern could not be withdrawn because its status just changed. Please review the latest update.');
    header("Location: {$back}");
    exit;
}

setFlash('success', "Ticket {$concern['ticket_id']} has been withdrawn. It stays in your history for reference.");
header("Location: {$back}");
exit;
