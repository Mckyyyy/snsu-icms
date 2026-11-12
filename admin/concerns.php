<?php
// admin/concerns.php - Manage Concerns for Administrators (Figure 15 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

// Lookups for the filter bar
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$statuses   = $pdo->query("SELECT * FROM statuses ORDER BY status_id ASC")->fetchAll();

// Server-side filters + pagination (dynamic priority queue ordering)
$assignOptions = $pdo->query("SELECT admin_id, name FROM administrators WHERE status = 'Active' ORDER BY name")->fetchAll();
[$whereSql, $params] = concernFilterSql(true, (int)getCurrentUser()['id']);
$fromSql = "
    FROM concerns c
    JOIN students s ON c.student_id = s.student_id
    JOIN categories cat ON c.category_id = cat.category_id
    JOIN statuses st ON c.status_id = st.status_id
    LEFT JOIN administrators aa ON aa.admin_id = c.assigned_admin_id
    WHERE {$whereSql}
";
$countStmt = $pdo->prepare("SELECT COUNT(*) {$fromSql}");
$countStmt->execute($params);
$pager = paginate((int)$countStmt->fetchColumn());

// "New" filter: newest submissions first; every other view keeps the urgency queue order
$newestFirst = ($_GET['priority'] ?? '') === 'new';
$orderSql = $newestFirst
    ? "c.date_submitted DESC, c.concern_id DESC"
    : "(c.status_id IN (3, 4)) ASC,
             FIELD(c.priority, 'Critical', 'High', 'Medium', 'Low'),
             c.sla_due_at ASC,
             c.concern_id DESC";
$stmt = $pdo->prepare("
    SELECT c.*, " . anonStudentColumns() . ", cat.category_name, st.status_name, aa.name AS assigned_name,
           EXISTS (SELECT 1 FROM concern_attachments ca WHERE ca.concern_id = c.concern_id) AS has_attachment
    {$fromSql}
    ORDER BY {$orderSql}
    LIMIT {$pager['per_page']} OFFSET {$pager['offset']}
");
$stmt->execute($params);
$concerns = $stmt->fetchAll();
$showArchived  = !empty($_GET['archived']);
$archivedCount = (int)$pdo->query("SELECT COUNT(*) FROM concerns WHERE archived_at IS NOT NULL")->fetchColumn();

$pageTitle = "Manage Concerns | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Concern queue</div>
    <h1 class="page-title"><?= $showArchived ? 'Archived Concerns' : 'Manage Concerns' ?></h1>
    <p class="page-desc"><?= $showArchived
        ? 'Finished tickets taken out of the queue. They are kept for the record, still count in reports, and can be restored.'
        : ($newestFirst
        ? 'New concerns no administrator has responded to yet — newest first.'
        : 'Sorted by AI priority and SLA deadline — the most urgent open tickets are always on top.') ?></p>
  </div>
  <div class="page-actions">
    <?php if ($showArchived): ?>
      <a href="concerns.php" class="btn btn-secondary"><?= icon('arrow-left', '', 16) ?> Back to the queue</a>
    <?php else: ?>
      <a href="concerns.php?archived=1" class="btn btn-secondary"><?= icon('archive', '', 16) ?> Archived (<?= $archivedCount ?>)</a>
    <?php endif; ?>
    <a href="../actions/export_csv.php?type=concerns" class="btn btn-secondary"><?= icon('file-text', '', 16) ?> Export CSV</a>
  </div>
</div>

<?php $searchPlaceholder = 'Search title, ticket ID, description or student...'; require __DIR__ . '/../includes/concern_filter_bar.php'; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-stack" id="concernsTable">
      <thead>
        <tr>
          <th>Concern</th>
          <th>Submitted by</th>
          <th>Assigned to</th>
          <th>Priority / SLA</th>
          <th>Status</th>
          <th class="cell-actions"><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$concerns): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <div class="empty-state-icon"><?= icon('search', '', 22) ?></div>
              <div class="empty-state-title">No matching concerns</div>
              <p>Try a different search term or clear the filters.</p>
              <a href="concerns.php" class="btn btn-secondary btn-sm">Clear filters</a>
            </div>
          </td></tr>
        <?php endif; ?>
        <?php foreach ($concerns as $c): ?>
          <tr data-href="concern_details.php?id=<?= (int)$c['concern_id'] ?>">
            <td class="cell-title">
              <div class="flex items-center gap-2" style="margin-bottom: 3px; flex-wrap: wrap;">
                <span class="ticket-badge"><?= e($c['ticket_id']) ?></span>
                <?php if ($c['is_rrt_alert'] && in_array((int)$c['status_id'], [1, 2], true)): ?><span class="badge-tag badge-red badge-dot">RRT</span><?php endif; ?>
                <?php if ((int)$c['reopen_count'] > 0): ?><span class="badge-tag badge-amber">Reopened</span><?php endif; ?>
                <?php if ((int)$c['edit_count'] > 0 && (int)$c['status_id'] === 1): ?><span class="badge-tag badge-gray">Edited</span><?php endif; ?>
                <?php if (!empty($c['has_attachment'])): ?><span class="text-muted" title="Has attachment"><?= icon('paperclip', '', 14) ?></span><?php endif; ?>
              </div>
              <a href="concern_details.php?id=<?= (int)$c['concern_id'] ?>"><?= e($c['title']) ?></a>
              <span class="cell-sub"><?= e($c['category_name']) ?> · <?= formatDateTime($c['date_submitted']) ?></span>
            </td>
            <td>
              <div class="person">
                <?= senderAvatar($c) ?>
                <div style="min-width: 0;">
                  <div class="person-name"><?= e($c['student_name']) ?></div>
                  <div class="person-sub"><?= $c['is_anonymous'] ? 'Name hidden' : e($c['student_no'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td class="text-sm">
              <?php if ($c['assigned_name']): ?>
                <span class="flex items-center gap-2"><span class="avatar avatar-admin" style="width: 26px; height: 26px; font-size: 0.65rem;"><?= e(userInitials($c['assigned_name'])) ?></span><span class="nowrap"><?= e($c['assigned_name']) ?></span></span>
              <?php else: ?>
                <span class="badge-tag badge-gray">Unassigned</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="pill-stack">
                <?= renderPriorityBadge($c['priority'], (int)$c['ai_urgency_score']) ?>
                <?= renderSlaBadge($c) ?>
              </div>
            </td>
            <td><?= renderStatusBadge($c['status_name']) ?></td>
            <td class="cell-actions">
              <div class="row-actions">
                <a href="concern_details.php?id=<?= (int)$c['concern_id'] ?>" class="btn-icon" title="View details"><?= icon('eye', '', 16) ?></a>
                <a href="concern_details.php?id=<?= (int)$c['concern_id'] ?>#responseBox" class="btn-icon" title="Respond &amp; update status"><?= icon('edit', '', 16) ?></a>
                <?php if ($c['archived_at']): ?>
                  <form action="../actions/concern_admin_action.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="concern_id" value="<?= (int)$c['concern_id'] ?>">
                    <button type="button" class="btn-icon" title="Restore to the queue"
                       data-confirm="Restore ticket &lt;strong&gt;<?= e($c['ticket_id']) ?>&lt;/strong&gt; to the concern queue?"
                       data-confirm-title="Restore Ticket" data-confirm-text="Restore" data-confirm-type="info"><?= icon('refresh', '', 16) ?></button>
                  </form>
                <?php elseif (in_array((int)$c['status_id'], [3, 4], true)): ?>
                  <form action="../actions/concern_admin_action.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="concern_id" value="<?= (int)$c['concern_id'] ?>">
                    <button type="button" class="btn-icon" title="Archive (kept for the record)"
                       data-confirm="Archive ticket &lt;strong&gt;<?= e($c['ticket_id']) ?>&lt;/strong&gt;? It leaves the queue but is kept for the record, still counts in reports, and can be restored."
                       data-confirm-title="Archive Ticket" data-confirm-text="Archive" data-confirm-type="warning"><?= icon('archive', '', 16) ?></button>
                  </form>
                <?php endif; ?>
              </div>
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
