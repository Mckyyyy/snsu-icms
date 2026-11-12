<?php
// actions/response_action.php - Admin response + status update (Use cases: Respond to Tickets, Update Concern Status)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$user = getCurrentUser();

$concernId = (int)($_POST['concern_id'] ?? 0);
$back      = $concernId > 0 ? "../admin/concern_details.php?id={$concernId}" : '../admin/concerns.php';
requirePostWithCsrf($back);

$statusId           = (int)($_POST['status_id'] ?? 0);
$responseText       = trim($_POST['response_text'] ?? '');
$departmentEndorsed = trim($_POST['department_endorsed'] ?? '');

if ($concernId <= 0 || $statusId <= 0 || $responseText === '') {
    setFlash('error', 'Please select a status and enter an official response message.');
    header("Location: {$back}");
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.*, s.status_name FROM concerns c
    JOIN statuses s ON s.status_id = c.status_id
    WHERE c.concern_id = :id
");
$stmt->execute(['id' => $concernId]);
$concern = $stmt->fetch();
if (!$concern) {
    setFlash('error', 'Concern ticket not found.');
    header('Location: ../admin/concerns.php');
    exit;
}

$st = $pdo->prepare("SELECT status_name FROM statuses WHERE status_id = :id");
$st->execute(['id' => $statusId]);
$newStatusName = $st->fetchColumn();
if ($newStatusName === false) {
    setFlash('error', 'Invalid status selected.');
    header("Location: {$back}");
    exit;
}

if ((int)$concern['status_id'] === 4 || $statusId === 4) {
    setFlash('error', 'This concern was withdrawn by the student and can no longer be updated.');
    header("Location: {$back}");
    exit;
}

$oldStatusName = $concern['status_name'];
$statusChanged = $oldStatusName !== $newStatusName;
$now = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO responses (concern_id, admin_id, response_text, department_endorsed, status_id, date_responded)
        VALUES (:concern_id, :admin_id, :response_text, :department_endorsed, :status_id, :now)
    ")->execute([
        'concern_id'          => $concernId,
        'admin_id'            => $user['id'],
        'response_text'       => $responseText,
        'department_endorsed' => $departmentEndorsed !== '' ? $departmentEndorsed : null,
        'status_id'           => $statusId,
        'now'                 => $now,
    ]);

    // Lifecycle timestamps used for SLA tracking and resolution-time reports
    $pdo->prepare("
        UPDATE concerns SET
            status_id         = :status_id,
            first_response_at = COALESCE(first_response_at, :now1),
            resolved_at       = CASE WHEN :is_resolved = 1 THEN COALESCE(resolved_at, :now2) ELSE NULL END,
            assigned_admin_id = COALESCE(assigned_admin_id, :admin_id)
        WHERE concern_id = :concern_id
    ")->execute([
        'status_id'   => $statusId,
        'now1'        => $now,
        'now2'        => $now,
        'is_resolved' => $newStatusName === 'Resolved' ? 1 : 0,
        'admin_id'    => $user['id'],
        'concern_id'  => $concernId,
    ]);

    logConcernAudit($pdo, $concernId, 'admin', (int)$user['id'], 'responded', null, mb_substr($responseText, 0, 255));
    if ($statusChanged) {
        logConcernAudit($pdo, $concernId, 'admin', (int)$user['id'], 'status_changed', $oldStatusName, $newStatusName);
    }
    if ($departmentEndorsed !== '') {
        logConcernAudit($pdo, $concernId, 'admin', (int)$user['id'], 'endorsed', null, $departmentEndorsed);
    }

    // Notify the student (Sequence step 4: "dispatches live notification to Student Dashboard")
    $notifTitle = $statusChanged
        ? "{$concern['ticket_id']} is now {$newStatusName}"
        : "New response on {$concern['ticket_id']}";
    notifyUser($pdo, 'student', (int)$concern['student_id'], $concernId,
        $statusChanged ? 'status_change' : 'response',
        $notifTitle,
        mb_substr($responseText, 0, 200));

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ICMS] Response failed: ' . $e->getMessage());
    setFlash('error', 'Could not save the response. Please try again.');
    header("Location: {$back}");
    exit;
}

// Email the student (queued after commit)
try {
    $stu = $pdo->prepare("SELECT name, email FROM students WHERE student_id = :id");
    $stu->execute(['id' => $concern['student_id']]);
    if ($student = $stu->fetch()) {
        $lines = ["Hi {$student['name']},"];
        $lines[] = $statusChanged
            ? "The status of your concern \"{$concern['title']}\" ({$concern['ticket_id']}) changed from {$oldStatusName} to {$newStatusName}."
            : "An administrator responded to your concern \"{$concern['title']}\" ({$concern['ticket_id']}).";
        $lines[] = "Response from {$user['name']}:\n{$responseText}";
        if ($departmentEndorsed !== '') {
            $lines[] = "Endorsed to: {$departmentEndorsed}";
        }
        if ($newStatusName === 'Resolved') {
            $lines[] = 'If you are satisfied, please rate the resolution. If the issue is not fixed, you can reopen the ticket within ' . REOPEN_WINDOW_DAYS . ' days.';
        }
        queueEmail($pdo, $student['email'], $student['name'], $notifTitle, $lines,
            appUrl("student/concern_details.php?id={$concernId}"), 'View your concern');
    }
} catch (Throwable $e) {
    error_log('[ICMS] Queue email failed: ' . $e->getMessage());
}

setFlash('success', "Response recorded and ticket status updated for {$concern['ticket_id']}.");
header("Location: {$back}");
exit;
