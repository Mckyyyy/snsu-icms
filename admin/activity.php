<?php
// admin/activity.php - Administrator activity log (accountability for user management, announcements, assignments)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

$actionLabels = [
    'user_created'            => ['Created account', 'plus', 'badge-green'],
    'user_activated'          => ['Activated account', 'eye', 'badge-green'],
    'user_deactivated'        => ['Deactivated account', 'eye-off', 'badge-amber'],
    'user_deleted'            => ['Deleted account', 'trash', 'badge-red'],
    'password_reset'          => ['Reset password', 'lock', 'badge-amber'],
    'reset_request_dismissed' => ['Dismissed reset request', 'x', 'badge-gray'],
    'announcement_published'  => ['Published announcement', 'megaphone', 'badge-blue'],
    'announcement_deleted'    => ['Deleted announcement', 'trash', 'badge-red'],
    'concern_assigned'        => ['Assigned ticket', 'user', 'badge-blue'],
    'concern_classified'      => ['Reclassified ticket', 'zap', 'badge-purple'],
    'concern_deleted'         => ['Deleted ticket', 'trash', 'badge-red'],
    'category_created'        => ['Added category', 'plus', 'badge-green'],
    'category_updated'        => ['Edited category', 'edit', 'badge-blue'],
    'category_activated'      => ['Showed category', 'eye', 'badge-green'],
    'category_deactivated'    => ['Hid category', 'eye-off', 'badge-amber'],
    'category_deleted'        => ['Deleted category', 'trash', 'badge-red'],
    'feedback_replied'        => ['Replied to feedback', 'message-square', 'badge-blue'],
    'student_approved'        => ['Approved registration', 'check', 'badge-green'],
    'student_rejected'        => ['Rejected registration', 'x', 'badge-red'],
    'masterlist_imported'     => ['Imported masterlist', 'upload', 'badge-blue'],
    'masterlist_entry_added'  => ['Added to masterlist', 'plus', 'badge-green'],
    'masterlist_entry_updated'=> ['Edited masterlist entry', 'edit', 'badge-blue'],
    'masterlist_entry_deleted'=> ['Removed from masterlist', 'trash', 'badge-red'],
    'masterlist_entry_archived'=> ['Archived masterlist entry', 'archive', 'badge-gray'],
    'masterlist_entry_restored'=> ['Restored masterlist entry', 'refresh', 'badge-green'],
    'announcement_updated'    => ['Edited announcement', 'edit', 'badge-blue'],
    'announcement_archived'   => ['Archived announcement', 'archive', 'badge-gray'],
    'announcement_restored'   => ['Restored announcement', 'refresh', 'badge-green'],
    'category_archived'       => ['Archived category', 'archive', 'badge-gray'],
    'category_restored'       => ['Restored category', 'refresh', 'badge-green'],
    'concern_archived'        => ['Archived ticket', 'archive', 'badge-gray'],
    'concern_restored'        => ['Restored ticket', 'refresh', 'badge-green'],
    'user_archived'           => ['Archived account', 'archive', 'badge-gray'],
    'user_restored'           => ['Restored account', 'refresh', 'badge-green'],
    'email_test'              => ['Sent test email', 'mail', 'badge-blue'],
    'iso_results_updated'     => ['Updated ISO 25010 results', 'bar-chart', 'badge-purple'],
];

$adminFilter  = (int)($_GET['admin'] ?? 0);
$actionFilter = array_key_exists($_GET['action'] ?? '', $actionLabels) ? $_GET['action'] : '';

$where = ['1 = 1'];
$params = [];
if ($adminFilter > 0) {
    $where[] = 'l.admin_id = :adm';
    $params['adm'] = $adminFilter;
}
if ($actionFilter !== '') {
    $where[] = 'l.action = :act';
    $params['act'] = $actionFilter;
}
$whereSql = implode(' AND ', $where);

$count = $pdo->prepare("SELECT COUNT(*) FROM admin_activity_logs l WHERE {$whereSql}");
$count->execute($params);
$pager = paginate((int)$count->fetchColumn(), 20);

$stmt = $pdo->prepare("
    SELECT l.*, a.name AS admin_name, a.role AS admin_role
    FROM admin_activity_logs l
    LEFT JOIN administrators a ON a.admin_id = l.admin_id
    WHERE {$whereSql}
    ORDER BY l.created_at DESC, l.activity_id DESC
    LIMIT {$pager['per_page']} OFFSET {$pager['offset']}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$admins = $pdo->query("SELECT admin_id, name FROM administrators ORDER BY name")->fetchAll();
$todayCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_activity_logs WHERE created_at >= CURDATE()")->fetchColumn();

$pageTitle = "Activity Log | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Accountability</div>
    <h1 class="page-title">Activity Log</h1>
    <p class="page-desc">Every account change, password reset, announcement and ticket assignment made by administrators. <?= $todayCount ?> action<?= $todayCount === 1 ? '' : 's' ?> today.</p>
  </div>
</div>

<form method="GET" class="filter-bar">
  <select name="admin" class="form-control" onchange="this.form.submit()" aria-label="Administrator">
    <option value="0">All administrators</option>
    <?php foreach ($admins as $a): ?>
      <option value="<?= (int)$a['admin_id'] ?>" <?= $adminFilter === (int)$a['admin_id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="action" class="form-control" onchange="this.form.submit()" aria-label="Action">
    <option value="">All actions</option>
    <?php foreach ($actionLabels as $key => [$label]): ?>
      <option value="<?= $key ?>" <?= $actionFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($adminFilter || $actionFilter): ?><a href="activity.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-stack">
      <thead>
        <tr><th>When</th><th>Administrator</th><th>Action</th><th>Details</th><th>IP address</th></tr>
      </thead>
      <tbody>
        <?php if (!$logs): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-state-icon"><?= icon('clipboard', '', 22) ?></div>
              <div class="empty-state-title">No activity recorded<?= ($adminFilter || $actionFilter) ? ' for these filters' : ' yet' ?></div>
              <p>Actions such as creating accounts, resetting passwords or assigning tickets will be listed here.</p>
            </div>
          </td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log):
          [$label, $ic, $badge] = $actionLabels[$log['action']] ?? [ucwords(str_replace('_', ' ', $log['action'])), 'info', 'badge-gray'];
        ?>
          <tr>
            <td class="cell-nowrap text-sm">
              <?= formatDateTime($log['created_at']) ?>
              <span class="cell-sub"><?= e(timeAgo($log['created_at'])) ?></span>
            </td>
            <td>
              <div class="person">
                <div class="avatar avatar-admin" style="width: 30px; height: 30px; font-size: 0.7rem;"><?= e(userInitials($log['admin_name'] ?? '?')) ?></div>
                <div style="min-width: 0;">
                  <div class="person-name"><?= e($log['admin_name'] ?? 'Deleted administrator') ?></div>
                  <div class="person-sub"><?= e($log['admin_role'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td><span class="badge-tag <?= $badge ?>"><?= icon($ic, '', 12) ?> <?= e($label) ?></span></td>
            <td class="text-sm" style="min-width: 220px;">
              <?php if ($log['target_type'] === 'concern' && $log['target_id'] && $log['action'] !== 'concern_deleted'): ?>
                <a href="concern_details.php?id=<?= (int)$log['target_id'] ?>"><?= e($log['details'] ?? '') ?></a>
              <?php else: ?>
                <?= e($log['details'] ?? '—') ?>
              <?php endif; ?>
              <?php if ($log['target_type'] && !in_array($log['target_type'], ['concern'], true)): ?>
                <span class="cell-sub"><?= e(ucfirst($log['target_type'])) ?><?= $log['target_id'] ? ' #' . (int)$log['target_id'] : '' ?></span>
              <?php endif; ?>
            </td>
            <td class="mono text-xs"><?= e($log['ip_address'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer"><?= renderPagination($pager) ?></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
