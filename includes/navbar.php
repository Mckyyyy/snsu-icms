<?php
// includes/navbar.php - Top Navigation Bar (Figures 2 & 10)
$user = getCurrentUser();
$isSubdir = (strpos($_SERVER['SCRIPT_NAME'], '/student/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$rootPrefix = $isSubdir ? '../' : './';
$userName = $user ? e($user['name']) : 'Guest User';
$userRole = $user ? ucfirst(e($user['role'])) : 'Guest';
$profileUrl = ($user && $user['role'] === 'admin') ? $rootPrefix . 'admin/profile.php' : $rootPrefix . 'student/profile.php';
?>
<?php
  // The page's own heading names the page, so the top bar only names the portal
  $portalName = ($user && $user['role'] === 'admin') ? 'Administrator Portal' : 'Student Portal';
?>
<header class="top-header">
  <div class="header-context">
    <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
      <?= icon('menu', '', 20) ?>
    </button>
    <div class="header-crumbs">
      <span class="header-crumb-top"><?= e($portalName) ?></span>
    </div>
  </div>

  <div class="header-actions">
    <span class="header-date"><?= date('l, F j, Y') ?></span>
    <!-- Notification Bell (live, DB-backed: see includes/notifications.php & api/notifications.php) -->
    <?php
      if ($user) {
          runSlaMonitorIfDue($pdo); // automatic SLA escalation (throttled)
      }
      $notifItems  = $user ? getUserNotifications($pdo, $user['role'], (int)$user['id'], 8) : [];
      $notifUnread = $user ? countUnreadNotifications($pdo, $user['role'], (int)$user['id']) : 0;
      $notifStyle  = [
          'rrt'           => ['#fee2e2', '#dc2626', 'zap'],
          'new_concern'   => ['#eff6ff', '#2563eb', 'clipboard'],
          'status_change' => ['#eff6ff', '#2563eb', 'refresh'],
          'response'      => ['#d1fae5', '#059669', 'message-square'],
          'password_reset'=> ['#fef3c7', '#d97706', 'lock'],
          'announcement'  => ['#fee2e2', '#dc2626', 'megaphone'],
          'sla_breach'    => ['#fee2e2', '#dc2626', 'alert-triangle'],
          'sla_risk'      => ['#fef3c7', '#d97706', 'clock'],
          'assigned'      => ['#eef4ff', '#2563eb', 'user'],
          'withdrawn'     => ['#eef1f6', '#46536a', 'x'],
          'edited'        => ['#eef4ff', '#2563eb', 'edit'],
          'feedback_reply'=> ['#e3f7ee', '#059669', 'message-square'],
          'registration'  => ['#fff4dc', '#b45309', 'user'],
      ];
      $detailBase = $rootPrefix . ($user && $user['role'] === 'admin' ? 'admin' : 'student') . '/concern_details.php?id=';
      // Messages are reached through the floating chat button (includes/chat_widget.php), not the top bar
    ?>
    <div class="header-dropdown-wrap" data-notif-api="<?= $rootPrefix ?>api/notifications.php">
      <button type="button" class="header-icon-btn" id="notificationBtn" title="Notifications" aria-label="Notifications" onclick="toggleNotificationDropdown(event)">
        <?= icon('bell') ?>
        <span class="header-badge-count" id="notifBadgeCount" style="<?= $notifUnread ? '' : 'display:none;' ?>"><?= $notifUnread ?></span>
      </button>

      <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-header">
          <div class="flex items-center gap-2">
            <span class="font-bold">Notifications</span>
            <span class="badge-tag badge-red" id="notifUnreadBadge"><?= $notifUnread ?> New</span>
          </div>
          <button type="button" class="notif-action-link" onclick="markAllNotificationsRead(event)">Mark all read</button>
        </div>

        <div class="notification-dropdown-body">
          <?php if (!$notifItems): ?>
            <div class="empty-state" style="padding: 32px 16px;">
              <div class="empty-state-icon"><?= icon('bell', '', 22) ?></div>
              <p>You're all caught up. New updates will appear here.</p>
            </div>
          <?php endif; ?>
          <?php foreach ($notifItems as $n): [$bg, $fg, $ic] = $notifStyle[$n['type']] ?? ['#f1f5f9', '#475569', 'bell']; ?>
            <a class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>"
               href="<?= $n['concern_id'] ? e($detailBase . (int)$n['concern_id']) : (in_array($n['type'], ['password_reset', 'registration'], true) ? e($rootPrefix . 'admin/users.php') : ($n['type'] === 'announcement' ? e($rootPrefix . 'student/announcements.php') : ($n['type'] === 'feedback_reply' ? e($rootPrefix . 'student/feedback.php') : '#'))) ?>"
               data-notif-id="<?= (int)$n['notification_id'] ?>"
               style="text-decoration: none; color: inherit;">
              <div class="notification-icon-wrap" style="background: <?= $bg ?>; color: <?= $fg ?>;">
                <?= icon($ic, '', 16) ?>
              </div>
              <div class="notification-text">
                <div class="notification-title"><?= e($n['title']) ?></div>
                <div class="notification-desc"><?= e($n['message']) ?></div>
                <div class="notification-time"><?= icon('clock', '', 12) ?> <?= e(timeAgo($n['created_at'])) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="notification-dropdown-footer">
          <a href="<?= $rootPrefix . ($user && $user['role'] === 'admin' ? 'admin' : 'student') ?>/announcements.php" class="card-link" style="justify-content: center; width: 100%;">
            <span>View All Announcements</span> <?= icon('arrow-right', '', 14) ?>
          </a>
        </div>
      </div>
    </div>

    <!-- User Profile Pill (Figures 2 & 10) -->
    <a href="<?= $profileUrl ?>" class="header-user-btn" title="View Profile">
      <?php $navPhotoType = ($user && $user['role'] === 'admin') ? 'admin' : 'student'; ?>
      <?= avatarHtml($navPhotoType, (int)($user['id'] ?? 0), $user['name'] ?? 'U', $user ? profilePhotoVersion($pdo, $navPhotoType, (int)$user['id']) : null, 'header-avatar') ?>
      <div class="header-user-info">
        <span class="header-user-name"><?= $userName ?></span>
        <span class="header-user-role"><?= ($user && $user['role'] === 'admin') ? e($user['program'] ?: 'Administrator') : e($user['student_no'] ?? 'Student') ?></span>
      </div>
    </a>
  </div>
</header>
<div class="app-container">
