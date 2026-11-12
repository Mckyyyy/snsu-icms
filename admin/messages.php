<?php
// admin/messages.php - Real-time conversations with students (shared admin inbox)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

$pageTitle = "Messages | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header chat-page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Student support</div>
    <h1 class="page-title">Messages</h1>
    <p class="page-desc">Chat with students in real time. Every administrator shares this inbox.</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" data-chat-new><?= icon('edit', '', 16) ?> New message</button>
  </div>
</div>

<?php require __DIR__ . '/../includes/chat_ui.php'; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
