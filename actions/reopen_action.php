<?php
// actions/reopen_action.php - Student reopens a resolved concern that is not actually fixed
//
// Rules: owner only, status Resolved, within REOPEN_WINDOW_DAYS of resolution,
// at most REOPEN_MAX times, reason required. The ticket returns to Pending with a
// fresh SLA window; any satisfaction rating is cleared (old value kept in the audit log).
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();

$concernId = (int)($_POST['concern_id'] ?? 0);
$back = "../student/concern_details.php?id={$concernId}";
requirePostWithCsrf($back);

$reason = trim($_POST['reason'] ?? '');
if (mb_strlen($reason) < 10) {
    setFlash('error', 'Please explain why the concern is not resolved (at least 10 characters).');
    header("Location: {$back}");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM concerns WHERE concern_id = :id AND student_id = :sid");
$stmt->execute(['id' => $concernId, 'sid' => $user['id']]);
$concern = $stmt->fetch();

if (!$concern || !canReopenConcern($concern)) {
    setFlash('error', 'This concern can no longer be reopened. Please submit a new concern instead.');
    header("Location: {$back}");
    exit;
}

$now      = date('Y-m-d H:i:s');
$newDueAt = computeSlaDueAt($now, (int)$concern['sla_hours']);

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE concerns SET
            status_id        = 1,
            resolved_at      = NULL,
            sla_due_at       = :due,
            sla_alert_level  = 'none',
            reopen_count     = reopen_count + 1,
            last_reopened_at = :now
        WHERE concern_id = :id AND status_id = 3
    ")->execute(['due' => $newDueAt, 'now' => $now, 'id' => $concernId]);

    $r = $pdo->prepare("SELECT rating FROM feedback WHERE concern_id = :id");
    $r->execute(['id' => $concernId]);
    if (($oldRating = $r->fetchColumn()) !== false) {
        // Kept for the record (never deleted): the old rating is detached from the ticket so the
        // student can rate the new resolution, and its subject says which ticket it belonged to
        $pdo->prepare("
            UPDATE feedback
            SET concern_id = NULL, subject = LEFT(CONCAT(subject, ' [', :t, ', before it was reopened]'), 255)
            WHERE concern_id = :id
        ")->execute(['t' => $concern['ticket_id'], 'id' => $concernId]);
        logConcernAudit($pdo, $concernId, 'student', (int)$user['id'], 'rating_cleared', "{$oldRating}/5", null);
    }

    logConcernAudit($pdo, $concernId, 'student', (int)$user['id'], 'reopened', 'Resolved', mb_substr($reason, 0, 255));
    logConcernAudit($pdo, $concernId, 'system', null, 'sla_reset', $concern['sla_due_at'], $newDueAt);

    notifyAllAdmins($pdo, $concernId, $concern['priority'] === 'Critical' ? 'rrt' : 'new_concern',
        "Reopened: {$concern['ticket_id']}",
        concernSenderName($concern, $user['name']) . " reopened \"{$concern['title']}\": " . mb_substr($reason, 0, 150));

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ICMS] Reopen failed: ' . $e->getMessage());
    setFlash('error', 'Could not reopen the concern. Please try again.');
    header("Location: {$back}");
    exit;
}

try {
    queueEmailToAdmins($pdo, "Concern reopened: {$concern['ticket_id']}", [
        concernSenderName($concern, $user['name']) . " reopened the concern \"{$concern['title']}\" ({$concern['ticket_id']}) because it is not resolved.",
        "Reason given:\n{$reason}",
        'The ticket is back to Pending with a new SLA deadline of ' . date('M j, Y · g:i A', strtotime($newDueAt)) . '.',
    ], appUrl("admin/concern_details.php?id={$concernId}"), 'Review ticket');
} catch (Throwable $e) {
    error_log('[ICMS] Queue email failed: ' . $e->getMessage());
}

setFlash('success', "Ticket {$concern['ticket_id']} has been reopened. The administrators have been notified.");
header("Location: {$back}");
exit;
