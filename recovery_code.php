<?php
// recovery_code.php - Shows a student's new recovery code exactly once (after registering, after
// using the old code, or after generating a new one on the Profile page).
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_check.php';

header('Cache-Control: no-store');

$show = $_SESSION['recovery_code_display'] ?? null;
unset($_SESSION['recovery_code_display']); // shown once; a refresh does not show it again
$next = $show['next'] ?? (isLoggedIn() ? 'student/profile.php' : 'index.php');
if (!preg_match('#^(index\.php|student/[a-z_]+\.php)$#', $next)) {
    $next = 'index.php';
}

$pageTitle      = 'Your Recovery Code | ICMS-DPT-RRT';
$authHeading    = $show ? 'Save Your Recovery Code' : 'Recovery Code';
$authSubheading = $show ? 'You will need it if you ever forget your password' : '';
require __DIR__ . '/includes/auth_layout_start.php';
?>
    <style>
      .rc-box { text-align: center; padding: 18px 12px; border: 2px dashed var(--brand-blue); border-radius: 12px; background: var(--brand-blue-light, #eef4ff); margin: 14px 0; }
      .rc-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 1.7rem; font-weight: 800; letter-spacing: 0.08em; color: var(--text-primary); user-select: all; }
      .rc-actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: 12px; }
      .rc-list { margin: 0 0 14px 18px; font-size: 0.84rem; color: var(--text-secondary); display: flex; flex-direction: column; gap: 4px; }
      @media print {
        body { background: #fff !important; padding: 0 !important; }
        .login-hero, .rc-actions, .rc-confirm, .rc-continue, .rc-noprint { display: none !important; }
        .login-container { box-shadow: none !important; }
      }
    </style>

<?php if (!$show): ?>
    <p class="text-sm text-secondary">This code was already shown and cannot be displayed again. If you did not save it,
      sign in and generate a new one on your <strong>Profile</strong> page.</p>
    <a href="<?= e($next) ?>" class="btn btn-primary btn-lg" style="width: 100%;">Continue</a>
<?php else: ?>
    <p class="text-sm text-secondary" style="margin: 0;"><?= e($show['context']) ?></p>

    <div class="rc-box">
      <div class="text-xs text-muted" style="margin-bottom: 6px;">Recovery code for <?= e($show['name']) ?></div>
      <div class="rc-code" id="rcCode" data-code="<?= e($show['code']) ?>"><?= e($show['code']) ?></div>
      <div class="rc-actions">
        <button type="button" class="btn btn-secondary btn-sm" id="rcCopy"><?= icon('file-text', '', 14) ?> Copy</button>
        <button type="button" class="btn btn-secondary btn-sm" id="rcDownload"><?= icon('download', '', 14) ?> Download</button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()"><?= icon('printer', '', 14) ?> Print</button>
      </div>
    </div>

    <ul class="rc-list">
      <li>Write it down, take a photo, or keep the downloaded file somewhere safe.</li>
      <li>On <strong>Forgot Password</strong>, enter your student number and this code to set a new password, with no email or administrator needed.</li>
      <li>It works <strong>once</strong>. After you use it you get a new code. Do not share it.</li>
      <li>This is the only time it is shown.</li>
    </ul>

    <label class="rc-confirm text-sm" style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px; cursor: pointer;">
      <input type="checkbox" id="rcSaved"> I have saved my recovery code
    </label>
    <a href="<?= e($next) ?>" class="btn btn-primary btn-lg rc-continue" id="rcContinue" style="width: 100%; pointer-events: none; opacity: 0.5;" aria-disabled="true">Continue</a>

    <script>
      (function () {
        const code = document.getElementById('rcCode').dataset.code;
        const name = <?= json_encode($show['name']) ?>;
        document.getElementById('rcCopy').addEventListener('click', () => {
          const done = () => showToast('Recovery code copied.', 'success');
          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code).then(done, () => window.prompt('Copy your recovery code:', code));
          } else {
            window.prompt('Copy your recovery code:', code);
          }
        });
        document.getElementById('rcDownload').addEventListener('click', () => {
          const text = 'ICMS-DPT-RRT recovery code\n\nName: ' + name + '\nCode: ' + code +
            '\n\nUse it on the Forgot Password page with your student number to set a new password.\nIt works once; you will receive a new code after using it.\n';
          const a = document.createElement('a');
          a.href = URL.createObjectURL(new Blob([text], { type: 'text/plain' }));
          a.download = 'icms-recovery-code.txt';
          document.body.appendChild(a);
          a.click();
          a.remove();
        });
        const box = document.getElementById('rcSaved');
        const go = document.getElementById('rcContinue');
        box.addEventListener('change', () => {
          go.style.pointerEvents = box.checked ? '' : 'none';
          go.style.opacity = box.checked ? '' : '0.5';
          go.setAttribute('aria-disabled', box.checked ? 'false' : 'true');
        });
      })();
    </script>
<?php endif; ?>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>
