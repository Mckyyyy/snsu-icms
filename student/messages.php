<?php
// student/messages.php - Real-time chat with the administration
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();

$pageTitle = "Messages | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header chat-page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Help &amp; support</div>
    <h1 class="page-title">Messages</h1>
    <p class="page-desc">Chat directly with the administration. For a problem that needs action, <a href="submit_concern.php">submit a concern</a> so it is tracked.</p>
  </div>
</div>

<?php require __DIR__ . '/../includes/chat_ui.php'; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
