<?php
// includes/sidebar.php - App sidebar (brand, role-based navigation, user card)
$user = getCurrentUser();
$navRole = $user['role'] ?? 'student';
$currentScript = basename($_SERVER['SCRIPT_NAME']);
// Editing an existing concern belongs under "My Concerns", not "Submit Concern"
if ($currentScript === 'submit_concern.php' && isset($_GET['id'])) {
    $currentScript = 'concern_details.php';
}
$isSubdir = (strpos($_SERVER['SCRIPT_NAME'], '/student/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$rootPrefix = $isSubdir ? '../' : './';

// Live counters shown next to menu items
$navCounts = [];
if ($navRole === 'admin') {
    $navCounts['concerns.php'] = (int)$pdo->query("SELECT COUNT(*) FROM concerns WHERE status_id = 1")->fetchColumn();
    $navCounts['users.php'] = (int)$pdo->query("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'Pending'")->fetchColumn()
        + (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Pending'")->fetchColumn();
    $navMine = $pdo->prepare("SELECT COUNT(*) FROM concerns WHERE assigned_admin_id = :id AND status_id IN (1, 2)");
    $navMine->execute(['id' => $user['id']]);
    $navCounts['dashboard.php'] = (int)$navMine->fetchColumn();
} else {
    $navStmt = $pdo->prepare("SELECT COUNT(*) FROM concerns WHERE student_id = :id AND status_id IN (1, 2)");
    $navStmt->execute(["id" => $user["id"]]);
    $navCounts['my_concerns.php'] = (int)$navStmt->fetchColumn();
}

$menu = $navRole === 'admin'
    ? [
        'Overview' => [
            ['admin/dashboard.php', 'dashboard', 'Dashboard', ['dashboard.php']],
            ['admin/reports.php', 'bar-chart', 'Reports & Analytics', ['reports.php']],
        ],
        'Management' => [
            ['admin/concerns.php', 'file-text', 'Manage Concerns', ['concerns.php', 'concern_details.php']],
            ['admin/users.php', 'users', 'Users', ['users.php']],
            ['admin/masterlist.php', 'file-text', 'Student Masterlist', ['masterlist.php']],
            ['admin/announcements.php', 'megaphone', 'Announcements', ['announcements.php']],
            ['admin/feedback.php', 'star', 'Feedback', ['feedback.php']],
            ['admin/categories.php', 'folder', 'Categories', ['categories.php']],
            ['admin/activity.php', 'clipboard', 'Activity Log', ['activity.php']],
        ],
        'Account' => [
            ['admin/profile.php', 'user', 'My Profile', ['profile.php']],
        ],
    ]
    : [
        'Overview' => [
            ['student/dashboard.php', 'dashboard', 'Dashboard', ['dashboard.php']],
        ],
        'Concerns' => [
            ['student/submit_concern.php', 'edit', 'Submit Concern', ['submit_concern.php']],
            ['student/my_concerns.php', 'clipboard', 'My Concerns', ['my_concerns.php', 'concern_details.php']],
        ],
        'Campus' => [
            ['student/announcements.php', 'megaphone', 'Announcements', ['announcements.php']],
            ['student/feedback.php', 'message-square', 'Feedback', ['feedback.php']],
        ],
        'Account' => [
            ['student/profile.php', 'user', 'My Profile', ['profile.php']],
        ],
    ];
$homeUrl = $rootPrefix . ($navRole === 'admin' ? 'admin' : 'student') . '/dashboard.php';
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
  <a href="<?= $homeUrl ?>" class="sidebar-brand">
    <img class="brand-emblem brand-logo" src="<?= $rootPrefix ?>assets/img/snsu-logo-96.png" width="44" height="44"
         alt="Surigao del Norte State University seal" decoding="async">
    <div class="brand-text">
      <span class="brand-title">SNSU Concerns</span>
      <span class="brand-subtitle">AI Prioritization &amp; Tracking</span>
    </div>
  </a>

  <nav class="sidebar-nav">
    <?php foreach ($menu as $section => $items): ?>
      <div class="sidebar-section-label"><?= e($section) ?></div>
      <ul class="sidebar-menu">
        <?php foreach ($items as [$href, $ic, $label, $activeOn]):
          $isActive = in_array($currentScript, $activeOn, true);
          $count = $navCounts[basename($href)] ?? 0;
        ?>
          <li>
            <a href="<?= $rootPrefix . $href ?>" class="sidebar-link <?= $isActive ? 'active' : '' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
              <span class="sidebar-icon"><?= icon($ic, '', 18) ?></span>
              <span><?= e($label) ?></span>
              <?php if ($count > 0): ?><span class="sidebar-count" title="<?= $count ?> needing attention"><?= $count ?></span><?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <?= avatarHtml($navRole === 'admin' ? 'admin' : 'student', (int)($user['id'] ?? 0), $user['name'] ?? 'U', $user ? profilePhotoVersion($pdo, $navRole === 'admin' ? 'admin' : 'student', (int)$user['id']) : null, 'avatar' . ($navRole === 'admin' ? ' avatar-admin' : '')) ?>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= e($user['name'] ?? '') ?></div>
        <div class="sidebar-user-role"><?= $navRole === 'admin' ? e($user['program'] ?: 'Administrator') : e($user['email'] ?? '') ?></div>
      </div>
      <button type="button" class="sidebar-logout" onclick="openModal('logoutModal')" title="Log out" aria-label="Log out">
        <?= icon('log-out', '', 18) ?>
      </button>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="app-main">
  <div class="main-content-container">
    <?php renderFlash(); ?>
