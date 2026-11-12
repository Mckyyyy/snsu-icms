<?php
// actions/concern_admin_action.php - Admin ticket management
//   classify : manual categorize / priority override (Use case: Auto/Manual Categorize Concerns)
//   archive  : archive a finished ticket (records are never deleted); restore brings it back
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$user = getCurrentUser();

$concernId = (int)($_POST['concern_id'] ?? 0);
$action    = $_POST['action'] ?? '';
$back      = (in_array($action, ['classify', 'assign'], true) && $concernId > 0) ? "../admin/concern_details.php?id={$concernId}" : '../admin/concerns.php';
requirePostWithCsrf($back);

$stmt = $pdo->prepare("
    SELECT c.*, cat.category_name FROM concerns c
    JOIN categories cat ON cat.category_id = c.category_id
    WHERE c.concern_id = :id
");
$stmt->execute(['id' => $concernId]);
$concern = $stmt->fetch();
if (!$concern) {
    setFlash('error', 'Concern ticket not found.');
    header('Location: ../admin/concerns.php');
    exit;
}

if ($action === 'assign') {
    $assigneeId = (int)($_POST['assigned_admin_id'] ?? 0);
    $assignee = null;
    if ($assigneeId > 0) {
        $a = $pdo->prepare("SELECT admin_id, name, email FROM administrators WHERE admin_id = :id AND status = 'Active'");
        $a->execute(['id' => $assigneeId]);
        $assignee = $a->fetch();
        if (!$assignee) {
            setFlash('error', 'That administrator is not available.');
            header("Location: {$back}");
            exit;
        }
    }
    if ((int)($concern['assigned_admin_id'] ?? 0) === $assigneeId) {
        setFlash('info', 'No change in assignment.');
        header("Location: {$back}");
        exit;
    }

    $old = null;
    if ($concern['assigned_admin_id']) {
        $o = $pdo->prepare("SELECT name FROM administrators WHERE admin_id = :id");
        $o->execute(['id' => $concern['assigned_admin_id']]);
        $old = $o->fetchColumn() ?: null;
    }

    $pdo->prepare("UPDATE concerns SET assigned_admin_id = :a WHERE concern_id = :id")
        ->execute(['a' => $assignee ? $assigneeId : null, 'id' => $concernId]);
    logConcernAudit($pdo, $concernId, 'admin', (int)$user['id'], 'assigned', $old, $assignee['name'] ?? 'Unassigned');
    logAdminActivity($pdo, (int)$user['id'], 'concern_assigned', 'concern', $concernId,
        "{$concern['ticket_id']} → " . ($assignee['name'] ?? 'Unassigned'));

    if ($assignee && $assigneeId !== (int)$user['id']) {
        notifyUser($pdo, 'admin', $assigneeId, $concernId, 'assigned',
            "Assigned to you: {$concern['ticket_id']}",
            "{$user['name']} assigned \"{$concern['title']}\" ({$concern['priority']}) to you.");
        queueEmail($pdo, $assignee['email'], $assignee['name'], "Ticket assigned to you: {$concern['ticket_id']}", [
            "Hi {$assignee['name']},",
            "{$user['name']} assigned the concern \"{$concern['title']}\" ({$concern['priority']} priority, {$concern['category_name']}) to you.",
            'SLA deadline: ' . date('M j, Y · g:i A', strtotime($concern['sla_due_at'])),
        ], appUrl("admin/concern_details.php?id={$concernId}"), 'Open ticket');
    }

    setFlash('success', $assignee ? "Ticket assigned to {$assignee['name']}." : 'Ticket is now unassigned.');
    header("Location: {$back}");
    exit;
}

if (in_array($action, ['archive', 'delete'], true)) {
    // Tickets are never deleted. Only finished tickets (Resolved / Withdrawn) can be archived,
    // so open work, SLA tracking and the dashboards are never affected.
    if (!in_array((int)$concern['status_id'], [3, 4], true)) {
        setFlash('warning', "Ticket {$concern['ticket_id']} is still open. Resolve it first (for spam or invalid reports, reply with the reason and mark it Resolved), then archive it.");
        header("Location: ../admin/concern_details.php?id={$concernId}");
        exit;
    }
    $pdo->prepare("UPDATE concerns SET archived_at = NOW(), archived_by = :a WHERE concern_id = :id")->execute(['a' => $user['id'], 'id' => $concernId]);
    logConcernAudit($pdo, $concernId, 'admin', (int)$user['id'], 'archived');
    logAdminActivity($pdo, (int)$user['id'], 'concern_archived', 'concern', $concernId, "{$concern['ticket_id']}: {$concern['title']}");
    setFlash('success', "Ticket {$concern['ticket_id']} archived. It stays in reports and the student's history; find it under Archived.");
    header('Location: ../admin/concerns.php');
    exit;
}

if ($action === 'restore') {
    $pdo->prepare("UPDATE concerns SET archived_at = NULL, archived_by = NULL WHERE concern_id = :id")->execute(['id' => $concernId]);
    logConcernAudit($pdo, $concernId, 'admin', (int)$user['id'], 'restored');
    logAdminActivity($pdo, (int)$user['id'], 'concern_restored', 'concern', $concernId, "{$concern['ticket_id']}: {$concern['title']}");
    setFlash('success', "Ticket {$concern['ticket_id']} restored.");
    header("Location: ../admin/concern_details.php?id={$concernId}");
    exit;
}

if ($action === 'classify') {
    $newCategoryId = (int)($_POST['category_id'] ?? 0);
    $newPriority   = $_POST['priority'] ?? '';

    if (!array_key_exists($newPriority, SLA_HOURS_BY_PRIORITY)) {
        setFlash('error', 'Invalid priority level.');
        header("Location: {$back}");
        exit;
    }
    $c = $pdo->prepare("SELECT category_name FROM categories WHERE category_id = :id");
    $c->execute(['id' => $newCategoryId]);
    $newCategoryName = $c->fetchColumn();
    if ($newCategoryName === false) {
        setFlash('error', 'Invalid category.');
        header("Location: {$back}");
        exit;
    }

    $changes = [];
    try {
        $pdo->beginTransaction();

        if ($newCategoryId !== (int)$concern['category_id']) {
            $pdo->prepare("UPDATE concerns SET category_id = :cid WHERE concern_id = :id")
                ->execute(['cid' => $newCategoryId, 'id' => $concernId]);
            logConcernAudit($pdo, $concernId, 'admin', (int)$user['id'], 'category_changed', $concern['category_name'], $newCategoryName);
            $changes[] = "category → {$newCategoryName}";
        }

        if ($newPriority !== $concern['priority']) {
            // SLA window is re-based on the original submission time so overrides can't hide a breach
            $slaHours = slaHoursForPriority($newPriority);
            $pdo->prepare("
                UPDATE concerns
                   SET priority = :p, sla_hours = :h, sla_due_at = :due, is_rrt_alert = :rrt, sla_alert_level = 'none'
                 WHERE concern_id = :id
            ")->execute([
                'p'   => $newPriority,
                'h'   => $slaHours,
                'due' => computeSlaDueAt($concern['date_submitted'], $slaHours),
                'rrt' => $newPriority === 'Critical' ? 1 : 0,
                'id'  => $concernId,
            ]);
            logConcernAudit($pdo, $concernId, 'admin', (int)$user['id'], 'priority_override', $concern['priority'], $newPriority);
            $changes[] = "priority → {$newPriority} ({$slaHours}h SLA)";
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[ICMS] Classify failed: ' . $e->getMessage());
        setFlash('error', 'Could not update classification.');
        header("Location: {$back}");
        exit;
    }

    if ($changes) {
        logAdminActivity($pdo, (int)$user['id'], 'concern_classified', 'concern', $concernId, "{$concern['ticket_id']}: " . implode(', ', $changes));
    }
    setFlash($changes ? 'success' : 'info', $changes ? 'Updated ' . implode(', ', $changes) . '.' : 'No changes made.');
    header("Location: {$back}");
    exit;
}

header('Location: ../admin/concerns.php');
exit;
