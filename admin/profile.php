<?php
// admin/profile.php - Administrator Profile Management (Figure 14 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$user = getCurrentUser();
$adminId = $user['id'];

// Fetch latest admin record
$stmt = $pdo->prepare("SELECT * FROM administrators WHERE admin_id = :id");
$stmt->execute(['id' => $adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    header('Location: dashboard.php');
    exit;
}

$actStmt = $pdo->prepare("
    SELECT (SELECT COUNT(*) FROM responses WHERE admin_id = :a1) AS responses,
           (SELECT COUNT(DISTINCT concern_id) FROM responses WHERE admin_id = :a2 AND status_id = 3) AS resolved,
           (SELECT COUNT(*) FROM announcements WHERE admin_id = :a3) AS announcements
");
$actStmt->execute(['a1' => $adminId, 'a2' => $adminId, 'a3' => $adminId]);
$activity = $actStmt->fetch();

$pageTitle = "Admin Profile | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Account</div>
    <h1 class="page-title">My Profile</h1>
    <p class="page-desc">Your administrator account and activity.</p>
  </div>
</div>

<div class="grid-main-side grid-side-first">
  <div>
    <div class="card">
      <div class="profile-cover"></div>
      <div class="profile-head">
        <?php $photoType = 'admin'; $photoOwnerId = $adminId; $photoName = $admin['name']; $photoClass = 'avatar avatar-xl avatar-admin'; require __DIR__ . '/../includes/profile_photo_form.php'; ?>
        <div class="profile-name"><?= e($admin['name']) ?></div>
        <div class="profile-role"><?= e($admin['role']) ?></div>
        <div style="margin-top: 8px;"><span class="badge-tag badge-purple">Administrator</span></div>
      </div>
      <div class="card-body" style="padding-top: 0;">
        <div class="kv-list">
          <div class="kv-row"><span class="kv-key">Department</span><span class="kv-val"><?= e($admin['department']) ?></span></div>
          <div class="kv-row"><span class="kv-key">Member since</span><span class="kv-val"><?= formatDate($admin['created_at']) ?></span></div>
          <div class="kv-row"><span class="kv-key">Responses posted</span><span class="kv-val"><?= (int)$activity['responses'] ?></span></div>
          <div class="kv-row"><span class="kv-key">Tickets resolved</span><span class="kv-val"><?= (int)$activity['resolved'] ?></span></div>
          <div class="kv-row"><span class="kv-key">Announcements</span><span class="kv-val"><?= (int)$activity['announcements'] ?></span></div>
        </div>
      </div>
    </div>
  </div>

  <div>
    <form action="../actions/profile_action.php" method="POST" class="card">
      <?= csrfField() ?>
      <div class="card-header">
        <div>
          <h2 class="card-title">Account Information</h2>
          <div class="card-subtitle">Your name appears on every response students see.</div>
        </div>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label" for="adminName">Full name <span class="required">*</span></label>
          <input type="text" name="name" id="adminName" class="form-control" value="<?= e($admin['name']) ?>" required maxlength="150">
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="adminEmail">Email address</label>
            <div class="input-icon-wrap">
              <span class="field-icon"><?= icon('mail', '', 16) ?></span>
              <input type="email" id="adminEmail" class="form-control" value="<?= e($admin['email']) ?>" readonly>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="adminDept">Department / assignment</label>
            <input type="text" id="adminDept" class="form-control" value="<?= e($admin['department']) ?>" readonly>
          </div>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= icon('check', '', 16) ?> Save Changes</button>
      </div>
    </form>

    <div class="card">
      <div class="card-body flex items-center justify-between gap-4" style="flex-wrap: wrap;">
        <div class="flex gap-3">
          <div class="metric-icon-wrap metric-icon-total" style="width: 40px; height: 40px;"><?= icon('lock', '', 18) ?></div>
          <div>
            <div class="font-bold">Password</div>
            <div class="text-sm text-muted">For security, changing your password requires your current password.</div>
          </div>
        </div>
        <a href="../change_password.php" class="btn btn-secondary">Change Password</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
