<?php
// student/dashboard.php - Student Dashboard (Figure 2 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();
$studentId = $user['id'];

$metricsStmt = $pdo->prepare("
    SELECT COUNT(*) AS total,
           SUM(c.status_id = 1) AS pending,
           SUM(c.status_id = 2) AS in_progress,
           SUM(c.status_id = 3) AS resolved
    FROM concerns c
    WHERE c.student_id = :student_id
");
$metricsStmt->execute(['student_id' => $studentId]);
$metrics = $metricsStmt->fetch();

$recentStmt = $pdo->prepare("
    SELECT c.*, cat.category_name, s.status_name
    FROM concerns c
    JOIN categories cat ON c.category_id = cat.category_id
    JOIN statuses s ON c.status_id = s.status_id
    WHERE c.student_id = :student_id
    ORDER BY c.date_submitted DESC, c.concern_id DESC
    LIMIT 5
");
$recentStmt->execute(['student_id' => $studentId]);
$recentConcerns = $recentStmt->fetchAll();

// Resolved concerns still waiting for a satisfaction rating
$toRateStmt = $pdo->prepare("
    SELECT c.concern_id, c.ticket_id, c.title FROM concerns c
    LEFT JOIN feedback f ON f.concern_id = c.concern_id
    WHERE c.student_id = :sid AND c.status_id = 3 AND f.feedback_id IS NULL
    ORDER BY c.resolved_at DESC LIMIT 3
");
$toRateStmt->execute(['sid' => $studentId]);
$toRate = $toRateStmt->fetchAll();

$announcements = $pdo->query("
    SELECT * FROM announcements
    WHERE archived_at IS NULL
    ORDER BY is_urgent DESC, published_at DESC
    LIMIT 3
")->fetchAll();

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $user['name'])[0];

$pageTitle = "Student Dashboard | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Welcome hero -->
<div class="card" style="border: 0; background: linear-gradient(115deg, var(--navy-900) 0%, var(--navy-700) 60%, #2563eb 130%); color: #fff; position: relative;">
  <div class="card-body" style="padding: 28px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
    <div style="max-width: 620px;">
      <div class="text-xs font-semibold" style="color: #93c5fd; letter-spacing: .08em; text-transform: uppercase;"><?= e($user['student_no']) ?> · <?= e($user['program']) ?></div>
      <h1 style="color: #fff; font-size: 1.6rem; font-weight: 800; margin-top: 6px;"><?= $greeting ?>, <?= e($firstName) ?>!</h1>
      <p style="color: #c7d7f5; margin-top: 6px; font-size: 0.92rem;">
        Have an issue on campus? Report it here — our AI engine prioritizes it instantly and you can follow every update in real time.
      </p>
    </div>
    <a href="submit_concern.php" class="btn btn-lg" style="background: #fff; color: var(--navy-900);"><?= icon('plus', '', 18) ?> Submit a Concern</a>
  </div>
</div>

<div class="metrics-grid">
  <a href="my_concerns.php" class="metric-card">
    <div class="metric-icon-wrap metric-icon-total"><?= icon('clipboard', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Total Concerns</span>
      <span class="metric-number"><?= (int)$metrics['total'] ?></span>
    </div>
  </a>
  <a href="my_concerns.php?status=1" class="metric-card">
    <div class="metric-icon-wrap metric-icon-pending"><?= icon('clock', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Pending</span>
      <span class="metric-number"><?= (int)$metrics['pending'] ?></span>
    </div>
  </a>
  <a href="my_concerns.php?status=2" class="metric-card">
    <div class="metric-icon-wrap metric-icon-progress"><?= icon('refresh', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">In Progress</span>
      <span class="metric-number"><?= (int)$metrics['in_progress'] ?></span>
    </div>
  </a>
  <a href="my_concerns.php?status=3" class="metric-card">
    <div class="metric-icon-wrap metric-icon-resolved"><?= icon('check-circle', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Resolved</span>
      <span class="metric-number"><?= (int)$metrics['resolved'] ?></span>
    </div>
  </a>
</div>

<?php
  $rc = $pdo->prepare("SELECT recovery_code_hash IS NOT NULL FROM students WHERE student_id = :id");
  $rc->execute(['id' => $user['id']]);
?>
<?php if (!(int)$rc->fetchColumn()): ?>
  <div class="alert alert-info">
    <span class="alert-icon"><?= icon('shield', '', 18) ?></span>
    <div class="alert-text">
      <strong>Set up your recovery code.</strong>
      If you ever forget your password, it lets you reset it yourself, with no email or administrator needed.
      <a href="profile.php#recovery" class="font-semibold" style="color: inherit; text-decoration: underline;">Create it on your Profile</a>
    </div>
  </div>
<?php endif; ?>

<?php if ($toRate): ?>
  <div class="alert alert-warning">
    <span class="alert-icon"><?= icon('star', '', 18) ?></span>
    <div class="alert-text">
      <strong>Rate your resolved concerns.</strong>
      Your feedback helps improve campus services:
      <?php foreach ($toRate as $i => $r): ?>
        <a href="concern_details.php?id=<?= (int)$r['concern_id'] ?>" class="font-semibold" style="color: inherit; text-decoration: underline;"><?= e($r['ticket_id']) ?></a><?= $i < count($toRate) - 1 ? ',' : '' ?>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="grid-main-side">
  <div>
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">Recent Concerns</h2>
          <div class="card-subtitle">Your latest submissions and their status</div>
        </div>
        <a href="my_concerns.php" class="card-link">View all <?= icon('arrow-right', '', 14) ?></a>
      </div>
      <div class="table-responsive">
        <table class="table table-stack">
          <thead>
            <tr>
              <th>Concern</th>
              <th>Priority / SLA</th>
              <th>Status</th>
              <th class="cell-actions"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentConcerns)): ?>
              <tr><td colspan="4">
                <div class="empty-state">
                  <div class="empty-state-icon"><?= icon('clipboard', '', 22) ?></div>
                  <div class="empty-state-title">No concerns yet</div>
                  <p>When you report an issue, you can track its progress here.</p>
                  <a href="submit_concern.php" class="btn btn-primary btn-sm"><?= icon('plus', '', 14) ?> Submit your first concern</a>
                </div>
              </td></tr>
            <?php endif; ?>
            <?php foreach ($recentConcerns as $c): ?>
              <tr data-href="concern_details.php?id=<?= (int)$c['concern_id'] ?>">
                <td class="cell-title">
                  <div style="margin-bottom: 3px;"><span class="ticket-badge"><?= e($c['ticket_id']) ?></span></div>
                  <a href="concern_details.php?id=<?= (int)$c['concern_id'] ?>"><?= e($c['title']) ?></a>
                  <span class="cell-sub"><?= e($c['category_name']) ?> · <?= formatDate($c['date_submitted']) ?></span>
                </td>
                <td>
                  <div class="pill-stack">
                    <?= renderPriorityBadge($c['priority'], (int)$c['ai_urgency_score']) ?>
                    <?= renderSlaBadge($c, false) ?>
                  </div>
                </td>
                <td><?= renderStatusBadge($c['status_name']) ?></td>
                <td class="cell-actions">
                  <a href="concern_details.php?id=<?= (int)$c['concern_id'] ?>" class="btn-icon" title="View details"><?= icon('eye', '', 16) ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title"><?= icon('megaphone', '', 18) ?> Announcements</h2>
        <a href="announcements.php" class="card-link">All <?= icon('arrow-right', '', 14) ?></a>
      </div>
      <?php if (!$announcements): ?>
        <div class="empty-state" style="padding: 28px 16px;"><p>No announcements right now.</p></div>
      <?php endif; ?>
      <?php foreach ($announcements as $anc): ?>
        <div class="list-item">
          <div class="list-item-icon <?= $anc['is_urgent'] ? 'urgent' : '' ?>"><?= icon($anc['is_urgent'] ? 'alert-triangle' : 'megaphone', '', 18) ?></div>
          <div class="list-item-body">
            <div class="list-item-title">
              <?= e($anc['title']) ?>
              <?php if ($anc['is_urgent']): ?><span class="badge-tag badge-red">Urgent</span><?php endif; ?>
            </div>
            <div class="list-item-text" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= e($anc['content']) ?></div>
            <div class="list-item-meta"><span><?= formatDate($anc['published_at']) ?></span></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div class="card-header"><h2 class="card-title">How it works</h2></div>
      <div class="card-body" style="display: flex; flex-direction: column; gap: 14px;">
        <?php foreach ([
            ['edit', 'Submit', 'Describe the issue and attach a photo if you have one.'],
            ['zap', 'AI prioritizes', 'Urgency and a resolution target (SLA) are set instantly.'],
            ['refresh', 'Track', 'Follow status updates and admin responses here and by email.'],
            ['star', 'Rate', 'Rate the resolution, or reopen it within 7 days if unresolved.'],
        ] as $i => [$ic, $t, $d]): ?>
          <div class="flex gap-3">
            <div class="form-section-num"><?= $i + 1 ?></div>
            <div>
              <div class="font-semibold text-sm"><?= $t ?></div>
              <div class="text-xs text-muted"><?= $d ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
