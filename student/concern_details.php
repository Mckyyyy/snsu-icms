<?php
// student/concern_details.php - Concern Details & Admin Response Timeline (Figure 6 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();
$studentId = $user['id'];
$concernId = (int)($_GET['id'] ?? 0);

if ($concernId <= 0) {
    header('Location: my_concerns.php');
    exit;
}

// Fetch concern details (guaranteeing it belongs to this student)
$stmt = $pdo->prepare("
    SELECT c.*, cat.category_name, s.status_name 
    FROM concerns c
    JOIN categories cat ON c.category_id = cat.category_id
    JOIN statuses s ON c.status_id = s.status_id
    WHERE c.concern_id = :id AND c.student_id = :student_id
");
$stmt->execute(['id' => $concernId, 'student_id' => $studentId]);
$concern = $stmt->fetch();

if (!$concern) {
    setFlash('error', 'Concern not found or you do not have permission to view it.');
    header('Location: my_concerns.php');
    exit;
}

// Fetch all administrative responses/updates for this ticket
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
        'admin_name'          => ($ro['student_name'] ?? 'Student') . ' (Student)',
        'department_endorsed' => null,
        'date_responded'      => $ro['created_at'],
        'status_name'         => $ro['action'] === 'withdrawn' ? 'Withdrawn' : 'Pending',
        'response_text'       => ($ro['action'] === 'withdrawn' ? 'Withdrew the concern: ' : 'Reopened the concern: ') . $ro['reason'],
        'is_reopen'           => true,
    ];
}
usort($responses, fn($a, $b) => strcmp($a['date_responded'], $b['date_responded']));


// Resolution satisfaction rating (one per resolved concern)
$ratingStmt = $pdo->prepare("SELECT rating, message, submitted_at FROM feedback WHERE concern_id = :id");
$ratingStmt->execute(['id' => $concernId]);
$existingRating = $ratingStmt->fetch();

$pageTitle = "Ticket {$concern['ticket_id']} Details | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php $sla = getSlaState($concern); ?>

<a href="my_concerns.php" class="back-link"><?= icon('arrow-left', '', 16) ?> Back to My Concerns</a>

<!-- Ticket header -->
<div class="card">
  <div class="card-body" style="padding: 22px 24px;">
    <div class="flex items-center gap-2" style="flex-wrap: wrap; margin-bottom: 8px;">
      <span class="ticket-badge"><?= e($concern['ticket_id']) ?></span>
      <?= renderStatusBadge($concern['status_name']) ?>
      <?php if ((int)$concern['reopen_count'] > 0): ?>
        <span class="badge-tag badge-amber">Reopened &times;<?= (int)$concern['reopen_count'] ?></span>
      <?php endif; ?>
      <?php if ((int)$concern['is_anonymous'] === 1): ?>
        <span class="badge-tag badge-gray" title="Administrators cannot see your name on this concern"><?= icon('eye-off', '', 12) ?> Anonymous</span>
      <?php endif; ?>
      <?php if ((int)$concern['edit_count'] > 0): ?>
        <span class="badge-tag badge-gray" title="Last edited <?= e(formatDateTime($concern['edited_at'])) ?>">Edited<?= (int)$concern['edit_count'] > 1 ? ' &times;' . (int)$concern['edit_count'] : '' ?></span>
      <?php endif; ?>
      <?php if (canEditConcern($concern)): ?>
        <a href="submit_concern.php?id=<?= (int)$concern['concern_id'] ?>" class="btn btn-secondary btn-sm" style="margin-left: auto;"><?= icon('edit', '', 14) ?> Edit concern</a>
      <?php endif; ?>
    </div>
    <h1 style="font-size: 1.35rem; font-weight: 800; letter-spacing: -0.02em;"><?= e($concern['title']) ?></h1>
    <p class="text-sm text-muted" style="margin-top: 4px;"><?= e($concern['category_name']) ?> · Submitted <?= formatDateTime($concern['date_submitted']) ?></p>
    <div style="margin-top: 22px;"><?= renderStatusTracker($concern['status_name']) ?></div>
  </div>
</div>

<div class="grid-main-side">
  <div>
    <div class="card">
      <div class="card-header"><h2 class="card-title">Concern Details</h2></div>
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
        <h2 class="card-title">Updates / Admin Response</h2>
        <span class="badge-tag"><?= count($responses) ?></span>
      </div>
      <div class="card-body">
        <?= renderResponseTimeline($responses, 'Your concern is waiting for review. Updates from the administrator will appear here, and you will also get a notification.') ?>
      </div>
    </div>

    <?php if ((int)$concern['status_id'] === 4): ?>
      <div class="card">
        <div class="card-body flex gap-3">
          <div class="metric-icon-wrap" style="width: 40px; height: 40px; background: var(--bg-muted); color: var(--text-secondary);"><?= icon('x', '', 18) ?></div>
          <div>
            <div class="font-bold">You withdrew this concern on <?= formatDateTime($concern['withdrawn_at']) ?></div>
            <div class="text-sm text-secondary" style="margin-top: 2px;">Reason: <?= e($concern['withdraw_reason'] ?? '—') ?></div>
            <div class="text-xs text-muted" style="margin-top: 6px;">It stays in your history. If the problem comes back, please <a href="submit_concern.php">submit a new concern</a>.</div>
          </div>
        </div>
      </div>
    <?php elseif ((int)$concern['status_id'] === 1): ?>
      <!-- Withdraw (only while still Pending) -->
      <div class="card">
        <div class="card-body">
          <details>
            <summary class="text-sm font-semibold" style="cursor: pointer; color: var(--text-secondary);">No longer need help with this? Withdraw this concern</summary>
            <p class="text-xs text-muted" style="margin: 8px 0 12px;">
              You can withdraw a concern while it is still <strong>Pending</strong>. The administrators will be told, and the ticket stays in your history marked as Withdrawn.
            </p>
            <form action="../actions/withdraw_action.php" method="POST" id="withdrawForm">
              <?= csrfField() ?>
              <input type="hidden" name="concern_id" value="<?= (int)$concern['concern_id'] ?>">
              <div class="form-row-2">
                <div class="form-group">
                  <label class="form-label" for="withdrawReason">Reason <span class="required">*</span></label>
                  <select name="reason" id="withdrawReason" class="form-control" required>
                    <option value="" disabled selected>Choose a reason</option>
                    <option value="resolved_itself">The issue has already been fixed</option>
                    <option value="mistake">Submitted by mistake</option>
                    <option value="duplicate">Duplicate of another concern</option>
                    <option value="other">Other reason</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" for="withdrawDetails">Details <span class="optional">(required for "Other")</span></label>
                  <input type="text" name="details" id="withdrawDetails" class="form-control" maxlength="200" placeholder="e.g. Maintenance fixed it this morning">
                </div>
              </div>
              <button type="button" class="btn btn-danger-soft"
                data-confirm="Withdraw ticket &lt;strong&gt;<?= e($concern['ticket_id']) ?>&lt;/strong&gt;? The administrators will stop working on it."
                data-confirm-title="Withdraw Concern" data-confirm-text="Withdraw" data-confirm-type="danger"><?= icon('x', '', 16) ?> Withdraw Concern</button>
            </form>
          </details>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($concern['status_name'] === 'Resolved'): ?>
      <!-- Resolution Satisfaction Rating -->
      <div class="card">
        <div class="card-header">
          <div>
            <h2 class="card-title"><?= icon('star', '', 18) ?> Rate the Resolution</h2>
            <div class="card-subtitle">How satisfied are you with how this concern was handled?</div>
          </div>
        </div>
        <div class="card-body">
          <?php if ($existingRating): ?>
            <div class="flex items-center gap-3" style="flex-wrap: wrap;">
              <?= renderStarRating((int)$existingRating['rating']) ?>
              <strong><?= (int)$existingRating['rating'] ?>/5</strong>
              <span class="text-sm text-muted">Rated on <?= formatDateTime($existingRating['submitted_at']) ?></span>
            </div>
            <?php if ($existingRating['message'] !== '(No comment)'): ?>
              <p class="text-sm text-secondary" style="margin-top: 8px;">“<?= nl2br(e($existingRating['message'])) ?>”</p>
            <?php endif; ?>
          <?php else: ?>
            <form action="../actions/feedback_action.php" method="POST">
              <?= csrfField() ?>
              <input type="hidden" name="concern_id" value="<?= (int)$concern['concern_id'] ?>">
              <input type="hidden" name="rating" id="ratingValue" value="5">
              <div class="star-rating" title="Click a star to rate">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="star selected" data-value="<?= $i ?>"><?= icon('star') ?></span>
                <?php endfor; ?>
              </div>
              <div class="form-group" style="margin-top: 14px;">
                <label class="form-label" for="ratingComment">Comment <span class="optional">(optional)</span></label>
                <textarea name="message" id="ratingComment" class="form-control" rows="3" maxlength="2000" placeholder="What went well? What could be improved?"></textarea>
              </div>
              <button type="submit" class="btn btn-primary"><?= icon('check', '', 16) ?> Submit Rating</button>
            </form>
          <?php endif; ?>

          <?php if (canReopenConcern($concern)): ?>
            <details style="margin-top: 20px; border-top: 1px dashed var(--border-strong); padding-top: 14px;">
              <summary class="text-sm font-semibold" style="cursor: pointer; color: #b45309;">Not actually fixed? Reopen this concern</summary>
              <p class="text-xs text-muted" style="margin: 8px 0 12px;">
                You can reopen within <?= REOPEN_WINDOW_DAYS ?> days of resolution (up to <?= REOPEN_MAX ?> times).
                The ticket returns to Pending with a new SLA deadline<?= $existingRating ? ', and your rating will be cleared so you can rate again later' : '' ?>.
              </p>
              <form action="../actions/reopen_action.php" method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="concern_id" value="<?= (int)$concern['concern_id'] ?>">
                <div class="form-group">
                  <label class="form-label" for="reopenReason">What is still wrong? <span class="required">*</span></label>
                  <textarea name="reason" id="reopenReason" class="form-control" rows="3" minlength="10" maxlength="1000" required placeholder="e.g. The projector was replaced but the HDMI port still does not work."></textarea>
                </div>
                <button type="button" class="btn btn-secondary"
                  data-confirm="Reopen ticket &lt;strong&gt;<?= e($concern['ticket_id']) ?>&lt;/strong&gt; and send it back to the administrators?"
                  data-confirm-title="Reopen Concern" data-confirm-text="Reopen" data-confirm-type="warning"><?= icon('refresh', '', 16) ?> Reopen Concern</button>
              </form>
            </details>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <div class="card-header"><h2 class="card-title">Ticket Summary</h2></div>
      <div class="card-body">
        <div class="kv-list">
          <div class="kv-row"><span class="kv-key">Ticket ID</span><span class="kv-val mono"><?= e($concern['ticket_id']) ?></span></div>
          <div class="kv-row"><span class="kv-key">Category</span><span class="kv-val"><?= e($concern['category_name']) ?></span></div>
          <div class="kv-row"><span class="kv-key">Status</span><span class="kv-val"><?= renderStatusBadge($concern['status_name']) ?></span></div>
          <div class="kv-row"><span class="kv-key">Priority</span><span class="kv-val"><?= renderPriorityBadge($concern['priority'], (int)$concern['ai_urgency_score']) ?></span></div>
          <div class="kv-row"><span class="kv-key">Submitted</span><span class="kv-val"><?= formatDateTime($concern['date_submitted']) ?></span></div>
        </div>
      </div>
    </div>

    <?php
      $slaDays    = (int)$concern['sla_hours'] % 24 === 0 ? intdiv((int)$concern['sla_hours'], 24) : null;
      $typical    = typicalResolutionTime($pdo, $concern['priority'], (int)$concern['category_id'], (int)$concern['concern_id']);
      $isResolved = $sla['state'] === 'met' || $sla['state'] === 'missed';
      // Gemini's estimate of the real fix time (never later than the SLA deadline)
      $fixHours   = !empty($concern['ai_fix_hours']) ? (int)$concern['ai_fix_hours'] : 0;
      $fixAt      = $fixHours ? strtotime($concern['date_submitted']) + $fixHours * 3600 : null;
    ?>
    <div class="card">
      <div class="card-header"><h2 class="card-title"><?= icon('clock', '', 16) ?> When Will It Be Fixed?</h2></div>
      <div class="card-body">
        <div class="text-center" style="padding: 4px 0 14px;">
          <?php if ($isResolved && !empty($concern['resolved_at'])): ?>
            <div class="text-xs text-muted">Fixed in</div>
            <div class="metric-number" style="font-size: 1.6rem;"><?= e(formatDurationLong(strtotime($concern['resolved_at']) - strtotime($concern['date_submitted']))) ?></div>
            <div class="text-xs text-muted">Target was <?= e(slaTargetText((int)$concern['sla_hours'])) ?></div>
          <?php else: ?>
            <div class="text-xs text-muted">Expected to be fixed</div>
            <?php if ($fixHours): ?>
              <div class="metric-number" style="font-size: 2rem;">~<?= e(formatDurationLong($fixHours * 3600)) ?></div>
              <div class="text-xs text-muted">Estimated by Google Gemini AI<?= $concern['ai_fix_note'] ? ': ' . e($concern['ai_fix_note']) : '' ?></div>
            <?php else: ?>
              <div class="metric-number" style="font-size: 2rem;"><?= $slaDays !== null ? $slaDays . ' day' . ($slaDays === 1 ? '' : 's') : (int)$concern['sla_hours'] . ' hours' ?></div>
              <div class="text-xs text-muted">Target set from your concern's <?= e(strtolower($concern['priority'])) ?> priority</div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <div class="kv-list">
          <?php if ($fixAt && !$isResolved): ?>
            <div class="kv-row"><span class="kv-key">Expected by (AI)</span><span class="kv-val"><?= e(date('D, M j · g:i A', $fixAt)) ?></span></div>
          <?php endif; ?>
          <div class="kv-row"><span class="kv-key"><?= $isResolved ? 'Target date' : ($fixAt ? 'Official deadline' : 'Fixed by') ?></span><span class="kv-val"><?= e(date('D, M j · g:i A', strtotime($concern['sla_due_at']))) ?></span></div>
          <div class="kv-row"><span class="kv-key">Status</span><span class="kv-val"><?= renderSlaBadge($concern, false) ?></span></div>
          <?php if ($sla['state'] === 'on_track' || $sla['state'] === 'at_risk'): ?>
            <div class="kv-row"><span class="kv-key">Time left</span><span class="kv-val"><?= e(formatDurationLong($sla['remaining'])) ?></span></div>
          <?php elseif ($sla['state'] === 'breached'): ?>
            <div class="kv-row"><span class="kv-key">Overdue by</span><span class="kv-val" style="color: var(--danger);"><?= e(formatDurationLong($sla['remaining'])) ?></span></div>
          <?php else: ?>
            <div class="kv-row"><span class="kv-key">Result</span><span class="kv-val"><?= e($sla['detail']) ?></span></div>
          <?php endif; ?>
          <?php if ($typical && !$isResolved): ?>
            <div class="kv-row"><span class="kv-key">Similar concerns</span><span class="kv-val">usually ~<?= e(formatDurationLong((int)round($typical['hours'] * 3600))) ?></span></div>
            <div class="kv-note">Average of <?= (int)$typical['count'] ?> resolved <?= e(strtolower($concern['priority'])) ?>-priority concern<?= $typical['count'] === 1 ? '' : 's' ?><?= $typical['basis'] === 'category' ? ' in ' . e($concern['category_name']) : '' ?>.</div>
          <?php endif; ?>
        </div>
        <?php if ($sla['state'] === 'breached'): ?>
          <p class="text-xs text-secondary" style="margin: 10px 0 0;">This is past its target. The administrators have been alerted automatically.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body text-sm text-secondary" style="display: flex; gap: 10px;">
        <span style="color: var(--brand-blue);"><?= icon('info', '', 18) ?></span>
        <span>You will receive a notification and an email whenever an administrator responds or the status changes.</span>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const form = document.getElementById('withdrawForm');
    if (!form) return;
    const btn = form.querySelector('[data-confirm]');
    btn.addEventListener('click', (e) => {
      const reason = document.getElementById('withdrawReason').value;
      const details = document.getElementById('withdrawDetails').value.trim();
      let msg = '';
      if (!reason) msg = 'Please choose a reason first.';
      else if (reason === 'other' && details.length < 5) msg = 'Please describe the reason (at least 5 characters).';
      if (msg) {
        e.stopImmediatePropagation();
        e.preventDefault();
        showToast(msg, 'error', 'Withdraw concern');
      }
    }, true);
  })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
