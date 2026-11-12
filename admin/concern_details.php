<?php
// admin/concern_details.php - Admin Concern Review & Response Center
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$concernId = (int)($_GET['id'] ?? 0);

if ($concernId <= 0) {
    header('Location: concerns.php');
    exit;
}

// Fetch concern with student details
$stmt = $pdo->prepare("
    SELECT c.*, " . anonStudentColumns() . ",
           cat.category_name, st.status_name, aa.name AS assigned_name
    FROM concerns c
    JOIN students s ON c.student_id = s.student_id
    LEFT JOIN administrators aa ON aa.admin_id = c.assigned_admin_id
    JOIN categories cat ON c.category_id = cat.category_id
    JOIN statuses st ON c.status_id = st.status_id
    WHERE c.concern_id = :id
");
$stmt->execute(['id' => $concernId]);
$concern = $stmt->fetch();

if (!$concern) {
    setFlash('error', 'Concern ticket not found.');
    header('Location: concerns.php');
    exit;
}

$activeAdmins = $pdo->query("SELECT admin_id, name, role FROM administrators WHERE status = 'Active' ORDER BY name")->fetchAll();
$me = getCurrentUser();

// Fetch statuses & categories
// Admins move tickets through Pending / In Progress / Resolved; only students withdraw
$statuses   = $pdo->query("SELECT * FROM statuses WHERE status_id IN (1, 2, 3) ORDER BY status_id ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

$aiReasons = json_decode((string)($concern['ai_reasons'] ?? ''), true) ?: [];
$suggestedCategoryName = null;
foreach ($categories as $cat) {
    if ((int)$cat['category_id'] === (int)($concern['ai_suggested_category_id'] ?? 0)) {
        $suggestedCategoryName = $cat['category_name'];
    }
}

// Audit trail (status/category/priority changes)
$auditStmt = $pdo->prepare("
    SELECT l.*, a.name AS admin_name, s.name AS student_name
    FROM concern_audit_logs l
    LEFT JOIN administrators a ON l.actor_type = 'admin' AND a.admin_id = l.actor_id
    LEFT JOIN students s ON l.actor_type = 'student' AND s.student_id = l.actor_id
    WHERE l.concern_id = :id
    ORDER BY l.created_at ASC, l.log_id ASC
");
$auditStmt->execute(['id' => $concernId]);
$auditLogs = $auditStmt->fetchAll();
if ((int)$concern['is_anonymous'] === 1) {
    foreach ($auditLogs as &$log) {
        if ($log['actor_type'] === 'student') {
            $log['student_name'] = ANONYMOUS_LABEL;
        }
    }
    unset($log);
}

$ratingStmt = $pdo->prepare("SELECT rating, message, submitted_at FROM feedback WHERE concern_id = :id");
$ratingStmt->execute(['id' => $concernId]);
$studentRating = $ratingStmt->fetch();

// Fetch timeline responses
$respStmt = $pdo->prepare("
    SELECT r.*, a.name AS admin_name, s.status_name
    FROM responses r
    JOIN administrators a ON r.admin_id = a.admin_id
    JOIN statuses s ON r.status_id = s.status_id
    WHERE r.concern_id = :id
    ORDER BY r.date_responded ASC
");
$respStmt->execute(['id' => $concernId]);
$responses = $respStmt->fetchAll();

// Show student reopen events on the same timeline as admin responses
$reopenStmt = $pdo->prepare("
    SELECT l.created_at, l.action, l.new_value AS reason, s.name AS student_name
    FROM concern_audit_logs l
    LEFT JOIN students s ON s.student_id = l.actor_id
    WHERE l.concern_id = :id AND l.action IN ('reopened', 'withdrawn')
");
$reopenStmt->execute(['id' => $concernId]);
foreach ($reopenStmt->fetchAll() as $ro) {
    $responses[] = [
        'admin_name'          => ((int)$concern['is_anonymous'] === 1 ? ANONYMOUS_LABEL : ($ro['student_name'] ?? 'Student')) . ' (Student)',
        'department_endorsed' => null,
        'date_responded'      => $ro['created_at'],
        'status_name'         => $ro['action'] === 'withdrawn' ? 'Withdrawn' : 'Pending',
        'response_text'       => ($ro['action'] === 'withdrawn' ? 'Withdrew the concern: ' : 'Reopened the concern: ') . $ro['reason'],
        'is_reopen'           => true,
    ];
}
usort($responses, fn($a, $b) => strcmp($a['date_responded'], $b['date_responded']));

// Possible duplicates: open or recently resolved concerns describing the same problem
$duplicates = findSimilarConcerns($pdo, $concern['title'], $concern['description'], (int)$concern['category_id'],
    (int)$concern['concern_id'], 4, [1, 2, 3]);


$pageTitle = "Review Ticket {$concern['ticket_id']} | Admin ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php
$sla = getSlaState($concern);
$departments = [
    'Facilities & Maintenance'                           => 'Facilities',
    'College of Computing & Information Sciences (CCIS)' => null,
    'Registrar / Academic Affairs'                       => 'Academic',
    'MIS / IT Services'                                  => 'Services',
    'Guidance & Student Welfare'                         => 'Student Welfare',
];
$templates = [
    ['wrench', 'Endorse to maintenance', 'We have received and acknowledged your report. This concern has been officially endorsed to the maintenance team for inspection.', 2],
    ['search', 'Investigation ongoing', 'Investigation is currently ongoing with the department head. We will update you with the resolution within 48 hours.', 2],
    ['info', 'Need more details', 'Thank you for your report. Could you please provide more details (exact location, time, and photos if possible) so we can act on this quickly?', 1],
    ['check-circle', 'Mark as resolved', 'This issue has been thoroughly resolved and inspected on-site. Thank you for bringing this to our attention!', 3],
];
?>

<div class="flex justify-between items-center gap-2" style="flex-wrap: wrap;">
  <a href="concerns.php<?= $concern['archived_at'] ? '?archived=1' : '' ?>" class="back-link"><?= icon('arrow-left', '', 16) ?> Back to <?= $concern['archived_at'] ? 'Archived Concerns' : 'Manage Concerns' ?></a>
  <?php if ($concern['archived_at'] || in_array((int)$concern['status_id'], [3, 4], true)): $isArchived = (bool)$concern['archived_at']; ?>
    <form action="../actions/concern_admin_action.php" method="POST" style="margin: 0 0 12px;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="<?= $isArchived ? 'restore' : 'archive' ?>">
      <input type="hidden" name="concern_id" value="<?= (int)$concern['concern_id'] ?>">
      <button type="button" class="btn btn-secondary btn-sm"
        data-confirm="<?= $isArchived ? 'Restore this ticket to the concern queue?' : 'Archive this ticket? It leaves the queue but is kept for the record, still counts in reports, and can be restored.' ?>"
        data-confirm-title="<?= $isArchived ? 'Restore Ticket' : 'Archive Ticket' ?>" data-confirm-text="<?= $isArchived ? 'Restore' : 'Archive' ?>" data-confirm-type="<?= $isArchived ? 'info' : 'warning' ?>">
        <?= icon($isArchived ? 'refresh' : 'archive', '', 15) ?> <?= $isArchived ? 'Restore ticket' : 'Archive ticket' ?>
      </button>
    </form>
  <?php endif; ?>
</div>

<?php if ($concern['archived_at']): ?>
  <div class="alert alert-warning" style="display: flex; gap: 8px; align-items: center;">
    <?= icon('archive', '', 16) ?>
    <span>This ticket was archived on <?= e(formatDateTime($concern['archived_at'])) ?>. It is kept for the record and still counts in reports.</span>
  </div>
<?php endif; ?>

<!-- Ticket header -->
<div class="card">
  <div class="card-body" style="padding: 22px 24px;">
    <div class="flex items-center gap-2" style="flex-wrap: wrap; margin-bottom: 8px;">
      <span class="ticket-badge"><?= e($concern['ticket_id']) ?></span>
      <?= renderStatusBadge($concern['status_name']) ?>
      <?= renderPriorityBadge($concern['priority'], (int)$concern['ai_urgency_score']) ?>
      <?php if ((int)$concern['status_id'] !== 4): ?><?= renderSlaBadge($concern, false) ?><?php endif; ?>
      <?php if ($concern['is_rrt_alert'] && in_array((int)$concern['status_id'], [1, 2], true)): ?><span class="badge-tag badge-red badge-dot">RRT alert</span><?php endif; ?>
      <?php if ((int)$concern['reopen_count'] > 0): ?>
        <span class="badge-tag badge-amber" title="Last reopened <?= e(formatDateTime($concern['last_reopened_at'])) ?>">Reopened &times;<?= (int)$concern['reopen_count'] ?></span>
      <?php endif; ?>
      <?php if ((int)$concern['edit_count'] > 0): ?>
        <span class="badge-tag badge-gray" title="Last edited <?= e(formatDateTime($concern['edited_at'])) ?>">Edited by student<?= (int)$concern['edit_count'] > 1 ? ' &times;' . (int)$concern['edit_count'] : '' ?></span>
      <?php endif; ?>
    </div>
    <h1 style="font-size: 1.35rem; font-weight: 800; letter-spacing: -0.02em;"><?= e($concern['title']) ?></h1>
    <p class="text-sm text-muted" style="margin-top: 4px;">
      <?= e($concern['category_name']) ?> · Submitted <?= formatDateTime($concern['date_submitted']) ?> by <?= e($concern['student_name']) ?><?php if ((int)$concern['is_anonymous'] === 1): ?> <span class="badge-tag badge-gray"><?= icon('eye-off', '', 12) ?> Anonymous</span><?php endif; ?>
    </p>
    <div style="margin-top: 22px;"><?= renderStatusTracker($concern['status_name']) ?></div>
  </div>
</div>

<div class="grid-main-side">
  <!-- ===================== Main column ===================== -->
  <div>
    <div class="card">
      <div class="card-header"><h2 class="card-title">Concern</h2></div>
      <div class="card-body">
        <div class="concern-description-block">
          <div class="concern-description-title">Description</div>
          <div class="concern-description-text"><?= nl2br(e($concern['description'])) ?></div>
        </div>
        <?= renderAttachment(getConcernAttachmentMeta($pdo, (int)$concern['concern_id'])) ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Activity &amp; Responses</h2>
        <span class="badge-tag"><?= count($responses) ?></span>
      </div>
      <div class="card-body">
        <?= renderResponseTimeline($responses, 'No response yet. Use the form below to acknowledge the student and update the status.') ?>
      </div>
    </div>

    <?php if ((int)$concern['status_id'] === 4): ?>
    <div class="card" id="responseBox">
      <div class="card-body flex gap-3">
        <div class="metric-icon-wrap" style="width: 40px; height: 40px; background: var(--bg-muted); color: var(--text-secondary);"><?= icon('x', '', 18) ?></div>
        <div>
          <div class="font-bold">Withdrawn by the student on <?= formatDateTime($concern['withdrawn_at']) ?></div>
          <div class="text-sm text-secondary" style="margin-top: 2px;">Reason: <?= e($concern['withdraw_reason'] ?? '—') ?></div>
          <div class="text-xs text-muted" style="margin-top: 6px;">No further action is needed. The ticket is kept for the record and excluded from SLA and resolution-rate figures.</div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <!-- Official response -->
    <form id="responseBox" action="../actions/response_action.php" method="POST" class="card" style="border-color: var(--brand-blue-border); scroll-margin-top: 80px;">
      <?= csrfField() ?>
      <input type="hidden" name="concern_id" value="<?= (int)$concern['concern_id'] ?>">
      <div class="card-header" style="background: var(--brand-blue-light);">
        <div>
          <h2 class="card-title" style="color: var(--brand-blue-hover);"><?= icon('edit', '', 18) ?> Respond &amp; Update Status</h2>
          <div class="card-subtitle">The student is notified in the app and by email.</div>
        </div>
      </div>
      <div class="card-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="respStatus">New status <span class="required">*</span></label>
            <select name="status_id" id="respStatus" class="form-control" required>
              <?php foreach ($statuses as $st): ?>
                <option value="<?= (int)$st['status_id'] ?>" <?= ((int)$concern['status_id'] === (int)$st['status_id']) ? 'selected' : '' ?>><?= e($st['status_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="respDept">Endorse to <span class="optional">(optional)</span></label>
            <select name="department_endorsed" id="respDept" class="form-control">
              <option value="">No endorsement — handle directly</option>
              <?php foreach ($departments as $dept => $forCategory): ?>
                <option value="<?= e($dept) ?>" <?= $forCategory === $concern['category_name'] ? 'selected' : '' ?>><?= e($dept) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <div class="flex justify-between items-center gap-2" style="flex-wrap: wrap; margin-bottom: 6px;">
            <label class="form-label" for="responseText" style="margin: 0;">Message to student <span class="required">*</span></label>
            <span class="text-xs text-muted">Quick templates</span>
          </div>
          <div class="flex gap-2" style="flex-wrap: wrap; margin-bottom: 8px;">
            <?php foreach ($templates as [$ic, $label, $text, $statusId]): ?>
              <button type="button" class="btn btn-secondary btn-sm" data-template="<?= e($text) ?>" data-status="<?= $statusId ?>"><?= icon($ic, '', 14) ?> <?= e($label) ?></button>
            <?php endforeach; ?>
          </div>
          <textarea name="response_text" id="responseText" class="form-control" rows="5" required placeholder="Describe the action taken, who is handling it, and what the student can expect next..."></textarea>
        </div>
      </div>
      <div class="form-actions">
        <span class="form-actions-note"><?= icon('info', '', 14) ?> Visible on the student's tracking timeline immediately.</span>
        <button type="submit" class="btn btn-primary"><?= icon('check', '', 16) ?> Publish Response</button>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <!-- ===================== Side column ===================== -->
  <div>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title"><?= icon('user', '', 16) ?> Assignment</h2>
        <?php if (!$concern['assigned_admin_id']): ?><span class="badge-tag badge-amber">Unassigned</span><?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($concern['assigned_name']): ?>
          <div class="person" style="margin-bottom: 14px;">
            <div class="avatar avatar-admin"><?= e(userInitials($concern['assigned_name'])) ?></div>
            <div>
              <div class="person-name"><?= e($concern['assigned_name']) ?><?= (int)$concern['assigned_admin_id'] === (int)$me['id'] ? ' <span class="badge-tag badge-blue">You</span>' : '' ?></div>
              <div class="person-sub">Owns this ticket and receives its SLA alerts</div>
            </div>
          </div>
        <?php else: ?>
          <p class="text-sm text-muted" style="margin-bottom: 12px;">Nobody owns this ticket yet, so SLA alerts go to every administrator. The first admin to respond is assigned automatically.</p>
        <?php endif; ?>
        <form action="../actions/concern_admin_action.php" method="POST" class="flex gap-2" style="flex-wrap: wrap;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="assign">
          <input type="hidden" name="concern_id" value="<?= (int)$concern['concern_id'] ?>">
          <select name="assigned_admin_id" class="form-control" style="flex: 1 1 160px;" aria-label="Assign to">
            <option value="0">Unassigned</option>
            <?php foreach ($activeAdmins as $adm): ?>
              <option value="<?= (int)$adm['admin_id'] ?>" <?= (int)$adm['admin_id'] === (int)$concern['assigned_admin_id'] ? 'selected' : '' ?>><?= e($adm['name']) ?> — <?= e($adm['role']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary">Assign</button>
        </form>
        <?php if ((int)$concern['assigned_admin_id'] !== (int)$me['id']): ?>
          <form action="../actions/concern_admin_action.php" method="POST" style="margin-top: 8px;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="concern_id" value="<?= (int)$concern['concern_id'] ?>">
            <input type="hidden" name="assigned_admin_id" value="<?= (int)$me['id'] ?>">
            <button type="submit" class="btn btn-ghost btn-sm"><?= icon('check', '', 14) ?> Assign to me</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2 class="card-title">Student</h2></div>
      <div class="card-body">
        <?php if ((int)$concern['is_anonymous'] === 1): ?>
          <div class="person" style="margin-bottom: 10px;">
            <?= senderAvatar($concern, 'avatar-lg', 'width: 44px; height: 44px;') ?>
            <div style="min-width: 0;">
              <div class="person-name"><?= ANONYMOUS_LABEL ?></div>
              <div class="person-sub">Submitted anonymously</div>
            </div>
          </div>
          <p class="text-sm text-secondary" style="margin: 0;">The student chose to hide their identity. They still receive your responses and status updates through the system, so reply here as usual.</p>
        <?php else: ?>
        <div class="person" style="margin-bottom: 14px;">
          <div class="avatar avatar-lg" style="width: 44px; height: 44px; font-size: 0.95rem;"><?= e(userInitials($concern['student_name'])) ?></div>
          <div style="min-width: 0;">
            <div class="person-name"><?= e($concern['student_name']) ?></div>
            <div class="person-sub"><?= e($concern['student_no']) ?></div>
          </div>
        </div>
        <div class="kv-list">
          <div class="kv-row"><span class="kv-key">Email</span><span class="kv-val truncate" style="max-width: 190px;"><a href="mailto:<?= e($concern['student_email']) ?>"><?= e($concern['student_email']) ?></a></span></div>
          <div class="kv-row"><span class="kv-key">Program</span><span class="kv-val"><?= e($concern['program']) ?></span></div>
          <div class="kv-row"><span class="kv-key">College</span><span class="kv-val"><?= e($concern['student_dept']) ?></span></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2 class="card-title">Service Level (SLA)</h2></div>
      <div class="card-body">
        <div class="kv-list">
          <div class="kv-row"><span class="kv-key">State</span><span class="kv-val"><?= renderSlaBadge($concern, false) ?></span></div>
          <div class="kv-row"><span class="kv-key">Target</span><span class="kv-val"><?= (int)$concern['sla_hours'] ?> hours</span></div>
          <div class="kv-row"><span class="kv-key">Due</span><span class="kv-val"><?= formatDateTime($concern['sla_due_at']) ?></span></div>
          <div class="kv-row"><span class="kv-key"><?= in_array($sla['state'], ['met', 'missed'], true) ? 'Result' : 'Time' ?></span><span class="kv-val"><?= e($sla['detail']) ?></span></div>
          <div class="kv-row"><span class="kv-key">First response</span><span class="kv-val"><?= $concern['first_response_at'] ? formatDateTime($concern['first_response_at']) : '—' ?></span></div>
          <?php if ($concern['resolved_at']): ?>
            <div class="kv-row"><span class="kv-key">Resolved</span><span class="kv-val"><?= formatDateTime($concern['resolved_at']) ?></span></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($duplicates): ?>
      <div class="card" style="border-color: #fde3a7;">
        <div class="card-header">
          <h2 class="card-title"><?= icon('copy', '', 16) ?> Possible Duplicates</h2>
          <span class="text-xs text-muted"><?= count($duplicates) ?> found</span>
        </div>
        <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">
          <?php foreach ($duplicates as $d): ?>
            <a href="concern_details.php?id=<?= (int)$d['concern_id'] ?>" class="dup-link">
              <span class="dup-link-top">
                <span class="mono text-xs font-bold"><?= e($d['ticket_id']) ?></span>
                <span class="duplicate-pct"><?= (int)$d['similarity'] ?>% match</span>
              </span>
              <span class="dup-link-title"><?= e($d['title']) ?></span>
              <span class="dup-link-meta"><?= renderStatusBadge($d['status_name']) ?> <span class="text-xs text-muted"><?= e($d['category_name']) ?> · <?= e(timeAgo($d['date_submitted'])) ?></span></span>
              <span class="text-xs text-muted">Shared words: <?= e(implode(', ', $d['shared_keywords'])) ?></span>
            </a>
          <?php endforeach; ?>
          <p class="text-xs text-muted" style="margin: 0;">Compared by keywords in the title and description. Check before acting; similar is not always the same problem.</p>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title"><?= icon('zap', '', 16) ?> AI Analysis</h2>
          <div class="card-subtitle"><?= ($concern['ai_engine'] ?? '') === 'gemini' ? 'Assessed by Google Gemini (language model)' : (($concern['ai_engine'] ?? '') === 'rules' ? 'Assessed by the rule-based engine (AI model unavailable)' : 'Assessed by the rule-based engine') ?></div>
        </div>
        <span class="mono text-sm font-bold" title="Urgency score: how urgent the AI judged this concern (not a count of reports)"><?= (int)$concern['ai_urgency_score'] ?>/100</span>
      </div>
      <div class="card-body">
        <div class="ai-score-bar-track" style="margin-bottom: 12px;">
          <div class="ai-score-bar-fill" style="width: <?= (int)$concern['ai_urgency_score'] ?>%; background: <?= ['Critical' => '#dc2626', 'High' => '#ea580c', 'Medium' => '#d97706', 'Low' => '#10b981'][$concern['priority']] ?? '#2563eb' ?>;"></div>
        </div>
        <?php if (!empty($concern['ai_fix_hours'])): ?>
          <div class="kv-list" style="margin-bottom: 10px;">
            <div class="kv-row"><span class="kv-key">AI fix estimate</span><span class="kv-val">~<?= e(formatDurationLong((int)$concern['ai_fix_hours'] * 3600)) ?> · by <?= e(date('M j, g:i A', strtotime($concern['date_submitted']) + (int)$concern['ai_fix_hours'] * 3600)) ?></span></div>
            <?php if ($concern['ai_fix_note']): ?><div class="kv-note"><?= e($concern['ai_fix_note']) ?></div><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($aiReasons): ?>
          <ul class="text-sm text-secondary" style="margin: 0 0 12px 18px; display: flex; flex-direction: column; gap: 4px;">
            <?php foreach ($aiReasons as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($suggestedCategoryName && $suggestedCategoryName !== $concern['category_name']): ?>
          <div class="alert alert-info" style="padding: 8px 12px; margin-bottom: 14px;">
            <span class="alert-text text-sm">AI suggests <strong><?= e($suggestedCategoryName) ?></strong> (student chose <?= e($concern['category_name']) ?>).</span>
          </div>
        <?php endif; ?>

        <form action="../actions/concern_admin_action.php" method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="classify">
          <input type="hidden" name="concern_id" value="<?= (int)$concern['concern_id'] ?>">
          <div class="form-group" style="margin-bottom: 12px;">
            <label class="form-label" for="clsCategory">Category</label>
            <select name="category_id" id="clsCategory" class="form-control">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['category_id'] ?>" <?= ((int)$cat['category_id'] === (int)$concern['category_id']) ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom: 12px;">
            <label class="form-label" for="clsPriority">Priority</label>
            <select name="priority" id="clsPriority" class="form-control">
              <?php foreach (SLA_HOURS_BY_PRIORITY as $p => $h): ?>
                <option value="<?= $p ?>" <?= $concern['priority'] === $p ? 'selected' : '' ?>><?= $p ?> — <?= $h ?>h SLA</option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-secondary btn-block">Save classification</button>
        </form>
      </div>
    </div>

    <?php if ($studentRating): ?>
      <div class="card">
        <div class="card-header"><h2 class="card-title"><?= icon('star', '', 16) ?> Student Satisfaction Rating</h2></div>
        <div class="card-body">
          <div class="flex items-center gap-2"><?= renderStarRating((int)$studentRating['rating']) ?> <strong><?= (int)$studentRating['rating'] ?>/5</strong></div>
          <?php if ($studentRating['message'] !== '(No comment)'): ?>
            <p class="text-sm text-secondary" style="margin-top: 8px;">“<?= nl2br(e($studentRating['message'])) ?>”</p>
          <?php endif; ?>
          <div class="text-xs text-muted" style="margin-top: 6px;"><?= formatDateTime($studentRating['submitted_at']) ?></div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($auditLogs): ?>
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Audit Log</h2>
          <span class="badge-tag"><?= count($auditLogs) ?></span>
        </div>
        <div style="max-height: 320px; overflow-y: auto;">
          <?php foreach (array_reverse($auditLogs) as $log): ?>
            <div class="list-item" style="padding: 10px 16px;">
              <div class="list-item-body">
                <div class="text-sm"><strong><?= e($log['admin_name'] ?? $log['student_name'] ?? ucfirst($log['actor_type'])) ?></strong> <?= e(str_replace('_', ' ', $log['action'])) ?></div>
                <?php if ($log['old_value'] !== null || $log['new_value'] !== null): ?>
                  <div class="text-xs text-secondary truncate" title="<?= e(($log['old_value'] ?? '') . ' → ' . ($log['new_value'] ?? '')) ?>">
                    <?= $log['old_value'] !== null ? e($log['old_value']) . ' → ' : '' ?><?= e($log['new_value'] ?? '—') ?>
                  </div>
                <?php endif; ?>
                <div class="list-item-meta" style="margin-top: 2px;"><?= formatDateTime($log['created_at']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  // Quick templates fill the message and suggest the matching status
  document.querySelectorAll('[data-template]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.getElementById('responseText').value = btn.dataset.template;
      document.getElementById('respStatus').value = btn.dataset.status;
      document.getElementById('responseText').focus();
    });
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
