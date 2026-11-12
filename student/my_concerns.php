<?php
// student/my_concerns.php - My Concerns List (Figure 5 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();
$studentId = $user['id'];

// Lookups for the filter bar
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$statuses   = $pdo->query("SELECT * FROM statuses ORDER BY status_id ASC")->fetchAll();

// Server-side filters + pagination, always scoped to this student
[$whereSql, $params] = concernFilterSql(false);
$params['student_id'] = $studentId;
$fromSql = "
    FROM concerns c
    JOIN categories cat ON c.category_id = cat.category_id
    JOIN statuses s ON c.status_id = s.status_id
    WHERE c.student_id = :student_id AND {$whereSql}
";
$countStmt = $pdo->prepare("SELECT COUNT(*) {$fromSql}");
$countStmt->execute($params);
$pager = paginate((int)$countStmt->fetchColumn());

$stmt = $pdo->prepare("
    SELECT c.*, cat.category_name, s.status_name
    {$fromSql}
    ORDER BY c.date_submitted DESC, c.concern_id DESC
    LIMIT {$pager['per_page']} OFFSET {$pager['offset']}
");
$stmt->execute($params);
$concerns = $stmt->fetchAll();

$pageTitle = "My Concerns | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Concerns</div>
    <h1 class="page-title">My Concerns</h1>
    <p class="page-desc">Track the status, SLA deadline and responses for everything you have reported.</p>
  </div>
  <div class="page-actions">
    <a href="submit_concern.php" class="btn btn-primary"><?= icon('plus', '', 16) ?> Submit Concern</a>
  </div>
</div>

<?php $searchPlaceholder = 'Search by title, ticket ID or description...'; require __DIR__ . '/../includes/concern_filter_bar.php'; ?>

<?php $filtered = !empty($_GET['q']) || !empty($_GET['category']) || !empty($_GET['status']) || !empty($_GET['priority']) || !empty($_GET['sla']); ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-stack" id="myConcernsTable">
      <thead>
        <tr>
          <th>Concern</th>
          <th>Priority / SLA</th>
          <th>Status</th>
          <th>Last update</th>
          <th class="cell-actions"><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$concerns): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-state-icon"><?= icon($filtered ? 'search' : 'clipboard', '', 22) ?></div>
              <div class="empty-state-title"><?= $filtered ? 'No matching concerns' : 'No concerns yet' ?></div>
              <p><?= $filtered ? 'Try a different search term or clear the filters.' : 'Report an issue on campus and follow its progress here.' ?></p>
              <?php if ($filtered): ?>
                <a href="my_concerns.php" class="btn btn-secondary btn-sm">Clear filters</a>
              <?php else: ?>
                <a href="submit_concern.php" class="btn btn-primary btn-sm"><?= icon('plus', '', 14) ?> Submit a concern</a>
              <?php endif; ?>
            </div>
          </td></tr>
        <?php endif; ?>
        <?php foreach ($concerns as $c): ?>
          <tr data-href="concern_details.php?id=<?= (int)$c['concern_id'] ?>">
            <td class="cell-title">
              <div class="flex items-center gap-2" style="margin-bottom: 3px;">
                <span class="ticket-badge"><?= e($c['ticket_id']) ?></span>
                <?php if ((int)$c['reopen_count'] > 0): ?><span class="badge-tag badge-amber">Reopened</span><?php endif; ?>
                <?php if (!empty($c['is_anonymous'])): ?><span class="badge-tag badge-gray" title="Administrators cannot see your name"><?= icon('eye-off', '', 11) ?> Anonymous</span><?php endif; ?>
              </div>
              <a href="concern_details.php?id=<?= (int)$c['concern_id'] ?>"><?= e($c['title']) ?></a>
              <span class="cell-sub"><?= e($c['category_name']) ?> · Submitted <?= formatDate($c['date_submitted']) ?></span>
            </td>
            <td>
              <div class="pill-stack">
                <?= renderPriorityBadge($c['priority'], (int)$c['ai_urgency_score']) ?>
                <?= renderSlaBadge($c) ?>
              </div>
            </td>
            <td><?= renderStatusBadge($c['status_name']) ?></td>
            <td class="cell-nowrap text-sm"><?= e(timeAgo($c['updated_at'] ?? $c['date_submitted'])) ?></td>
            <td class="cell-actions">
              <a href="concern_details.php?id=<?= (int)$c['concern_id'] ?>" class="btn-icon" title="Open ticket" aria-label="Open <?= e($c['ticket_id']) ?>"><?= icon('chevron-right', '', 16) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    <?= renderPagination($pager) ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
