<?php
// admin/dashboard.php - Administrator Dashboard (Figure 10 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$user = getCurrentUser();

// System-wide status metrics
$metrics = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(c.status_id = 1) AS pending,
           SUM(c.status_id = 2) AS in_progress,
           SUM(c.status_id = 3) AS resolved,
           SUM(c.status_id = 4) AS withdrawn,
           SUM(c.date_submitted >= NOW() - INTERVAL 7 DAY) AS this_week,
           -- Resolved this calendar month, by resolution date (same rule as the Reports monthly table)
           COALESCE(SUM(c.status_id = 3 AND COALESCE(c.resolved_at, c.updated_at, c.date_submitted) >= DATE_FORMAT(NOW(), '%Y-%m-01')), 0) AS resolved_this_month
    FROM concerns c
")->fetch();
$total = (int)$metrics['total'];
$handled = $total - (int)$metrics['withdrawn']; // withdrawn tickets never needed resolving
$resolutionRate = $handled > 0 ? round((int)$metrics['resolved'] / $handled * 100) : 0;

// Active Critical / Rapid Response Team alerts
$criticalAlerts = $pdo->query("
    SELECT c.*, " . anonStudentColumns() . ", cat.category_name, st.status_name
    FROM concerns c
    JOIN students s ON c.student_id = s.student_id
    JOIN categories cat ON c.category_id = cat.category_id
    JOIN statuses st ON c.status_id = st.status_id
    WHERE (c.priority = 'Critical' OR c.is_rrt_alert = 1) AND c.status_id IN (1, 2)
    ORDER BY c.date_submitted DESC
")->fetchAll();

// Recent concerns
$recentConcerns = $pdo->query("
    SELECT c.*, " . anonStudentColumns() . ", cat.category_name, st.status_name
    FROM concerns c
    JOIN students s ON c.student_id = s.student_id
    JOIN categories cat ON c.category_id = cat.category_id
    JOIN statuses st ON c.status_id = st.status_id
    ORDER BY c.date_submitted DESC, c.concern_id DESC
    LIMIT 7
")->fetchAll();

// SLA health
$slaStats = $pdo->query("
    SELECT
        SUM(c.status_id IN (1, 2)) AS open_total,
        SUM(c.status_id IN (1, 2) AND c.sla_due_at < NOW()) AS breached,
        SUM(c.status_id IN (1, 2) AND c.sla_due_at >= NOW()
            AND TIMESTAMPDIFF(SECOND, NOW(), c.sla_due_at) <= c.sla_hours * 3600 * " . SLA_AT_RISK_RATIO . ") AS at_risk,
        SUM(c.status_id = 3) AS resolved_total,
        SUM(c.status_id = 3 AND c.resolved_at <= c.sla_due_at) AS resolved_on_time
    FROM concerns c
")->fetch();
$openTotal     = (int)$slaStats['open_total'];
$breached      = (int)$slaStats['breached'];
$atRisk        = (int)$slaStats['at_risk'];
$onTrack       = max(0, $openTotal - $breached - $atRisk);
$slaCompliance = (int)$slaStats['resolved_total'] > 0
    ? round((int)$slaStats['resolved_on_time'] / (int)$slaStats['resolved_total'] * 100) : null;

// Open concerns by category
$byCategory = $pdo->query("
    SELECT cat.category_name, COUNT(c.concern_id) AS open_count
    FROM categories cat
    LEFT JOIN concerns c ON c.category_id = cat.category_id AND c.status_id IN (1, 2)
    GROUP BY cat.category_id, cat.category_name
    ORDER BY open_count DESC, cat.category_name
")->fetchAll();
$catMax = max(1, ...array_map('intval', array_column($byCategory, 'open_count')));

// My queue: open tickets assigned to me, most urgent first
$myQueueStmt = $pdo->prepare("
    SELECT c.*, st.status_name FROM concerns c
    JOIN statuses st ON st.status_id = c.status_id
    WHERE c.assigned_admin_id = :me AND c.status_id IN (1, 2)
    ORDER BY FIELD(c.priority, 'Critical', 'High', 'Medium', 'Low'), c.sla_due_at
    LIMIT 5
");
$myQueueStmt->execute(['me' => $user['id']]);
$myQueue = $myQueueStmt->fetchAll();
$unassignedOpen = (int)$pdo->query("SELECT COUNT(*) FROM concerns WHERE assigned_admin_id IS NULL AND status_id IN (1, 2)")->fetchColumn();

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$pageTitle = "Admin Dashboard | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Institution overview</div>
    <h1 class="page-title"><?= $greeting ?>, <?= e(explode(' ', $user['name'])[0]) ?></h1>
    <p class="page-desc">
      <?= (int)$metrics['pending'] ?> concern<?= (int)$metrics['pending'] === 1 ? '' : 's' ?> waiting for review
      · <?= (int)$metrics['this_week'] ?> submitted in the last 7 days
    </p>
  </div>
  <div class="page-actions">
    <a href="reports.php" class="btn btn-secondary"><?= icon('bar-chart', '', 16) ?> Reports</a>
    <a href="concerns.php" class="btn btn-primary"><?= icon('clipboard', '', 16) ?> Manage Concerns</a>
  </div>
</div>

<!-- Rapid Response Team alerts -->
<?php foreach ($criticalAlerts as $crit): $sla = getSlaState($crit); ?>
  <div class="rrt-alert-banner">
    <div class="rrt-alert-left">
      <div class="rrt-alert-icon"><?= icon('zap', '', 20) ?></div>
      <div style="min-width: 0;">
        <div class="flex items-center gap-2" style="flex-wrap: wrap;">
          <span class="rrt-alert-badge">RRT CRITICAL</span>
          <span class="ticket-badge"><?= e($crit['ticket_id']) ?></span>
          <span class="rrt-alert-title"><?= e($crit['title']) ?></span>
        </div>
        <div class="rrt-alert-meta">
          <?= e($crit['student_name']) ?> · <?= e($crit['category_name']) ?> · Urgency <?= (int)$crit['ai_urgency_score'] ?>/100 ·
          <strong style="color: <?= $sla['state'] === 'breached' ? 'var(--danger)' : 'inherit' ?>;"><?= e($sla['detail']) ?></strong>
        </div>
      </div>
    </div>
    <a href="concern_details.php?id=<?= (int)$crit['concern_id'] ?>#responseBox" class="btn btn-danger btn-sm">Respond now <?= icon('arrow-right', '', 14) ?></a>
  </div>
<?php endforeach; ?>

<!-- Status metrics -->
<div class="metrics-grid" style="margin-top: <?= $criticalAlerts ? '10px' : '0' ?>;">
  <a href="concerns.php" class="metric-card">
    <div class="metric-icon-wrap metric-icon-total"><?= icon('folder', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Total Concerns</span>
      <span class="metric-number"><?= $total ?></span>
      <span class="metric-sub"><?= $resolutionRate ?>% resolved<?= (int)$metrics['withdrawn'] ? ' · ' . (int)$metrics['withdrawn'] . ' withdrawn' : '' ?></span>
    </div>
  </a>
  <a href="concerns.php?status=1" class="metric-card">
    <div class="metric-icon-wrap metric-icon-pending"><?= icon('clock', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Pending</span>
      <span class="metric-number"><?= (int)$metrics['pending'] ?></span>
      <span class="metric-sub">Awaiting first action</span>
    </div>
  </a>
  <a href="concerns.php?status=2" class="metric-card">
    <div class="metric-icon-wrap metric-icon-progress"><?= icon('refresh', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">In Progress</span>
      <span class="metric-number"><?= (int)$metrics['in_progress'] ?></span>
      <span class="metric-sub">Being worked on</span>
    </div>
  </a>
  <a href="concerns.php?status=3" class="metric-card">
    <div class="metric-icon-wrap metric-icon-resolved"><?= icon('check-circle', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Resolved</span>
      <span class="metric-number"><?= (int)$metrics['resolved'] ?></span>
      <span class="metric-sub"><?= (int)$metrics['resolved_this_month'] ?> resolved in <?= date('F') ?></span>
    </div>
  </a>
</div>

<div class="grid-main-side">
  <!-- Recent concerns -->
  <div>
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">Recent Concerns</h2>
          <div class="card-subtitle">Latest submissions across all colleges</div>
        </div>
        <a href="concerns.php" class="card-link">View all <?= icon('arrow-right', '', 14) ?></a>
      </div>
      <div class="table-responsive">
        <table class="table table-stack">
          <thead>
            <tr>
              <th>Concern</th>
              <th>Submitted by</th>
              <th>Priority / SLA</th>
              <th>Status</th>
              <th class="cell-actions"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$recentConcerns): ?>
              <tr><td colspan="5">
                <div class="empty-state">
                  <div class="empty-state-icon"><?= icon('clipboard', '', 22) ?></div>
                  <div class="empty-state-title">No concerns yet</div>
                  <p>New student concerns will appear here as soon as they are submitted.</p>
                </div>
              </td></tr>
            <?php endif; ?>
            <?php foreach ($recentConcerns as $c): ?>
              <tr data-href="concern_details.php?id=<?= (int)$c['concern_id'] ?>">
                <td class="cell-title">
                  <div class="flex items-center gap-2" style="margin-bottom: 3px;">
                    <span class="ticket-badge"><?= e($c['ticket_id']) ?></span>
                    <?php if ((int)$c['reopen_count'] > 0): ?><span class="badge-tag badge-amber">Reopened</span><?php endif; ?>
                  </div>
                  <a href="concern_details.php?id=<?= (int)$c['concern_id'] ?>"><?= e($c['title']) ?></a>
                  <span class="cell-sub"><?= e($c['category_name']) ?> · <?= formatDate($c['date_submitted']) ?></span>
                </td>
                <td>
                  <div class="person">
                    <?= senderAvatar($c) ?>
                    <div style="min-width: 0;">
                      <div class="person-name"><?= e($c['student_name']) ?></div>
                      <div class="person-sub"><?= $c['is_anonymous'] ? 'Name hidden' : e($c['student_no']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <div class="pill-stack">
                    <?= renderPriorityBadge($c['priority'], (int)$c['ai_urgency_score']) ?>
                    <?= renderSlaBadge($c, false) ?>
                  </div>
                </td>
                <td><?= renderStatusBadge($c['status_name']) ?></td>
                <td class="cell-actions">
                  <a href="concern_details.php?id=<?= (int)$c['concern_id'] ?>" class="btn-icon" title="Open ticket"><?= icon('arrow-right', '', 16) ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Side panels -->
  <div>
    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">My Queue</h2>
          <div class="card-subtitle">Open tickets assigned to you</div>
        </div>
        <a href="concerns.php?assigned=me" class="card-link">All <?= icon('arrow-right', '', 14) ?></a>
      </div>
      <?php if (!$myQueue): ?>
        <div class="card-body text-sm text-muted">
          Nothing assigned to you.
          <?php if ($unassignedOpen > 0): ?>
            <a href="concerns.php?assigned=none" class="font-semibold"><?= $unassignedOpen ?> open ticket<?= $unassignedOpen === 1 ? '' : 's' ?> unassigned</a>.
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($myQueue as $q): ?>
        <a href="concern_details.php?id=<?= (int)$q['concern_id'] ?>" class="list-item" style="padding: 12px 20px; text-decoration: none; color: inherit;">
          <div class="list-item-body">
            <div class="flex items-center gap-2" style="margin-bottom: 4px;">
              <span class="ticket-badge"><?= e($q['ticket_id']) ?></span>
              <?= renderSlaBadge($q, false) ?>
            </div>
            <div class="text-sm font-semibold truncate" style="color: var(--text-primary);"><?= e($q['title']) ?></div>
            <div class="text-xs text-muted"><?= e($q['priority']) ?> · <?= e(getSlaState($q)['detail']) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">SLA Health</h2>
          <div class="card-subtitle"><?= $openTotal ?> open ticket<?= $openTotal === 1 ? '' : 's' ?></div>
        </div>
      </div>
      <div class="card-body">
        <?php if ($openTotal > 0): ?>
          <div style="display: flex; height: 10px; border-radius: 99px; overflow: hidden; gap: 2px; margin-bottom: 14px;" role="img"
               aria-label="<?= $breached ?> breached, <?= $atRisk ?> at risk, <?= $onTrack ?> on track">
            <?php foreach ([[$breached, 'var(--danger)'], [$atRisk, 'var(--warning)'], [$onTrack, 'var(--brand-blue)']] as [$n, $color]): if ($n > 0): ?>
              <div style="flex: <?= $n ?>; background: <?= $color ?>;"></div>
            <?php endif; endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="kv-list">
          <a class="kv-row" href="concerns.php?sla=breached" style="text-decoration: none;">
            <span class="kv-key"><span class="sla-pill sla-breached">Breached</span></span>
            <span class="kv-val"><?= $breached ?></span>
          </a>
          <a class="kv-row" href="concerns.php?sla=at_risk" style="text-decoration: none;">
            <span class="kv-key"><span class="sla-pill sla-at_risk">At risk</span></span>
            <span class="kv-val"><?= $atRisk ?></span>
          </a>
          <a class="kv-row" href="concerns.php?sla=on_track" style="text-decoration: none;">
            <span class="kv-key"><span class="sla-pill sla-on_track">On track</span></span>
            <span class="kv-val"><?= $onTrack ?></span>
          </a>
          <div class="kv-row">
            <span class="kv-key">Resolved within SLA</span>
            <span class="kv-val"><?= $slaCompliance === null ? '—' : $slaCompliance . '%' ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div>
          <h2 class="card-title">Open by Category</h2>
          <div class="card-subtitle">Where the workload is</div>
        </div>
      </div>
      <div class="card-body" style="display: flex; flex-direction: column; gap: 14px;">
        <?php foreach ($byCategory as $cat): $n = (int)$cat['open_count']; ?>
          <div>
            <div class="flex justify-between text-sm" style="margin-bottom: 6px;">
              <span class="font-semibold"><?= e($cat['category_name']) ?></span>
              <span class="text-muted"><?= $n ?></span>
            </div>
            <div class="hbar-track"><div class="hbar-fill" style="width: <?= round($n / $catMax * 100) ?>%;"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
