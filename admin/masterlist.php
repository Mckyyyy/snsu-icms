<?php
// admin/masterlist.php - Registrar masterlist of enrolled students
// Registrations whose student number and name match this list are approved automatically.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

$q      = trim((string)($_GET['q'] ?? ''));
$filter = in_array($_GET['show'] ?? '', ['registered', 'unregistered', 'archived'], true) ? $_GET['show'] : '';

// Archived entries are kept for the record but only listed under "Archived"
$where = [$filter === 'archived' ? 'm.archived_at IS NOT NULL' : 'm.archived_at IS NULL'];
$params = [];
if ($q !== '') {
    $where[] = '(m.student_no LIKE :q1 OR m.name LIKE :q2 OR m.email LIKE :q3 OR m.program LIKE :q4)';
    foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
        $params[$k] = "%{$q}%";
    }
}
if ($filter === 'registered') {
    $where[] = 's.student_id IS NOT NULL';
} elseif ($filter === 'unregistered') {
    $where[] = 's.student_id IS NULL';
}
$whereSql = implode(' AND ', $where);
$from = "FROM student_masterlist m LEFT JOIN students s ON s.student_no = m.student_no";

$count = $pdo->prepare("SELECT COUNT(*) {$from} WHERE {$whereSql}");
$count->execute($params);
$pager = paginate((int)$count->fetchColumn(), 20);

$stmt = $pdo->prepare("
    SELECT m.*, s.student_id, s.status AS account_status, a.name AS imported_by_name
    {$from}
    LEFT JOIN administrators a ON a.admin_id = m.imported_by
    WHERE {$whereSql}
    ORDER BY m.imported_at DESC, m.student_no ASC
    LIMIT {$pager['per_page']} OFFSET {$pager['offset']}
");
$stmt->execute($params);
$entries = $stmt->fetchAll();

$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           COALESCE(SUM(s.student_id IS NOT NULL), 0) AS registered,
           MAX(m.imported_at) AS last_import
    FROM student_masterlist m LEFT JOIN students s ON s.student_no = m.student_no
    WHERE m.archived_at IS NULL
")->fetch();
$archivedCount = (int)$pdo->query("SELECT COUNT(*) FROM student_masterlist WHERE archived_at IS NOT NULL")->fetchColumn();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Pending'")->fetchColumn();

$pageTitle = "Student Masterlist | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Accounts</div>
    <h1 class="page-title">Student Masterlist</h1>
    <p class="page-desc">The Registrar's list of enrolled students. A registration whose student number and name match this list is approved automatically; others wait for approval on the Users page.</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-secondary" onclick="openModal('addEntryModal')"><?= icon('plus', '', 16) ?> Add Student</button>
    <button type="button" class="btn btn-primary" onclick="openModal('importModal')"><?= icon('upload', '', 16) ?> Import CSV</button>
  </div>
</div>

<div class="metrics-grid">
  <a href="masterlist.php" class="metric-card">
    <div class="metric-icon-wrap metric-icon-total"><?= icon('users', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">On the list</span><span class="metric-number"><?= (int)$stats['total'] ?></span><span class="metric-sub"><?= $stats['last_import'] ? 'Updated ' . e(timeAgo($stats['last_import'])) : 'Nothing imported yet' ?></span></div>
  </a>
  <a href="?show=registered" class="metric-card">
    <div class="metric-icon-wrap metric-icon-success"><?= icon('check', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Registered</span><span class="metric-number"><?= (int)$stats['registered'] ?></span><span class="metric-sub">Have an account</span></div>
  </a>
  <a href="?show=unregistered" class="metric-card">
    <div class="metric-icon-wrap metric-icon-warning"><?= icon('clock', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Not yet registered</span><span class="metric-number"><?= (int)$stats['total'] - (int)$stats['registered'] ?></span><span class="metric-sub">No account yet</span></div>
  </a>
  <a href="users.php" class="metric-card">
    <div class="metric-icon-wrap metric-icon-danger"><?= icon('alert-circle', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Waiting for approval</span><span class="metric-number"><?= $pendingCount ?></span><span class="metric-sub">Not on the list</span></div>
  </a>
</div>

<form method="GET" class="filter-bar">
  <div class="search-input-wrap">
    <span class="search-icon"><?= icon('search', '', 16) ?></span>
    <input type="search" name="q" class="form-control" value="<?= e($q) ?>" placeholder="Search student number, name, email or program...">
  </div>
  <div class="segmented" aria-label="Show">
    <?php foreach (['' => 'All', 'registered' => 'Registered', 'unregistered' => 'Not registered', 'archived' => "Archived ({$archivedCount})"] as $val => $label):
      $qs = http_build_query(array_filter(['q' => $q, 'show' => $val])); ?>
      <a href="?<?= e($qs) ?>" class="<?= $filter === $val ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="show" value="<?= e($filter) ?>">
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-stack">
      <thead>
        <tr><th>Student number</th><th>Name</th><th>College / program</th><th>Account</th><th>Added</th><th class="cell-actions"><span class="sr-only">Actions</span></th></tr>
      </thead>
      <tbody>
        <?php if (!$entries): ?>
          <tr><td colspan="6">
            <div class="empty-state">
              <div class="empty-state-icon"><?= icon('file-text', '', 22) ?></div>
              <div class="empty-state-title"><?= ($q !== '' || $filter !== '') ? 'No students match these filters' : 'The masterlist is empty' ?></div>
              <p>Import the Registrar's list as a CSV file. <a href="../actions/masterlist_action.php?action=template">Download the template</a> to see the columns.</p>
            </div>
          </td></tr>
        <?php endif; ?>
        <?php foreach ($entries as $m): ?>
          <tr>
            <td class="mono text-sm cell-nowrap"><?= e($m['student_no']) ?></td>
            <td class="cell-title">
              <?= e($m['name']) ?>
              <?php if ($m['email']): ?><span class="cell-sub"><?= e($m['email']) ?></span><?php endif; ?>
            </td>
            <td class="text-sm">
              <?= e($m['program'] ?? '—') ?>
              <?php if ($m['department']): ?><span class="cell-sub"><?= e($m['department']) ?></span><?php endif; ?>
            </td>
            <td>
              <?php if ($m['archived_at']): ?>
                <span class="badge-tag badge-gray"><?= icon('archive', '', 12) ?> Archived</span>
              <?php elseif (!$m['student_id']): ?>
                <span class="badge-tag badge-gray">Not registered</span>
              <?php elseif ($m['account_status'] === 'Pending'): ?>
                <span class="badge-tag badge-amber">Waiting for approval</span>
              <?php else: ?>
                <span class="badge-tag badge-dot <?= $m['account_status'] === 'Active' ? 'badge-green' : 'badge-gray' ?>">Registered</span>
              <?php endif; ?>
            </td>
            <td class="cell-nowrap text-sm">
              <?= e(timeAgo($m['imported_at'])) ?>
              <span class="cell-sub"><?= e($m['imported_by_name'] ?? '') ?></span>
            </td>
            <td class="cell-actions">
              <div style="display: inline-flex; gap: 4px;">
              <?php if (!$m['archived_at']): ?>
              <button type="button" class="btn-icon" title="Edit this record" data-edit-entry="<?= e(json_encode([
                  'id' => (int)$m['masterlist_id'], 'student_no' => $m['student_no'], 'name' => $m['name'],
                  'email' => (string)$m['email'], 'department' => (string)$m['department'], 'program' => (string)$m['program'],
                  'registered' => $m['student_id'] !== null,
                ])) ?>"><?= icon('edit', '', 16) ?></button>
              <?php endif; ?>
              <form action="../actions/masterlist_action.php" method="POST">
                <?= csrfField() ?>
                <?php if ($m['archived_at']): ?>
                  <input type="hidden" name="action" value="restore">
                  <input type="hidden" name="return_tab" value="archived">
                  <input type="hidden" name="masterlist_id" value="<?= (int)$m['masterlist_id'] ?>">
                  <button type="button" class="btn-icon" title="Restore to the masterlist"
                    data-confirm="Restore &lt;strong&gt;<?= e($m['student_no']) ?>&lt;/strong&gt; to the masterlist? The student can then find and claim this record when registering."
                    data-confirm-title="Restore Entry" data-confirm-text="Restore" data-confirm-type="info"><?= icon('refresh', '', 16) ?></button>
                <?php else: ?>
                  <input type="hidden" name="action" value="archive">
                  <input type="hidden" name="masterlist_id" value="<?= (int)$m['masterlist_id'] ?>">
                  <button type="button" class="btn-icon" title="Archive (kept for the record)"
                    data-confirm="Archive &lt;strong&gt;<?= e($m['student_no']) ?>&lt;/strong&gt;? It can no longer be found or claimed on the Register page. An existing account is not affected, and you can restore it from Archived."
                    data-confirm-title="Archive Entry" data-confirm-text="Archive" data-confirm-type="warning"><?= icon('archive', '', 16) ?></button>
                <?php endif; ?>
              </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer"><?= renderPagination($pager) ?></div>
</div>

<!-- Import CSV -->
<div class="modal-backdrop" id="importModal" onclick="if(event.target === this) closeModal('importModal')">
  <div class="modal-content" role="dialog" aria-labelledby="importTitle" style="max-width: 520px;">
    <div class="modal-header">
      <h3 class="card-title" id="importTitle">Import Masterlist (CSV)</h3>
      <button type="button" class="alert-close" onclick="closeModal('importModal')" aria-label="Close">&times;</button>
    </div>
    <form action="../actions/masterlist_action.php" method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="import">
      <div class="modal-body">
        <ol class="text-sm text-secondary" style="margin: 0 0 14px 18px; display: flex; flex-direction: column; gap: 4px;">
          <li>Get the list of enrolled students from the Registrar.</li>
          <li>Put it in the template's columns: <span class="mono">student_no, name, email, college, program</span>. Only <strong>student_no</strong> and <strong>name</strong> are required.</li>
          <li>In Excel: File &gt; Save As &gt; <strong>CSV UTF-8</strong>, then upload it here.</li>
        </ol>
        <div class="form-group">
          <label class="form-label" for="csvFile">CSV file <span class="required">*</span></label>
          <input type="file" name="csv_file" id="csvFile" class="form-control" accept=".csv,text/csv" required>
          <span class="text-xs text-muted">Up to 2 MB. Students already on the list are updated, not duplicated.</span>
        </div>
        <a href="../actions/masterlist_action.php?action=template" class="text-sm"><?= icon('download', '', 14) ?> Download the CSV template</a>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('upload', '', 16) ?> Import</button>
      </div>
    </form>
  </div>
</div>

<!-- Add one student -->
<div class="modal-backdrop" id="addEntryModal" onclick="if(event.target === this) closeModal('addEntryModal')">
  <div class="modal-content" role="dialog" aria-labelledby="addEntryTitle" style="max-width: 520px;">
    <div class="modal-header">
      <h3 class="card-title" id="addEntryTitle">Add Student to Masterlist</h3>
      <button type="button" class="alert-close" onclick="closeModal('addEntryModal')" aria-label="Close">&times;</button>
    </div>
    <form action="../actions/masterlist_action.php" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="mlNo">Student number <span class="required">*</span></label>
          <input type="text" name="student_no" id="mlNo" class="form-control" required pattern="[A-Za-z0-9\-]{4,50}" title="Use letters, numbers and dashes only, e.g. SNSU-2025-01234." placeholder="SNSU-2025-01234">
        </div>
        <div class="form-group">
          <label class="form-label" for="mlName">Full name <span class="required">*</span></label>
          <input type="text" name="name" id="mlName" class="form-control" required minlength="2" maxlength="150">
        </div>
        <div class="form-group">
          <label class="form-label" for="mlEmail">Email <span class="optional">(optional)</span></label>
          <input type="email" name="email" id="mlEmail" class="form-control" maxlength="150">
        </div>
        <div class="form-group">
          <label class="form-label" for="mlDept">College <span class="optional">(optional)</span></label>
          <select name="department" id="mlDept" class="form-control">
            <option value="">—</option>
            <?php foreach (SNSU_DEPARTMENTS as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" for="mlProg">Program <span class="optional">(optional)</span></label>
          <input type="text" name="program" id="mlProg" class="form-control" maxlength="150">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addEntryModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check', '', 16) ?> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit one record -->
<div class="modal-backdrop" id="editEntryModal" onclick="if(event.target === this) closeModal('editEntryModal')">
  <div class="modal-content" role="dialog" aria-labelledby="editEntryTitle" style="max-width: 560px;">
    <div class="modal-header">
      <div>
        <h3 class="card-title" id="editEntryTitle">Edit Masterlist Record</h3>
        <div class="card-subtitle">Students who register by searching their name see these details and use this email to sign in.</div>
      </div>
      <button type="button" class="alert-close" onclick="closeModal('editEntryModal')" aria-label="Close">&times;</button>
    </div>
    <form action="../actions/masterlist_action.php" method="POST" id="editEntryForm">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="masterlist_id" id="edId" value="">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="edNo">Student number <span class="required">*</span></label>
            <input type="text" name="student_no" id="edNo" class="form-control mono" required pattern="[A-Za-z0-9\-]{4,50}" title="Use letters, numbers and dashes only, e.g. SNSU-2025-01234.">
            <span class="form-help" id="edNoLocked" hidden>Locked: this student already has an account linked by this number.</span>
          </div>
          <div class="form-group">
            <label class="form-label" for="edName">Full name <span class="required">*</span></label>
            <input type="text" name="name" id="edName" class="form-control" required minlength="2" maxlength="150">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="edEmail">Email <span class="optional">(optional)</span></label>
          <input type="email" name="email" id="edEmail" class="form-control" maxlength="150" placeholder="student@gmail.com">
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="edDept">College <span class="optional">(optional)</span></label>
            <select name="department" id="edDept" class="form-control">
              <option value="">—</option>
              <?php foreach (SNSU_DEPARTMENTS as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="edProg">Program <span class="optional">(optional)</span></label>
            <input type="text" name="program" id="edProg" class="form-control" maxlength="150">
          </div>
        </div>
        <label id="edSyncWrap" class="text-sm" style="display: flex; gap: 8px; align-items: flex-start; padding: 10px 12px; border-radius: 8px; background: var(--bg-subtle); border: 1px solid var(--border-subtle); cursor: pointer;" hidden>
          <input type="checkbox" name="sync_account" value="1" id="edSync" checked style="margin-top: 3px;">
          <span><strong>Also update the student's account</strong><br><span class="text-muted text-xs">This student already registered. Apply the corrected name, email, college and program to their account too (the email is what they sign in with).</span></span>
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editEntryModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><?= icon('check', '', 16) ?> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<style>#edNoLocked[hidden], #edSyncWrap[hidden] { display: none; }</style>
<script>
  document.querySelectorAll('[data-edit-entry]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const m = JSON.parse(btn.dataset.editEntry);
      const $ = (id) => document.getElementById(id);
      $('edId').value = m.id;
      $('edNo').value = m.student_no;
      $('edNo').readOnly = m.registered;
      $('edNoLocked').hidden = !m.registered;
      $('edName').value = m.name;
      $('edEmail').value = m.email;
      $('edProg').value = m.program;
      const dept = $('edDept');
      if (m.department && ![...dept.options].some((o) => o.value === m.department)) {
        dept.add(new Option(m.department, m.department)); // keep a college typed differently in the CSV
      }
      dept.value = m.department || '';
      $('edSyncWrap').hidden = !m.registered;
      $('edSync').checked = true;
      openModal('editEntryModal');
      setTimeout(() => $('edName').focus(), 60);
    });
  });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
