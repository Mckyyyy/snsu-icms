<?php
// admin/users.php - Manage Users (Figure 11 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$currentAdminId = (int)getCurrentUser()['id'];

// ---- Filters (?q, ?role=student|admin, ?status=Active|Inactive) ----
$q      = trim((string)($_GET['q'] ?? ''));
$roleFilter   = in_array($_GET['role'] ?? '', ['student', 'admin'], true) ? $_GET['role'] : '';
$status = in_array($_GET['status'] ?? '', ['Active', 'Inactive', 'Archived'], true) ? $_GET['status'] : '';

$union = "
    SELECT student_id AS id, name, email, student_no AS ref, 'Student' AS role_label, department, program AS detail,
           status, created_at, last_login_at, must_change_password, 'student' AS type, archived_at
    FROM students WHERE status <> 'Pending'
    UNION ALL
    SELECT admin_id, name, email, NULL, role, department, NULL,
           status, created_at, last_login_at, must_change_password, 'admin', archived_at
    FROM administrators
";
$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR u.ref LIKE :q3 OR u.department LIKE :q4)';
    foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
        $params[$k] = "%{$q}%";
    }
}
if ($roleFilter !== '') {
    $where[] = 'u.type = :role';
    $params['role'] = $roleFilter;
}
// Archived accounts are kept for the record but only listed under "Archived"
if ($status === 'Archived') {
    $where[] = 'u.archived_at IS NOT NULL';
} else {
    $where[] = 'u.archived_at IS NULL';
    if ($status !== '') {
        $where[] = 'u.status = :status';
        $params['status'] = $status;
    }
}
$whereSql = implode(' AND ', $where);

$count = $pdo->prepare("SELECT COUNT(*) FROM ({$union}) u WHERE {$whereSql}");
$count->execute($params);
$pager = paginate((int)$count->fetchColumn());

$stmt = $pdo->prepare("
    SELECT u.* FROM ({$union}) u
    WHERE {$whereSql}
    ORDER BY u.type = 'admin' DESC, u.name ASC
    LIMIT {$pager['per_page']} OFFSET {$pager['offset']}
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Header stats
$stats = $pdo->query("
    SELECT (SELECT COUNT(*) FROM students WHERE status <> 'Pending' AND archived_at IS NULL) AS students,
           (SELECT COUNT(*) FROM administrators WHERE archived_at IS NULL) AS admins,
           (SELECT COUNT(*) FROM students WHERE status = 'Inactive' AND archived_at IS NULL) + (SELECT COUNT(*) FROM administrators WHERE status = 'Inactive' AND archived_at IS NULL) AS inactive,
           (SELECT COUNT(*) FROM students WHERE created_at >= NOW() - INTERVAL 30 DAY) AS new_students
")->fetch();

// Pending password reset requests (from forgot_password.php)
$resetRequests = $pdo->query("
    SELECT r.request_id, r.user_type, r.user_id, r.requested_at,
           COALESCE(s.name, a.name) AS name,
           COALESCE(s.student_no, a.email) AS ref
    FROM password_reset_requests r
    LEFT JOIN students s ON r.user_type = 'student' AND s.student_id = r.user_id
    LEFT JOIN administrators a ON r.user_type = 'admin' AND a.admin_id = r.user_id
    WHERE r.status = 'Pending'
    ORDER BY r.requested_at ASC
")->fetchAll();

// Rejected self-registrations, kept for the record (newest first)
$rejections = $pdo->query("
    SELECT r.*, a.name AS rejected_by_name
    FROM registration_rejections r LEFT JOIN administrators a ON a.admin_id = r.rejected_by
    ORDER BY r.rejected_at DESC LIMIT 50
")->fetchAll();

// Self-registrations waiting for approval (not on the masterlist)
$pendingStudents = $pdo->query("
    SELECT student_id, student_no, name, email, department, program, registration_note, created_at
    FROM students WHERE status = 'Pending' ORDER BY created_at ASC
")->fetchAll();

// One-time display of a freshly generated temporary password
$tempNotice = $_SESSION['temp_password_notice'] ?? null;
unset($_SESSION['temp_password_notice']);

$hasFilter = $q !== '' || $roleFilter !== '' || $status !== '';

$pageTitle = "Manage Users | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Accounts</div>
    <h1 class="page-title">Manage Users</h1>
    <p class="page-desc">Student and administrator accounts, access status and password resets.</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openModal('addUserModal')"><?= icon('plus', '', 16) ?> Add User</button>
  </div>
</div>

<?php if ($tempNotice): ?>
  <div class="card" style="border-color: #fbd38d; background: #fffbf0;">
    <div class="card-body flex items-center justify-between gap-4" style="flex-wrap: wrap;">
      <div class="flex gap-3">
        <div class="metric-icon-wrap metric-icon-warning" style="width: 40px; height: 40px;"><?= icon('lock', '', 18) ?></div>
        <div>
          <div class="font-bold">Temporary password for <?= e($tempNotice['name']) ?></div>
          <div class="text-sm text-secondary"><?= e($tempNotice['email']) ?> · Share it privately. It is shown only once and must be changed at first sign-in.</div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <code id="tempPwd" class="mono" style="font-size: 1.05rem; font-weight: 700; background: #fff; border: 1px dashed #f2b01e; padding: 7px 12px; border-radius: 8px;"><?= e($tempNotice['password']) ?></code>
        <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('tempPwd').textContent).then(() => showToast('Copied to clipboard.', 'success', 'Temporary Password'))"><?= icon('clipboard', '', 14) ?> Copy</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="metrics-grid">
  <a href="?role=student" class="metric-card">
    <div class="metric-icon-wrap metric-icon-total"><?= icon('users', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Students</span><span class="metric-number"><?= (int)$stats['students'] ?></span><span class="metric-sub"><?= (int)$stats['new_students'] ?> joined in 30 days<?= (int)$stats['inactive'] ? ' · ' . (int)$stats['inactive'] . ' inactive' : '' ?></span></div>
  </a>
  <a href="?role=admin" class="metric-card">
    <div class="metric-icon-wrap" style="background: #f1ecff; color: #6d28d9;"><?= icon('shield', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Administrators</span><span class="metric-number"><?= (int)$stats['admins'] ?></span><span class="metric-sub">Staff with review access</span></div>
  </a>
  <a href="#pendingRegistrations" class="metric-card">
    <div class="metric-icon-wrap metric-icon-warning"><?= icon('clock', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Awaiting Approval</span><span class="metric-number"><?= count($pendingStudents) ?></span><span class="metric-sub">New registrations</span></div>
  </a>
  <div class="metric-card">
    <div class="metric-icon-wrap metric-icon-warning"><?= icon('lock', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Reset Requests</span><span class="metric-number"><?= count($resetRequests) ?></span><span class="metric-sub">Waiting for an admin</span></div>
  </div>
</div>

<?php if ($pendingStudents): ?>
  <div class="card" id="pendingRegistrations" style="border-color: #fde3a7; scroll-margin-top: 80px;">
    <div class="card-header" style="background: #fffbf0;">
      <div>
        <h2 class="card-title"><?= icon('clock', '', 18) ?> Registrations Awaiting Approval</h2>
        <div class="card-subtitle">These student numbers were not verified by the <a href="masterlist.php">masterlist</a>. Confirm with the Registrar that each person is enrolled before approving.</div>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-stack">
        <thead><tr><th>Student</th><th>Student number</th><th>College / program</th><th>Masterlist check</th><th>Registered</th><th class="cell-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($pendingStudents as $p): ?>
            <tr>
              <td>
                <div class="person">
                  <div class="avatar"><?= e(userInitials($p['name'])) ?></div>
                  <div style="min-width: 0;">
                    <div class="person-name"><?= e($p['name']) ?></div>
                    <div class="person-sub"><?= e($p['email']) ?></div>
                  </div>
                </div>
              </td>
              <td class="mono text-sm cell-nowrap"><?= e($p['student_no']) ?></td>
              <td class="text-sm"><?= e($p['program']) ?><span class="cell-sub"><?= e($p['department']) ?></span></td>
              <td class="text-sm" style="max-width: 240px;"><span class="badge-tag badge-amber"><?= icon('alert-circle', '', 12) ?> Not verified</span><span class="cell-sub"><?= e($p['registration_note'] ?? '') ?></span></td>
              <td class="cell-nowrap text-sm"><?= e(timeAgo($p['created_at'])) ?></td>
              <td class="cell-actions">
                <div class="row-actions">
                  <form action="../actions/registration_action.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="student_id" value="<?= (int)$p['student_id'] ?>">
                    <input type="hidden" name="add_to_masterlist" value="1">
                    <button type="button" class="btn btn-primary btn-sm"
                      data-confirm="Approve &lt;strong&gt;<?= e($p['name']) ?>&lt;/strong&gt; (<?= e($p['student_no']) ?>)? They will be added to the masterlist and can sign in right away."
                      data-confirm-title="Approve Registration" data-confirm-text="Approve" data-confirm-type="info"><?= icon('check', '', 14) ?> Approve</button>
                  </form>
                  <button type="button" class="btn btn-ghost btn-sm" style="color: var(--danger, #dc2626);"
                    data-id="<?= (int)$p['student_id'] ?>" data-name="<?= e($p['name']) ?>" data-no="<?= e($p['student_no']) ?>"
                    onclick="openRejectModal(this)">Reject</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-backdrop" id="rejectModal" onclick="if(event.target === this) closeModal('rejectModal')">
    <div class="modal-content" role="dialog" aria-labelledby="rejectTitle" style="max-width: 480px;">
      <div class="modal-header">
        <h3 class="card-title" id="rejectTitle">Reject Registration</h3>
        <button type="button" class="alert-close" onclick="closeModal('rejectModal')" aria-label="Close">&times;</button>
      </div>
      <form action="../actions/registration_action.php" method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="student_id" id="rejectId" value="">
        <div class="modal-body">
          <p class="text-sm" style="margin-top: 0;">The account of <strong id="rejectName"></strong> will be removed and they will be emailed. The student number becomes free again for the real owner.</p>
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="rejectReason">Reason <span class="optional">(optional, included in the email)</span></label>
            <textarea name="reason" id="rejectReason" class="form-control" rows="3" maxlength="500" placeholder="e.g. The student number does not belong to an enrolled student."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject &amp; Remove</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    function openRejectModal(btn) {
      document.getElementById('rejectId').value = btn.dataset.id;
      document.getElementById('rejectName').textContent = btn.dataset.name + ' (' + btn.dataset.no + ')';
      document.getElementById('rejectReason').value = '';
      openModal('rejectModal');
    }
  </script>
<?php endif; ?>

<?php if ($resetRequests): ?>
  <div class="card" style="border-color: #fde3a7;">
    <div class="card-header" style="background: #fffbf0;">
      <div>
        <h2 class="card-title"><?= icon('alert-circle', '', 18) ?> Pending Password Reset Requests</h2>
        <div class="card-subtitle">Verify the person's identity before generating a temporary password.</div>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-stack">
        <thead><tr><th>Name</th><th>Account</th><th>Requested</th><th class="cell-actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($resetRequests as $r): ?>
            <tr>
              <td>
                <div class="person">
                  <div class="avatar <?= $r['user_type'] === 'admin' ? 'avatar-admin' : '' ?>"><?= e(userInitials($r['name'] ?? '?')) ?></div>
                  <div class="person-name"><?= e($r['name'] ?? '(deleted user)') ?></div>
                </div>
              </td>
              <td><span class="badge-tag <?= $r['user_type'] === 'admin' ? 'badge-purple' : 'badge-blue' ?>"><?= ucfirst(e($r['user_type'])) ?></span> <span class="text-sm"><?= e($r['ref'] ?? '') ?></span></td>
              <td class="cell-nowrap text-sm"><?= e(timeAgo($r['requested_at'])) ?></td>
              <td class="cell-actions">
                <div class="row-actions">
                  <form action="../actions/password_reset_action.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="type" value="<?= e($r['user_type']) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['user_id'] ?>">
                    <button type="button" class="btn btn-primary btn-sm"
                      data-confirm="Verify the identity of &lt;strong&gt;<?= e($r['name'] ?? '') ?>&lt;/strong&gt; first. Generate a temporary password now?"
                      data-confirm-title="Reset Password" data-confirm-text="Generate" data-confirm-type="warning">Reset &amp; Generate</button>
                  </form>
                  <form action="../actions/password_reset_action.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="dismiss">
                    <input type="hidden" name="request_id" value="<?= (int)$r['request_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm">Dismiss</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php if ($rejections): ?>
  <details class="card" style="margin-bottom: 20px;">
    <summary class="card-header" style="cursor: pointer; list-style: none;">
      <h2 class="card-title"><?= icon('archive', '', 18) ?> Rejected Registrations</h2>
      <span class="badge-tag"><?= count($rejections) ?> on record</span>
    </summary>
    <div class="table-responsive">
      <table class="table table-stack">
        <thead><tr><th>Student</th><th>College / program</th><th>Reason</th><th>Rejected</th></tr></thead>
        <tbody>
          <?php foreach ($rejections as $r): ?>
            <tr>
              <td>
                <div class="person-name"><?= e($r['name']) ?></div>
                <div class="person-sub"><span class="mono"><?= e($r['student_no']) ?></span> · <?= e($r['email']) ?></div>
              </td>
              <td class="text-sm"><?= e($r['program'] ?? '—') ?><span class="cell-sub"><?= e($r['department'] ?? '') ?></span></td>
              <td class="text-sm"><?= e($r['reason'] ?? 'No reason given') ?></td>
              <td class="cell-nowrap text-sm"><?= e(formatDateTime($r['rejected_at'])) ?><span class="cell-sub"><?= e($r['rejected_by_name'] ?? '') ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
<?php endif; ?>

<!-- Filters -->
<form method="GET" class="filter-bar">
  <div class="search-input-wrap">
    <span class="search-icon"><?= icon('search', '', 16) ?></span>
    <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Search name, email, student number or college...">
  </div>
  <div class="segmented" aria-label="Role">
    <?php foreach (['' => 'All', 'student' => 'Students', 'admin' => 'Admins'] as $val => $label):
      $qs = http_build_query(array_filter(['q' => $q, 'role' => $val, 'status' => $status])); ?>
      <a href="?<?= e($qs) ?>" class="<?= $roleFilter === $val ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="role" value="<?= e($roleFilter) ?>">
  <select name="status" class="form-control" onchange="this.form.submit()" aria-label="Status">
    <option value="">Any status</option>
    <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
    <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
    <option value="Archived" <?= $status === 'Archived' ? 'selected' : '' ?>>Archived</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">Search</button>
  <?php if ($hasFilter): ?><a href="users.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-stack">
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>College / Department</th>
          <th>Status</th>
          <th>Last sign-in</th>
          <th class="cell-actions"><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$users): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <div class="empty-state-icon"><?= icon('users', '', 22) ?></div>
              <div class="empty-state-title">No users found</div>
              <p>Try a different search or clear the filters.</p>
            </div>
          </td></tr>
        <?php endif; ?>
        <?php
          $userPhotos = [
              'student' => profilePhotoVersions($pdo, 'student', array_column(array_filter($users, fn($x) => $x['type'] !== 'admin'), 'id')),
              'admin'   => profilePhotoVersions($pdo, 'admin', array_column(array_filter($users, fn($x) => $x['type'] === 'admin'), 'id')),
          ];
        ?>
        <?php foreach ($users as $u):
          $isAdmin = $u['type'] === 'admin';
          $isSelf  = $isAdmin && (int)$u['id'] === $currentAdminId;
          $nextStatus = $u['status'] === 'Active' ? 'Inactive' : 'Active';
        ?>
          <tr>
            <td>
              <div class="person">
                <?= avatarHtml($isAdmin ? 'admin' : 'student', (int)$u['id'], $u['name'], $userPhotos[$isAdmin ? 'admin' : 'student'][(int)$u['id']] ?? null, 'avatar' . ($isAdmin ? ' avatar-admin' : '')) ?>
                <div style="min-width: 0;">
                  <div class="person-name"><?= e($u['name']) ?><?= $isSelf ? ' <span class="badge-tag badge-gray">You</span>' : '' ?></div>
                  <div class="person-sub"><?= e($u['email']) ?><?= $u['ref'] ? ' · ' . e($u['ref']) : '' ?></div>
                </div>
              </div>
            </td>
            <td><span class="badge-tag <?= $isAdmin ? 'badge-purple' : 'badge-blue' ?>"><?= e($u['role_label']) ?></span></td>
            <td class="text-sm">
              <?= e($u['department']) ?>
              <?php if ($u['detail']): ?><span class="cell-sub"><?= e($u['detail']) ?></span><?php endif; ?>
            </td>
            <td>
              <?php if ($u['archived_at']): ?>
                <span class="badge-tag badge-gray"><?= icon('archive', '', 12) ?> Archived</span>
                <span class="cell-sub"><?= e(formatDate($u['archived_at'])) ?></span>
              <?php else: ?>
                <span class="badge-tag badge-dot <?= $u['status'] === 'Active' ? 'badge-green' : 'badge-gray' ?>"><?= e($u['status']) ?></span>
              <?php endif; ?>
              <?php if ((int)$u['must_change_password'] === 1): ?><span class="cell-sub">Temp password</span><?php endif; ?>
            </td>
            <td class="cell-nowrap text-sm"><?= $u['last_login_at'] ? e(timeAgo($u['last_login_at'])) : '<span class="text-muted">Never</span>' ?></td>
            <td class="cell-actions">
              <div class="row-actions">
                <?php if ($u['archived_at']): ?>
                  <form action="../actions/user_action.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="return_tab" value="archived">
                    <input type="hidden" name="type" value="<?= e($u['type']) ?>">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="button" class="btn btn-secondary btn-sm"
                       data-confirm="Restore &lt;strong&gt;<?= e($u['name']) ?>&lt;/strong&gt;? The account becomes active and can sign in again."
                       data-confirm-title="Restore Account" data-confirm-text="Restore" data-confirm-type="info"><?= icon('refresh', '', 14) ?> Restore</button>
                  </form>
                <?php elseif (!$isSelf): ?>
                  <form action="../actions/password_reset_action.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="type" value="<?= e($u['type']) ?>">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="button" class="btn-icon" title="Reset password"
                       data-confirm="Generate a temporary password for &lt;strong&gt;<?= e($u['name']) ?>&lt;/strong&gt;? Their current password will stop working."
                       data-confirm-title="Reset Password" data-confirm-text="Generate" data-confirm-type="warning"><?= icon('lock', '', 16) ?></button>
                  </form>
                  <form action="../actions/user_action.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="type" value="<?= e($u['type']) ?>">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="button" class="btn-icon" title="<?= $nextStatus === 'Inactive' ? 'Deactivate account' : 'Activate account' ?>"
                       data-confirm="Change the status of &lt;strong&gt;<?= e($u['name']) ?>&lt;/strong&gt; to <?= $nextStatus ?>?"
                       data-confirm-title="Change Account Status" data-confirm-text="Set <?= $nextStatus ?>" data-confirm-type="warning"><?= icon($nextStatus === 'Inactive' ? 'eye-off' : 'eye', '', 16) ?></button>
                  </form>
                  <form action="../actions/user_action.php" method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="type" value="<?= e($u['type']) ?>">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="button" class="btn-icon" title="Archive account (kept for the record)"
                       data-confirm="Archive &lt;strong&gt;<?= e($u['name']) ?>&lt;/strong&gt;? The account can no longer sign in, but it and its history are kept and it can be restored from Archived."
                       data-confirm-title="Archive Account" data-confirm-text="Archive" data-confirm-type="warning"><?= icon('archive', '', 16) ?></button>
                  </form>
                <?php else: ?>
                  <a href="profile.php" class="btn btn-secondary btn-sm">My profile</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer"><?= renderPagination($pager) ?></div>
</div>

<!-- Modal: Add New User -->
<div class="modal-backdrop" id="addUserModal" onclick="if(event.target === this) closeModal('addUserModal')">
  <div class="modal-content" role="dialog" aria-labelledby="addUserTitle">
    <div class="modal-header">
      <div>
        <h3 class="card-title" id="addUserTitle">Add New User</h3>
        <div class="card-subtitle">The user must change the initial password at first sign-in.</div>
      </div>
      <button type="button" class="alert-close" onclick="closeModal('addUserModal')" aria-label="Close">&times;</button>
    </div>
    <form action="../actions/user_action.php" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <span class="form-label">Account type</span>
          <div class="choice-grid" style="grid-template-columns: 1fr 1fr;">
            <label class="choice-card">
              <input type="radio" name="role" value="student" checked onchange="toggleAddUserFields(this.value)">
              <span class="choice-card-body"><span class="choice-card-title"><?= icon('user', '', 16) ?> Student</span><span class="choice-card-desc">Submits and tracks concerns</span></span>
            </label>
            <label class="choice-card">
              <input type="radio" name="role" value="admin" onchange="toggleAddUserFields(this.value)">
              <span class="choice-card-body"><span class="choice-card-title"><?= icon('shield', '', 16) ?> Administrator</span><span class="choice-card-desc">Reviews and resolves concerns</span></span>
            </label>
          </div>
        </div>

        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="addName">Full name <span class="required">*</span></label>
            <input type="text" name="name" id="addName" class="form-control" placeholder="Maria Santos" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="addEmail">Email <span class="required">*</span></label>
            <input type="email" name="email" id="addEmail" class="form-control" placeholder="user@snsu.edu.ph" required>
          </div>
        </div>

        <div id="studentOnlyFields" class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="addStudentNo">Student number</label>
            <input type="text" name="student_no" id="addStudentNo" class="form-control" placeholder="Auto-generated if blank">
          </div>
          <div class="form-group">
            <label class="form-label" for="addProgram">Degree program</label>
            <input type="text" name="program" id="addProgram" class="form-control" value="BS Information Technology">
          </div>
        </div>

        <div id="adminOnlyFields" class="form-group" style="display: none;">
          <label class="form-label" for="addAdminRole">Position / title</label>
          <input type="text" name="admin_role" id="addAdminRole" class="form-control" placeholder="e.g. Department Head, Dean, MIS Officer" value="Administrator">
        </div>

        <div class="form-group">
          <label class="form-label" for="addDepartment">College / Department</label>
          <input type="text" name="department" id="addDepartment" class="form-control" value="College of Computing & Information Sciences" list="deptList">
          <datalist id="deptList">
            <?php foreach (SNSU_DEPARTMENTS as $dept): ?><option value="<?= e($dept) ?>"><?php endforeach; ?>
          </datalist>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" for="addPassword">Initial password <span class="optional">(optional)</span></label>
          <input type="password" name="password" id="addPassword" class="form-control" placeholder="Leave blank to auto-generate" autocomplete="new-password">
          <span class="form-help">At least 8 characters with a letter and a number. A blank field generates a temporary password shown once after saving.</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('plus', '', 16) ?> Create User</button>
      </div>
    </form>
  </div>
</div>

<script>
  function toggleAddUserFields(role) {
    document.getElementById('studentOnlyFields').style.display = role === 'student' ? '' : 'none';
    document.getElementById('adminOnlyFields').style.display = role === 'admin' ? '' : 'none';
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
