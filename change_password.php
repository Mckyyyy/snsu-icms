<?php
// change_password.php - Set a new password (required after an admin-issued temporary password)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_check.php';

requireAuth();
$user   = getCurrentUser();
$forced = !empty($_SESSION['must_change_password']);

$pageTitle      = 'Change Password | ICMS-DPT-RRT';
$authHeading    = $forced ? 'Set a New Password' : 'Change Password';
$authSubheading = $forced
    ? 'You signed in with a temporary password. Choose a new one to continue.'
    : 'Update the password for ' . $user['email'];
require __DIR__ . '/includes/auth_layout_start.php';
?>
    <form action="actions/change_password_action.php" method="POST" id="changePwdForm">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label" for="cpCurrent"><?= $forced ? 'Temporary Password' : 'Current Password' ?></label>
        <input type="password" name="current_password" id="cpCurrent" class="form-control" required autocomplete="current-password" autofocus>
      </div>
      <div class="form-group">
        <label class="form-label" for="cpNew">New Password</label>
        <input type="password" name="new_password" id="cpNew" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password" data-strength>
      </div>
      <div class="form-group">
        <label class="form-label" for="cpConfirm">Confirm New Password</label>
        <input type="password" name="new_password_confirm" id="cpConfirm" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password" data-match="#cpNew">
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
        <span>Save New Password</span>
      </button>
    </form>

    <div class="auth-links">
      <?php if (!$forced): ?>
        <a href="<?= $user['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php' ?>">Back to dashboard</a> ·
      <?php endif; ?>
      <form action="logout.php" method="POST" style="display: inline;">
        <?= csrfField() ?>
        <button type="submit" style="background: none; border: 0; color: var(--brand-blue); font-weight: 600; cursor: pointer; font-size: 0.8rem;">Sign out</button>
      </form>
    </div>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>
