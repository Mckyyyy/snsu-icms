<?php
// includes/chat_widget.php - Floating Messages button + chat panel (bottom-right), on every signed-in page
// except the full Messages page itself. Included from includes/footer.php.
// Reuses includes/chat_ui.php (markup) and assets/js/chat.js (behaviour) in "widget" mode.
// Expects: $pdo, $rootPrefix.
$widgetUser = getCurrentUser();
if (!$widgetUser) {
    return;
}
$msgRole    = $widgetUser['role'] === 'admin' ? 'admin' : 'student';
$msgPageUrl = $rootPrefix . $msgRole . '/messages.php';
chatTouchPresence($pdo, $msgRole, (int)$widgetUser['id']);          // page views count as "online"
$msgUnread  = chatUnreadCount($pdo, $msgRole, (int)$widgetUser['id']);
$onMessagesPage = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'messages.php';
?>
<div id="msgRoot" class="no-print"
     data-msg-api="<?= e($rootPrefix . 'api/messages.php') ?>"
     data-msg-page="<?= e($msgPageUrl) ?>"
     data-msg-role="<?= $msgRole ?>">
<?php if (!$onMessagesPage): ?>
  <div class="chat-widget" id="chatWidget">
    <section class="chat-panel" id="chatPanel" role="dialog" aria-modal="false" aria-labelledby="chatPanelTitle" aria-hidden="true">
      <header class="chat-panel-bar">
        <div class="chat-panel-title" id="chatPanelTitle"><?= icon('message-square', '', 18) ?> <span>Messages</span></div>
        <div class="chat-panel-actions">
          <?php if ($msgRole === 'admin'): ?>
            <button type="button" class="chat-panel-btn" data-chat-new title="New message" aria-label="New message"><?= icon('edit', '', 16) ?></button>
          <?php endif; ?>
          <a href="<?= e($msgPageUrl) ?>" class="chat-panel-btn" title="Open full Messages page" aria-label="Open full Messages page">
            <svg class="svg-icon" width="16" height="16" viewBox="0 0 24 24"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </a>
          <button type="button" class="chat-panel-btn" id="chatPanelClose" title="Minimize" aria-label="Minimize chat">
            <svg class="svg-icon" width="16" height="16" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
        </div>
      </header>
      <div class="chat-panel-body">
        <?php
          $pageUser = $user ?? null;   // chat_ui.php reads $user; restore the page's own value afterwards
          $user = $widgetUser;
          $chatMode = 'widget';
          require __DIR__ . '/chat_ui.php';
          $user = $pageUser;
        ?>
      </div>
    </section>

    <button type="button" class="chat-fab" id="chatFab" aria-controls="chatPanel" aria-expanded="false" aria-label="Open messages" title="Messages">
      <span class="chat-fab-icon chat-fab-open"><svg viewBox="0 0 24 24" width="25" height="25" aria-hidden="true"><path d="M4 4.5h16a2 2 0 0 1 2 2v9.5a2 2 0 0 1-2 2H9.5L5 21.5V18H4a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2z" fill="currentColor"/><circle cx="8" cy="11.25" r="1.25" fill="var(--brand-blue)"/><circle cx="12" cy="11.25" r="1.25" fill="var(--brand-blue)"/><circle cx="16" cy="11.25" r="1.25" fill="var(--brand-blue)"/></svg></span>
      <span class="chat-fab-icon chat-fab-close"><svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><polyline points="6 9 12 15 18 9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
      <span class="chat-fab-badge" data-msg-badge style="<?= $msgUnread ? '' : 'display:none;' ?>"><?= $msgUnread > 99 ? '99+' : $msgUnread ?></span>
    </button>
  </div>
<?php endif; ?>
</div>
