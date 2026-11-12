<?php
// reset_password.php - Choose a new password from an emailed one-time link
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_check.php';

$token = (string)($_GET['token'] ?? '');
$valid = false;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = $pdo->prepare("SELECT 1 FROM password_reset_tokens WHERE token_hash = :h AND used_at IS NULL AND expires_at > NOW()");
    $stmt->execute(['h' => hash('sha256', $token)]);
    $valid = (bool)$stmt->fetchColumn();
}

$pageTitle      = 'Reset Password | ICMS-DPT-RRT';
$authHeading    = 'Choose a New Password';
$authSubheading = $valid ? 'Enter a new password for your account' : 'This link cannot be used';
require __DIR__ . '/includes/auth_layout_start.php';
?>
    <?php if (!$valid): ?>
      <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: var(--radius-md); padding: 12px 14px; font-size: 0.85rem; margin-bottom: 18px;">
        This password reset link is invalid, already used, or expired.
      </div>
      <a href="forgot_password.php" class="btn btn-primary btn-lg" style="width: 100%;"><span>Request a new link</span></a>
    <?php else: ?>
      <form action="actions/password_reset_action.php" method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="complete_link">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-group">
          <label class="form-label" for="rpNew">New Password</label>
          <input type="password" name="new_password" id="rpNew" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password" autofocus data-strength>
        </div>
        <div class="form-group">
          <label class="form-label" for="rpConfirm">Confirm New Password</label>
          <input type="password" name="new_password_confirm" id="rpConfirm" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password" data-match="#rpNew">
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;"><span>Save New Password</span></button>
      </form>
    <?php endif; ?>
    <div class="auth-links"><a href="index.php">Back to sign in</a></div>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>
