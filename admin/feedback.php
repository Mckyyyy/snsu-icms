<?php
// admin/feedback.php - Admin Feedback Management (Figure 13 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

// Calculate feedback statistics
$stats = $pdo->query("
    SELECT
        COUNT(*) as total_feedback,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as stars_5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as stars_4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as stars_3,
        SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as stars_low,
        SUM(CASE WHEN feedback_type = 'Resolution Rating' THEN 1 ELSE 0 END) as resolution_ratings,
        SUM(CASE WHEN (admin_reply IS NULL OR admin_reply = '') AND student_id IS NOT NULL THEN 1 ELSE 0 END) as awaiting
    FROM feedback
")->fetch();

$allFeedback = $pdo->query("
    SELECT f.*,
           IF(ac.is_anonymous = 1, '" . ANONYMOUS_LABEL . "', f.student_name) AS student_name,
           IF(ac.is_anonymous = 1, NULL, f.student_email) AS student_email,
           IF(ac.is_anonymous = 1, NULL, s.student_no) AS student_no,
           COALESCE(ac.is_anonymous, 0) AS is_anonymous,
           ac.ticket_id,
           ra.name AS replied_by_name
    FROM feedback f
    LEFT JOIN students s ON f.student_id = s.student_id
    LEFT JOIN concerns ac ON ac.concern_id = f.concern_id
    LEFT JOIN administrators ra ON ra.admin_id = f.replied_by
    ORDER BY f.submitted_at DESC
")->fetchAll();

$typeFilter = in_array($_GET['type'] ?? '', ['Resolution Rating', 'general', 'awaiting'], true) ? $_GET['type'] : '';
$shown = array_filter($allFeedback, function ($f) use ($typeFilter) {
    if ($typeFilter === 'Resolution Rating') return $f['feedback_type'] === 'Resolution Rating';
    if ($typeFilter === 'general') return $f['feedback_type'] !== 'Resolution Rating';
    if ($typeFilter === 'awaiting') return empty($f['admin_reply']) && $f['student_id'];
    return true;
});

$totalFb = (int)$stats['total_feedback'];
$dist = [5 => (int)$stats['stars_5'], 4 => (int)$stats['stars_4'], 3 => (int)$stats['stars_3'], 2 => (int)$stats['stars_low']];

$pageTitle = "Feedback Management | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
  .fb-item { padding: 18px 20px; border-bottom: 1px solid var(--border-subtle); scroll-margin-top: 80px; }
  .fb-item:last-child { border-bottom: 0; }
  .fb-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  .fb-who { display: flex; align-items: center; gap: 10px; min-width: 0; }
  .fb-who-name { font-weight: 600; color: var(--text-primary); font-size: 0.9rem; }
  .fb-who-sub { font-size: 0.76rem; color: var(--text-muted); }
  .fb-rating { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
  .fb-subject { font-weight: 700; color: var(--text-primary); margin: 12px 0 4px; }
  .fb-message { font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; white-space: pre-line; }
  .fb-tags { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-top: 10px; font-size: 0.78rem; }
  .fb-reply-toggle { margin-top: 12px; }
  .fb-reply-form { margin-top: 10px; padding: 12px; background: var(--bg-subtle); border: 1px solid var(--border-subtle); border-radius: 10px; }
  .fb-reply-form[hidden] { display: none; }
  .fb-dist-row { display: grid; grid-template-columns: 44px 1fr 40px; align-items: center; gap: 10px; }
  @media (max-width: 640px) { .fb-item { padding: 16px; } .fb-rating { align-items: flex-start; } }
</style>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Voice of the students</div>
    <h1 class="page-title">Feedback</h1>
    <p class="page-desc">General feedback and resolution ratings submitted by students. Reply to let them know what will be done.</p>
  </div>
</div>

<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-icon-wrap metric-icon-warning"><?= icon('star-filled', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Average rating</span>
      <span class="metric-number"><?= $totalFb ? number_format((float)$stats['avg_rating'], 1) : '—' ?><span class="text-sm text-muted" style="font-weight: 600;"> / 5</span></span>
      <span class="metric-sub"><?= renderStarRating((int)round((float)$stats['avg_rating'])) ?></span>
    </div>
  </div>
  <a href="feedback.php" class="metric-card">
    <div class="metric-icon-wrap metric-icon-total"><?= icon('message-square', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Total responses</span><span class="metric-number"><?= $totalFb ?></span><span class="metric-sub">All feedback</span></div>
  </a>
  <a href="?type=Resolution+Rating" class="metric-card">
    <div class="metric-icon-wrap metric-icon-success"><?= icon('check-circle', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Resolution ratings</span><span class="metric-number"><?= (int)$stats['resolution_ratings'] ?></span><span class="metric-sub">Rated a resolved ticket</span></div>
  </a>
  <a href="?type=awaiting" class="metric-card">
    <div class="metric-icon-wrap metric-icon-danger"><?= icon('clock', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Awaiting reply</span><span class="metric-number"><?= (int)$stats['awaiting'] ?></span><span class="metric-sub">No reply yet</span></div>
  </a>
</div>

<div class="grid-main-side">
  <div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 10px;">
      <h2 class="card-title">All Feedback</h2>
      <div class="segmented" aria-label="Show">
        <a href="feedback.php" class="<?= $typeFilter === '' ? 'active' : '' ?>">All</a>
        <a href="?type=awaiting" class="<?= $typeFilter === 'awaiting' ? 'active' : '' ?>">Awaiting reply</a>
        <a href="?type=Resolution+Rating" class="<?= $typeFilter === 'Resolution Rating' ? 'active' : '' ?>">Resolution ratings</a>
        <a href="?type=general" class="<?= $typeFilter === 'general' ? 'active' : '' ?>">General</a>
      </div>
    </div>

    <?php if (!$shown): ?>
      <div class="empty-state">
        <div class="empty-state-icon"><?= icon('message-square', '', 22) ?></div>
        <div class="empty-state-title"><?= $typeFilter === 'awaiting' ? 'Every feedback has a reply' : 'No feedback here yet' ?></div>
      </div>
    <?php endif; ?>

    <?php foreach ($shown as $fb): $fid = (int)$fb['feedback_id']; $hasReply = !empty($fb['admin_reply']); ?>
      <div class="fb-item" id="feedback-<?= $fid ?>">
        <div class="fb-head">
          <div class="fb-who">
            <?= senderAvatar($fb) ?>
            <div style="min-width: 0;">
              <div class="fb-who-name"><?= e($fb['student_name']) ?></div>
              <div class="fb-who-sub"><?= !empty($fb['student_no']) ? e($fb['student_no']) . ' · ' : '' ?><?= formatDateTime($fb['submitted_at']) ?></div>
            </div>
          </div>
          <div class="fb-rating">
            <?= renderStarRating((int)$fb['rating']) ?>
            <span class="badge-tag <?= $fb['feedback_type'] === 'Resolution Rating' ? 'badge-amber' : '' ?>"><?= e($fb['feedback_type']) ?></span>
          </div>
        </div>

        <div class="fb-subject"><?= e($fb['subject']) ?></div>
        <div class="fb-message"><?= e($fb['message']) ?></div>

        <div class="fb-tags">
          <?php if (!empty($fb['concern_id'])): ?>
            <a href="concern_details.php?id=<?= (int)$fb['concern_id'] ?>" class="badge-tag badge-blue"><?= icon('file-text', '', 12) ?> <?= e($fb['ticket_id'] ?? 'View ticket') ?></a>
          <?php endif; ?>
          <?php if (!$hasReply && $fb['student_id']): ?><span class="badge-tag badge-amber badge-dot">Awaiting reply</span><?php endif; ?>
          <?php if ($hasReply): ?><span class="badge-tag badge-green badge-dot">Replied</span><?php endif; ?>
        </div>

        <?php if ($hasReply): ?>
          <div class="feedback-reply">
            <div class="feedback-reply-head"><?= icon('message-square', '', 14) ?> Reply by <?= e($fb['replied_by_name'] ?? 'Administrator') ?> · <?= formatDateTime($fb['replied_at']) ?></div>
            <div class="text-sm" style="white-space: pre-line;"><?= e($fb['admin_reply']) ?></div>
          </div>
        <?php endif; ?>

        <?php if ($fb['student_id']): ?>
          <div class="fb-reply-toggle">
            <button type="button" class="btn <?= $hasReply ? 'btn-secondary' : 'btn-primary' ?> btn-sm"
              onclick="const f = document.getElementById('replyForm<?= $fid ?>'); f.hidden = !f.hidden; if (!f.hidden) f.querySelector('textarea').focus();">
              <?= icon($hasReply ? 'edit' : 'message-square', '', 14) ?> <?= $hasReply ? 'Edit reply' : 'Reply to student' ?>
            </button>
          </div>
          <form action="../actions/feedback_reply_action.php" method="POST" class="fb-reply-form" id="replyForm<?= $fid ?>" hidden>
            <?= csrfField() ?>
            <input type="hidden" name="feedback_id" value="<?= $fid ?>">
            <input type="hidden" name="return_type" value="<?= e($typeFilter) ?>">
            <label class="form-label" for="replyText<?= $fid ?>">Your reply</label>
            <textarea name="admin_reply" id="replyText<?= $fid ?>" class="form-control" rows="3" maxlength="2000" required placeholder="Thank the student and say what will be done..."><?= e($fb['admin_reply'] ?? '') ?></textarea>
            <div class="flex justify-between items-center gap-2" style="margin-top: 10px; flex-wrap: wrap;">
              <span class="text-xs text-muted">The student is notified in the app and by email.</span>
              <div class="flex gap-2">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('replyForm<?= $fid ?>').hidden = true;">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', '', 14) ?> <?= $hasReply ? 'Update reply' : 'Send reply' ?></button>
              </div>
            </div>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card" style="position: sticky; top: 84px; align-self: start;">
    <div class="card-header"><h2 class="card-title">Rating Breakdown</h2></div>
    <div class="card-body" style="display: flex; flex-direction: column; gap: 12px;">
      <?php foreach ($dist as $stars => $n): $pct = $totalFb ? round($n / $totalFb * 100) : 0; ?>
        <div class="fb-dist-row">
          <span class="text-sm font-semibold"><?= $stars === 2 ? '≤ 2' : $stars ?> <?= icon('star-filled', '', 12) ?></span>
          <div class="hbar-track"><div class="hbar-fill" style="width: <?= $pct ?>%;"></div></div>
          <span class="text-sm text-right text-muted"><?= $n ?></span>
        </div>
      <?php endforeach; ?>
      <p class="text-xs text-muted" style="margin: 4px 0 0;">Bars show each rating's share of all <?= $totalFb ?> response<?= $totalFb === 1 ? '' : 's' ?>.</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
