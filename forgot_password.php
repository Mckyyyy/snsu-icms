<?php
// forgot_password.php - Reset a forgotten password
//   1. Recovery code (students): student number/email + the code saved at registration -> new password at once
//   2. Email link when the server can really send mail; otherwise the request goes to an administrator
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_check.php';
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
$tab = ($_GET['tab'] ?? 'code') === 'request' ? 'request' : 'code';
$oldIdentifier = (string)($_SESSION['recovery_old_identifier'] ?? '');
unset($_SESSION['recovery_old_identifier']);
$emailMode = mailCanDeliver();

$pageTitle      = 'Forgot Password | ICMS-DPT-RRT';
$authHeading    = 'Forgot Password';
$authSubheading = 'Choose how you want to reset it';
require __DIR__ . '/includes/auth_layout_start.php';
?>
    <style>
      .fp-tabs { display: flex; width: 100%; margin-bottom: 16px; }
      .fp-tabs a { flex: 1; text-align: center; }
      .fp-note { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; border-radius: var(--radius-md); padding: 12px 14px; font-size: 0.825rem; margin-bottom: 16px; line-height: 1.5; }
      .fp-pane[hidden] { display: none; }
    </style>

    <div class="segmented fp-tabs" role="tablist">
      <a href="?tab=code" class="<?= $tab === 'code' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'code' ? 'true' : 'false' ?>">I have my recovery code</a>
      <a href="?tab=request" class="<?= $tab === 'request' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'request' ? 'true' : 'false' ?>"><?= $emailMode ? 'Email me a link' : 'Ask the administrator' ?></a>
    </div>

    <!-- 1. Recovery code -->
    <form action="actions/password_reset_action.php" method="POST" class="fp-pane" <?= $tab === 'code' ? '' : 'hidden' ?> id="recoveryForm" novalidate>
      <?= csrfField() ?>
      <input type="hidden" name="action" value="recovery">
      <div class="fp-note">
        Use the <strong>recovery code</strong> you saved when you registered (it looks like <span class="mono">ABCD-2345-EFGH</span>).
        You can set a new password right now, with no email or administrator needed. You will then get a new code.
      </div>
      <div class="form-group">
        <label class="form-label" for="rcIdentifier">Student number or email <span class="required">*</span></label>
        <input type="text" name="identifier" id="rcIdentifier" class="form-control" value="<?= e($oldIdentifier) ?>"
               placeholder="SNSU-2025-01234" required maxlength="150" autocomplete="username" <?= $tab === 'code' ? 'autofocus' : '' ?>>
      </div>
      <div class="form-group">
        <label class="form-label" for="rcCode">Recovery code <span class="required">*</span></label>
        <input type="text" name="recovery_code" id="rcCode" class="form-control mono" required maxlength="20"
               placeholder="ABCD-2345-EFGH" autocomplete="off" autocapitalize="characters" spellcheck="false" style="letter-spacing: 0.06em;">
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label class="form-label" for="rcPassword">New password <span class="required">*</span></label>
          <input type="password" name="password" id="rcPassword" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password" data-strength>
        </div>
        <div class="form-group">
          <label class="form-label" for="rcConfirm">Confirm password <span class="required">*</span></label>
          <input type="password" name="password_confirm" id="rcConfirm" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password" data-match="#rcPassword">
        </div>
      </div>
      <span class="form-help" style="margin: -8px 0 14px;">At least 8 characters with a letter and a number.</span>
      <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Reset My Password</button>
      <div class="auth-links">
        Lost your code? <a href="?tab=request"><?= $emailMode ? 'Get a link by email' : 'Ask the administrator' ?></a> · <a href="index.php">Back to sign in</a>
      </div>
    </form>

    <!-- 2. Email link, or administrator-assisted when the server cannot send email -->
    <form action="actions/password_reset_action.php" method="POST" class="fp-pane" <?= $tab === 'request' ? '' : 'hidden' ?>>
      <?= csrfField() ?>
      <input type="hidden" name="action" value="request">
      <div class="fp-note">
        <?php if ($emailMode): ?>
          Enter your registered email or student number. We will email you a <strong>one-time link</strong> to choose a
          new password. The link expires in 60 minutes.
        <?php else: ?>
          Enter your registered email or student number. The administrator will be notified and will issue you a
          <strong>temporary password</strong> (in person or through your official contact). You will be asked to set a new
          password the first time you sign in with it.
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label class="form-label" for="resetIdentifier">Email or Student Number</label>
        <div class="input-icon-wrap">
          <span class="field-icon"><?= icon('user', '', 16) ?></span>
          <input type="text" name="identifier" id="resetIdentifier" class="form-control" placeholder="you@gmail.com or SNSU-2025-01234" required maxlength="150" <?= $tab === 'request' ? 'autofocus' : '' ?>>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
        <span><?= $emailMode ? 'Send Reset Link' : 'Send Reset Request' ?></span>
      </button>
      <div class="auth-links">
        Remembered it? <a href="index.php">Back to sign in</a>
      </div>
    </form>

    <script>
      // Client-side check mirrors the server policy (server remains authoritative)
      document.getElementById('recoveryForm').addEventListener('submit', function (e) {
        const pwd = document.getElementById('rcPassword').value;
        let msg = '';
        if (!document.getElementById('rcIdentifier').value.trim() || document.getElementById('rcCode').value.replace(/[^A-Za-z0-9]/g, '').length !== 12) msg = 'Enter your student number and the 12-character recovery code.';
        else if (pwd.length < 8 || !/[A-Za-z]/.test(pwd) || !/\d/.test(pwd)) msg = 'Password must be at least 8 characters with a letter and a number.';
        else if (pwd !== document.getElementById('rcConfirm').value) msg = 'Passwords do not match.';
        if (msg) {
          e.preventDefault();
          showToast(msg, 'error', 'Reset Password');
        }
      });
    </script>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>
