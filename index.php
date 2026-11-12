<?php
// index.php - Login Page (Figure 1 of Prototype)
// The login form has Student / Administrator tabs. The tab tailors the form; the server
// still detects the account type, so a sign-in on the wrong tab opens the right portal.
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_check.php';

if (isLoggedIn()) {
    $target = ($_SESSION['role'] === 'admin') ? 'admin/dashboard.php' : 'student/dashboard.php';
    header("Location: {$target}");
    exit;
}

// ?as=admin opens the Administrator tab directly (e.g. a bookmark for staff)
$initialTab = ($_GET['as'] ?? '') === 'admin' ? 'admin' : 'student';
$isAdminTab = $initialTab === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | AI-Powered Institutional Concern Management System (ICMS-DPT-RRT)</title>
  <?php require __DIR__ . '/includes/head_assets.php'; ?>
  <link rel="stylesheet" href="assets/css/variables.css<?= assetVersion('css/variables.css') ?>">
  <link rel="stylesheet" href="assets/css/base.css<?= assetVersion('css/base.css') ?>">
  <link rel="stylesheet" href="assets/css/components.css<?= assetVersion('css/components.css') ?>">
  <link rel="stylesheet" href="assets/css/auth.css<?= assetVersion('css/auth.css') ?>">
</head>
<body>

<div class="login-container">
  <!-- Left Brand Hero (Figure 1) -->
  <div class="login-hero">
    <div class="hero-top">
      <div class="hero-icon-bubble">
        <?= icon('message-square', '', 38) ?>
      </div>
      <h1 class="hero-title">AI-Powered Institutional Concern Management System</h1>
      <p class="hero-subtitle">Dynamic Prioritization &amp; Resolution Tracking</p>

      <div class="hero-badges">
        <span class="hero-badge">AI Urgency Engine</span>
        <span class="hero-badge">SLA Tracking</span>
        <span class="hero-badge">Rapid Response</span>
      </div>
    </div>

    <div class="hero-footer">
      "Your voice matters. We're here to listen and act."
      <div style="font-size: 0.725rem; opacity: 0.8; margin-top: 4px;">Surigao del Norte State University (SNSU)</div>
    </div>
  </div>

  <!-- Right Login Form (Figure 1) -->
  <div class="login-form-pane">
    <div class="form-header">
      <h2>Welcome Back!</h2>
      <p>Choose your portal and sign in to continue</p>
    </div>

    <?php renderFlash(); ?>

    <div class="login-tabbox" id="loginTabbox" data-active="<?= $initialTab ?>">
      <div class="login-tabs" role="tablist" aria-label="Choose your portal">
        <button type="button" class="login-tab" role="tab" data-tab="student" id="tabStudent"
                aria-controls="loginForm" aria-selected="<?= $isAdminTab ? 'false' : 'true' ?>">
          <?= icon('user', '', 16) ?> Student
        </button>
        <button type="button" class="login-tab" role="tab" data-tab="admin" id="tabAdmin"
                aria-controls="loginForm" aria-selected="<?= $isAdminTab ? 'true' : 'false' ?>">
          <?= icon('shield', '', 16) ?> Administrator
        </button>
      </div>

      <form action="actions/auth_action.php" method="POST" id="loginForm" class="login-tabpanel" role="tabpanel"
            aria-labelledby="<?= $isAdminTab ? 'tabAdmin' : 'tabStudent' ?>">
        <?= csrfField() ?>
        <input type="hidden" name="role" id="loginRole" value="<?= $initialTab ?>">

        <div class="form-group">
          <label class="form-label" for="identifier" id="identifierLabel"><?= $isAdminTab ? 'Email Address' : 'Student ID No. or Email' ?></label>
          <div class="input-icon-wrap">
            <span class="field-icon" id="identifierIcon"><?= icon($isAdminTab ? 'mail' : 'user', '', 16) ?></span>
            <input type="text" name="identifier" id="identifier" class="form-control" required autofocus autocomplete="username"
                   placeholder="<?= $isAdminTab ? 'name@snsu.edu.ph' : 'SNSU-2025-01234 or you@snsu.edu.ph' ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-icon-wrap" style="position: relative;">
            <span class="field-icon"><?= icon('lock', '', 16) ?></span>
            <input type="password" name="password" id="password" class="form-control" style="padding-right: 42px;" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" id="togglePasswordBtn" class="login-pw-toggle" title="Show/Hide Password" aria-label="Show password">
              <?= icon('eye', '', 16) ?>
            </button>
          </div>
        </div>

        <div class="login-options">
          <a href="forgot_password.php" style="color: #64748b;">Forgot Password?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;" id="submitBtn">
          <span id="submitLabel"><?= icon('arrow-right', '', 16) ?> <?= $isAdminTab ? 'Sign in as Administrator' : 'Sign in as Student' ?></span>
        </button>
      </form>
    </div>

    <div class="auth-links" id="registerLink" <?= $isAdminTab ? 'hidden' : '' ?>>
      Don't have an account? <a href="register.php">Create a student account</a>
    </div>
    <div class="auth-links" id="adminNote" <?= $isAdminTab ? '' : 'hidden' ?>>
      Staff accounts are created by the system administrator.
    </div>
  </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="assets/js/main.js<?= assetVersion('js/main.js') ?>"></script>
<script>
  (function () {
    const box = document.getElementById('loginTabbox');
    const tabs = box.querySelectorAll('.login-tab');
    const input = document.getElementById('identifier');
    const icons = { student: <?= json_encode(icon('user', '', 16)) ?>, admin: <?= json_encode(icon('mail', '', 16)) ?> };
    const arrow = <?= json_encode(icon('arrow-right', '', 16)) ?>;
    const copy = {
      student: { label: 'Student ID No. or Email', placeholder: 'SNSU-2025-01234 or you@snsu.edu.ph', button: 'Sign in as Student' },
      admin:   { label: 'Email Address', placeholder: 'name@snsu.edu.ph', button: 'Sign in as Administrator' },
    };

    function selectTab(tab, focus) {
      box.dataset.active = tab;
      document.getElementById('loginRole').value = tab;
      document.getElementById('identifierLabel').textContent = copy[tab].label;
      document.getElementById('identifierIcon').innerHTML = icons[tab];
      document.getElementById('submitLabel').innerHTML = arrow + ' ' + copy[tab].button;
      input.placeholder = copy[tab].placeholder;
      tabs.forEach((t) => t.setAttribute('aria-selected', t.dataset.tab === tab ? 'true' : 'false'));
      document.getElementById('loginForm').setAttribute('aria-labelledby', tab === 'admin' ? 'tabAdmin' : 'tabStudent');
      document.getElementById('registerLink').hidden = tab !== 'student';
      document.getElementById('adminNote').hidden = tab !== 'admin';
      try { localStorage.setItem('icmsLoginTab', tab); } catch (e) {}
      if (focus) input.focus();
    }

    tabs.forEach((t) => t.addEventListener('click', () => selectTab(t.dataset.tab, true)));
    box.querySelector('.login-tabs').addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      const next = box.dataset.active === 'student' ? 'admin' : 'student';
      selectTab(next, false);
      box.querySelector('.login-tab[data-tab="' + next + '"]').focus();
    });

    // Remember the last tab used on this device (unless the URL chose one)
    if (!new URLSearchParams(location.search).has('as')) {
      try {
        const saved = localStorage.getItem('icmsLoginTab');
        if (saved === 'admin' || saved === 'student') selectTab(saved, false);
      } catch (e) {}
    }

    // Show / hide password
    const pwd = document.getElementById('password');
    const toggle = document.getElementById('togglePasswordBtn');
    const eye = <?= json_encode(icon('eye', '', 16)) ?>;
    const eyeOff = <?= json_encode(icon('eye-off', '', 16)) ?>;
    toggle.addEventListener('click', () => {
      const show = pwd.type === 'password';
      pwd.type = show ? 'text' : 'password';
      toggle.innerHTML = show ? eyeOff : eye;
      toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    // Prevent double submission
    document.getElementById('loginForm').addEventListener('submit', () => {
      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Signing in...';
    });
  })();
</script>

</body>
</html>
