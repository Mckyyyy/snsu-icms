<?php
// student/profile.php - Student Profile View & Edit (Figure 8 of Prototype)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();
$studentId = $user['id'];

// Fetch latest student record
$stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = :id");
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: dashboard.php');
    exit;
}

$statsStmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status_id = 3) AS resolved FROM concerns WHERE student_id = :id");
$statsStmt->execute(['id' => $studentId]);
$stats = $statsStmt->fetch();

$pageTitle = "My Profile | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Account</div>
    <h1 class="page-title">My Profile</h1>
    <p class="page-desc">Your account details and contact information.</p>
  </div>
</div>

<div class="grid-main-side grid-side-first">
  <div>
    <div class="card">
      <div class="profile-cover"></div>
      <div class="profile-head">
        <?php $photoType = 'student'; $photoOwnerId = $studentId; $photoName = $student['name']; $photoClass = 'avatar avatar-xl'; require __DIR__ . '/../includes/profile_photo_form.php'; ?>
        <div class="profile-name"><?= e($student['name']) ?></div>
        <div class="profile-role"><?= e($student['student_no']) ?></div>
        <div style="margin-top: 8px;"><span class="badge-tag badge-dot <?= $student['status'] === 'Active' ? 'badge-green' : 'badge-gray' ?>"><?= e($student['status']) ?></span></div>
      </div>
      <div class="card-body" style="padding-top: 0;">
        <div class="kv-list">
          <div class="kv-row"><span class="kv-key">Program</span><span class="kv-val"><?= e($student['program']) ?></span></div>
          <div class="kv-row"><span class="kv-key">College</span><span class="kv-val"><?= e($student['department']) ?></span></div>
          <div class="kv-row"><span class="kv-key">Member since</span><span class="kv-val"><?= formatDate($student['created_at']) ?></span></div>
          <div class="kv-row"><span class="kv-key">Concerns filed</span><span class="kv-val"><?= (int)$stats['total'] ?></span></div>
          <div class="kv-row"><span class="kv-key">Resolved</span><span class="kv-val"><?= (int)$stats['resolved'] ?></span></div>
        </div>
      </div>
    </div>
  </div>

  <div>
    <form action="../actions/profile_action.php" method="POST" class="card">
      <?= csrfField() ?>
      <div class="card-header">
        <div>
          <h2 class="card-title">Personal Information</h2>
          <div class="card-subtitle">Keep your contact details up to date so the office can reach you.</div>
        </div>
      </div>
      <div class="card-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="profileName">Full name <span class="required">*</span></label>
            <input type="text" name="name" id="profileName" class="form-control" value="<?= e($student['name']) ?>" required maxlength="150">
          </div>
          <div class="form-group">
            <label class="form-label" for="profilePhone">Mobile number</label>
            <div class="input-icon-wrap">
              <span class="field-icon"><?= icon('phone', '', 16) ?></span>
              <input type="tel" name="phone" id="profilePhone" class="form-control" value="<?= e($student['phone'] ?? '') ?>" placeholder="09XX XXX XXXX" maxlength="50">
            </div>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="profileEmail">Email address</label>
            <div class="input-icon-wrap">
              <span class="field-icon"><?= icon('mail', '', 16) ?></span>
              <input type="email" id="profileEmail" class="form-control" value="<?= e($student['email']) ?>" readonly>
            </div>
            <span class="form-help">Managed by the registrar. Contact the MIS Office to change it.</span>
          </div>
          <div class="form-group">
            <label class="form-label" for="profileNo">Student number</label>
            <input type="text" id="profileNo" class="form-control" value="<?= e($student['student_no']) ?>" readonly>
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

    <?php $hasCode = !empty($student['recovery_code_hash']); ?>
    <div class="card" id="recovery" style="scroll-margin-top: 80px;">
      <div class="card-header">
        <div>
          <h2 class="card-title"><?= icon('shield', '', 16) ?> Recovery Code</h2>
          <div class="card-subtitle">Lets you reset a forgotten password yourself, with no email or administrator needed.</div>
        </div>
        <?php if ($hasCode): ?>
          <span class="badge-tag badge-green badge-dot">Set <?= e(formatDate($student['recovery_code_set_at'])) ?></span>
        <?php else: ?>
          <span class="badge-tag badge-amber badge-dot">Not set up</span>
        <?php endif; ?>
      </div>
      <form action="../actions/recovery_code_action.php" method="POST" class="card-body">
        <?= csrfField() ?>
        <p class="text-sm text-secondary" style="margin: 0 0 12px;">
          <?= $hasCode
              ? 'Lost the code you saved? Make a new one. The old code stops working.'
              : 'You do not have a recovery code yet. Make one now and keep it somewhere safe.' ?>
        </p>
        <div class="flex gap-2 items-end" style="flex-wrap: wrap;">
          <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
            <label class="form-label" for="rcCurrent">Current password <span class="required">*</span></label>
            <input type="password" name="current_password" id="rcCurrent" class="form-control" required autocomplete="current-password">
          </div>
          <button type="submit" class="btn <?= $hasCode ? 'btn-secondary' : 'btn-primary' ?>"><?= icon('shield', '', 16) ?> <?= $hasCode ? 'Make a New Code' : 'Create Recovery Code' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
