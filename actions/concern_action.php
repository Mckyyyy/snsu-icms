<?php
// actions/concern_action.php - Student concern submission (Use case: Submit Concern)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();
requirePostWithCsrf('../student/submit_concern.php');

$action = $_POST['action'] ?? 'create';

if ($action !== 'create') {
    header('Location: ../student/my_concerns.php');
    exit;
}

$categoryId  = (int)($_POST['category_id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($title === '' || $description === '' || $categoryId <= 0) {
    setFlash('error', 'Please fill in the Category, Concern Title, and Description.');
    header('Location: ../student/submit_concern.php');
    exit;
}
if (mb_strlen($title) > 255) {
    setFlash('error', 'Concern title must be 255 characters or fewer.');
    header('Location: ../student/submit_concern.php');
    exit;
}

$catStmt = $pdo->prepare("SELECT category_id, category_name FROM categories WHERE category_id = :id AND is_active = 1");
$catStmt->execute(['id' => $categoryId]);
$category = $catStmt->fetch();
if (!$category) {
    setFlash('error', 'Selected category does not exist.');
    header('Location: ../student/submit_concern.php');
    exit;
}

// ---- Attachment (optional): validated now, saved to the database with the concern below ----
$attachment = validateConcernAttachment($_FILES['attachment'] ?? null, '../student/submit_concern.php');

// ---- AI Dynamic Prioritization (server-side, authoritative) ---------------
// Google Gemini when configured; the rule-based engine when the AI is unavailable
$ai = prioritizeConcern($title, $description, $category['category_name'], activeCategoryNames($pdo));

$suggestedCategoryId = null;
if ($ai['suggested_category'] !== null) {
    $s = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = :n AND is_active = 1");
    $s->execute(['n' => $ai['suggested_category']]);
    $suggestedCategoryId = $s->fetchColumn() ?: null;
}

$isAnonymous = !empty($_POST['is_anonymous']) ? 1 : 0;
$now      = date('Y-m-d H:i:s');
$slaDueAt = computeSlaDueAt($now, $ai['sla_hours']);

try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare("
        INSERT INTO concerns (
            ticket_id, student_id, is_anonymous, category_id, status_id, title, description,
            priority, ai_urgency_score, ai_reasons, ai_engine, ai_fix_hours, ai_fix_note, ai_suggested_category_id,
            sla_hours, sla_due_at, is_rrt_alert, date_submitted
        ) VALUES (
            :ticket_id, :student_id, :anon, :category_id, 1, :title, :description,
            :priority, :score, :reasons, :engine, :fix_hours, :fix_note, :suggested,
            :sla_hours, :sla_due_at, :is_rrt, :now
        )
    ");
    $insert->execute([
        'ticket_id'       => 'TMP-' . bin2hex(random_bytes(8)),
        'student_id'      => $user['id'],
        'anon'            => $isAnonymous,
        'category_id'     => $categoryId,
        'title'           => $title,
        'description'     => $description,
        'priority'        => $ai['priority'],
        'score'           => $ai['score'],
        'reasons'         => json_encode($ai['reasons']),
        'engine'          => $ai['engine'],
        'fix_hours'       => $ai['fix_hours'],
        'fix_note'        => $ai['fix_note'],
        'suggested'       => $suggestedCategoryId,
        'sla_hours'       => $ai['sla_hours'],
        'sla_due_at'      => $slaDueAt,
        'is_rrt'          => $ai['is_rrt_alert'],
        'now'             => $now,
    ]);

    $concernId = (int)$pdo->lastInsertId();
    $ticketId  = formatTicketId($concernId);
    $pdo->prepare("UPDATE concerns SET ticket_id = :t WHERE concern_id = :id")->execute(['t' => $ticketId, 'id' => $concernId]);
    if ($attachment) {
        saveConcernAttachment($pdo, $concernId, $attachment);
    }

    logConcernAudit($pdo, $concernId, 'student', (int)$user['id'], 'created', null, $isAnonymous ? 'Pending (anonymous)' : 'Pending');
    logConcernAudit($pdo, $concernId, 'system', null, 'ai_prioritized', null, "{$ai['priority']} ({$ai['score']}/100), SLA {$ai['sla_hours']}h · " . ($ai['engine'] === 'gemini' ? 'Gemini AI' : 'rule-based engine'));

    $adminTitle = $ai['is_rrt_alert'] ? "RRT Critical Alert: {$ticketId}" : "New concern: {$ticketId}";
    $adminMsg   = "{$title} — {$category['category_name']}, {$ai['priority']} priority, due " . date('M j · g:i A', strtotime($slaDueAt)) . '.';
    notifyAllAdmins($pdo, $concernId, $ai['is_rrt_alert'] ? 'rrt' : 'new_concern', $adminTitle, $adminMsg);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[ICMS] Concern submit failed: ' . $e->getMessage());
    setFlash('error', 'Something went wrong while submitting your concern. Please try again.');
    header('Location: ../student/submit_concern.php');
    exit;
}

// ---- Email notifications (queued; never block the submission) -------------
try {
    $dueText = date('M j, Y · g:i A', strtotime($slaDueAt));
    queueEmail($pdo, $user['email'], $user['name'], "Concern received: {$ticketId}", [
        "Hi {$user['name']},",
        "We received your concern \"{$title}\" ({$category['category_name']}).",
        "Ticket ID: {$ticketId}\nPriority: {$ai['priority']} (urgency score {$ai['score']}/100)\nExpected to be fixed: " . slaTargetText((int)$ai['sla_hours']) . " (by {$dueText})",
        'You will receive an email whenever an administrator responds or the status changes.',
    ], appUrl("student/concern_details.php?id={$concernId}"), 'Track your concern');

    if ($ai['is_rrt_alert']) {
        queueEmailToAdmins($pdo, "[RRT CRITICAL] {$ticketId}: {$title}", [
            'The AI Dynamic Prioritization Engine flagged a CRITICAL concern that requires immediate Rapid Response Team action.',
            "Ticket: {$ticketId}\nCategory: {$category['category_name']}\nUrgency score: {$ai['score']}/100\nSubmitted by: " . ($isAnonymous ? 'Anonymous student' : $user['name']) . "\nSLA deadline: {$dueText}",
            'Reason: ' . implode('; ', $ai['reasons']),
        ], appUrl("admin/concern_details.php?id={$concernId}"), 'Respond now');
    }
} catch (Throwable $e) {
    error_log('[ICMS] Queue email failed: ' . $e->getMessage());
}

$rrtMessage = $ai['is_rrt_alert']
    ? " [RRT Alert] The AI Urgency Engine marked this as CRITICAL (Score: {$ai['score']}/100) and alerted the Rapid Response Team."
    : '';
setFlash('success', "Concern submitted successfully! Ticket ID: {$ticketId}. Expected to be fixed " . slaTargetText((int)$ai['sla_hours']) . " (by " . date('M j, g:i A', strtotime($slaDueAt)) . ").{$rrtMessage}");
header("Location: ../student/concern_details.php?id={$concernId}");
exit;
