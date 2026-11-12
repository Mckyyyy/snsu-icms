<?php
// actions/edit_concern_action.php - Student edits their own concern
//
// Allowed only while the concern is Pending and no administrator has responded
// (canEditConcern). The AI engine re-scores the new text; the SLA deadline is still
// measured from the ORIGINAL submission time so editing can never extend it. A
// priority that an administrator set by hand is kept.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();

$concernId = (int)($_POST['concern_id'] ?? 0);
$back      = "../student/submit_concern.php?id={$concernId}";
$details   = "../student/concern_details.php?id={$concernId}";
requirePostWithCsrf($back);

$stmt = $pdo->prepare("
    SELECT c.*, cat.category_name FROM concerns c
    JOIN categories cat ON cat.category_id = c.category_id
    WHERE c.concern_id = :id AND c.student_id = :sid
");
$stmt->execute(['id' => $concernId, 'sid' => $user['id']]);
$concern = $stmt->fetch();
if (!$concern) {
    setFlash('error', 'Concern not found.');
    header('Location: ../student/my_concerns.php');
    exit;
}
if (!canEditConcern($concern)) {
    setFlash('warning', 'This concern can no longer be edited because an administrator has already started on it.');
    header("Location: {$details}");
    exit;
}

// ---- Validate input ----
$categoryId  = (int)($_POST['category_id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
if ($title === '' || $description === '' || $categoryId <= 0) {
    setFlash('error', 'Please fill in the Category, Title and Description.');
    header("Location: {$back}");
    exit;
}
if (mb_strlen($title) > 255) {
    setFlash('error', 'The title must be 255 characters or fewer.');
    header("Location: {$back}");
    exit;
}
$catStmt = $pdo->prepare("SELECT category_id, category_name FROM categories WHERE category_id = :id AND (is_active = 1 OR category_id = :cur)");
$catStmt->execute(['id' => $categoryId, 'cur' => $concern['category_id']]);
$category = $catStmt->fetch();
if (!$category) {
    setFlash('error', 'Selected category does not exist.');
    header("Location: {$back}");
    exit;
}

// ---- Attachment: replace, remove or keep ----
$newAttachment    = validateConcernAttachment($_FILES['attachment'] ?? null, $back);
$oldAttachment    = getConcernAttachmentMeta($pdo, $concernId);
$removeAttachment = !$newAttachment && !empty($_POST['remove_attachment']) && $oldAttachment;
$isAnonymous      = !empty($_POST['is_anonymous']) ? 1 : 0;

// ---- What changed? ----
$changes = [];
if ($title !== $concern['title'])                    { $changes[] = 'title'; }
if ($description !== $concern['description'])        { $changes[] = 'description'; }
if ($categoryId !== (int)$concern['category_id'])    { $changes[] = 'category'; }
if ($newAttachment)                                  { $changes[] = $oldAttachment ? 'attachment replaced' : 'attachment added'; }
if ($removeAttachment)                               { $changes[] = 'attachment removed'; }
if ($isAnonymous !== (int)$concern['is_anonymous'])  { $changes[] = $isAnonymous ? 'made anonymous' : 'name shown'; }

if (!$changes) {
    setFlash('info', 'No changes were made.');
    header("Location: {$details}");
    exit;
}

// ---- Re-run the AI engine ----
$ai = prioritizeConcern($title, $description, $category['category_name'], activeCategoryNames($pdo));
$suggestedId = null;
if ($ai['suggested_category'] !== null) {
    $s = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = :n AND is_active = 1");
    $s->execute(['n' => $ai['suggested_category']]);
    $suggestedId = $s->fetchColumn() ?: null;
}

// Keep a priority an administrator set by hand
$ov = $pdo->prepare("SELECT COUNT(*) FROM concern_audit_logs WHERE concern_id = :id AND action = 'priority_override'");
$ov->execute(['id' => $concernId]);
$adminOverride = (int)$ov->fetchColumn() > 0;

$priority = $adminOverride ? $concern['priority'] : $ai['priority'];
$slaHours = $adminOverride ? (int)$concern['sla_hours'] : $ai['sla_hours'];
$isRrt    = $priority === 'Critical' ? 1 : 0;
$dueAt    = computeSlaDueAt($concern['date_submitted'], $slaHours); // from the ORIGINAL submission
$becameCritical = $isRrt === 1 && (int)$concern['is_rrt_alert'] === 0;

try {
    $pdo->beginTransaction();

    $up = $pdo->prepare("
        UPDATE concerns SET
            category_id = :cat, is_anonymous = :anon, title = :title, description = :descr,
            priority = :p, ai_urgency_score = :score, ai_reasons = :reasons, ai_engine = :engine, ai_fix_hours = :fixh, ai_fix_note = :fixn, ai_suggested_category_id = :sugg,
            sla_hours = :h, sla_due_at = :due, is_rrt_alert = :rrt, sla_alert_level = 'none',
            edited_at = NOW(), edit_count = edit_count + 1
        WHERE concern_id = :id AND status_id = 1 AND first_response_at IS NULL
    ");
    $up->execute([
        'cat' => $categoryId, 'anon' => $isAnonymous, 'title' => $title, 'descr' => $description,
        'p' => $priority, 'score' => $ai['score'], 'reasons' => json_encode($ai['reasons']), 'engine' => $ai['engine'], 'fixh' => $adminOverride ? null : $ai['fix_hours'], 'fixn' => $adminOverride ? null : $ai['fix_note'], 'sugg' => $suggestedId,
        'h' => $slaHours, 'due' => $dueAt, 'rrt' => $isRrt, 'id' => $concernId,
    ]);
    if ($up->rowCount() === 0) {
        throw new RuntimeException('Concern changed before the edit was saved.');
    }
    if ($newAttachment) {
        saveConcernAttachment($pdo, $concernId, $newAttachment);   // replaces the old file
    } elseif ($removeAttachment) {
        removeConcernAttachment($pdo, $concernId);
    }

    logConcernAudit($pdo, $concernId, 'student', (int)$user['id'], 'edited', null, 'Changed ' . implode(', ', $changes));
    if (in_array('category', $changes, true)) {
        logConcernAudit($pdo, $concernId, 'student', (int)$user['id'], 'category_changed', $concern['category_name'], $category['category_name']);
    }
    if ($priority !== $concern['priority']) {
        logConcernAudit($pdo, $concernId, 'system', null, 'ai_prioritized', "{$concern['priority']}, SLA {$concern['sla_hours']}h", "{$priority} ({$ai['score']}/100), SLA {$slaHours}h");
    }

    // Keep the owner (or everyone, if it became an emergency) informed
    if ($becameCritical) {
        notifyAllAdmins($pdo, $concernId, 'rrt', "RRT Critical Alert: {$concern['ticket_id']}",
            "{$title} was updated and is now Critical ({$ai['score']}/100).");
    } elseif ($concern['assigned_admin_id']) {
        notifyUser($pdo, 'admin', (int)$concern['assigned_admin_id'], $concernId, 'edited',
            "Updated by student: {$concern['ticket_id']}", concernSenderName(['is_anonymous' => $isAnonymous], $user['name']) . ' changed the ' . implode(', ', $changes) . '.');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ICMS] Edit failed: ' . $e->getMessage());
    setFlash('error', 'Your changes could not be saved because the concern was just updated by an administrator.');
    header("Location: {$details}");
    exit;
}

if ($becameCritical) {
    try {
        queueEmailToAdmins($pdo, "[RRT CRITICAL] {$concern['ticket_id']}: {$title}", [
            'A student updated a concern and the AI engine now rates it CRITICAL.',
            "Ticket: {$concern['ticket_id']}\nUrgency score: {$ai['score']}/100\nSLA deadline: " . date('M j, Y · g:i A', strtotime($dueAt)),
            'Reason: ' . implode('; ', $ai['reasons']),
        ], appUrl("admin/concern_details.php?id={$concernId}"), 'Respond now');
    } catch (Throwable $e) {
        error_log('[ICMS] Queue email failed: ' . $e->getMessage());
    }
}

$priorityNote = $priority !== $concern['priority'] ? " Priority is now {$priority} ({$slaHours}h target)." : '';
setFlash('success', "Your changes to {$concern['ticket_id']} were saved.{$priorityNote}");
header("Location: {$details}");
exit;
