<?php
// includes/chat_ui.php - Messenger-style chat shell shared by the full Messages pages
// (admin/messages.php, student/messages.php) and the floating chat widget (includes/chat_widget.php).
// Expects: $pdo, $user (getCurrentUser()), $rootPrefix; optional $chatMode = 'page' (default) | 'widget'.
// Behaviour lives in assets/js/chat.js. Only one instance is rendered per page.
$chatMode = ($chatMode ?? 'page') === 'widget' ? 'widget' : 'page';
$chatIsWidget = $chatMode === 'widget';
$chatIsAdmin = $user['role'] === 'admin';
$chatOpenConv = $chatIsWidget ? 0 : (int)($_GET['conv'] ?? 0);
$chatOpenStudent = $chatIsWidget ? 0 : (int)($_GET['student'] ?? 0);
?>
<div class="chat-shell <?= $chatIsAdmin ? '' : 'chat-shell-single show-thread' ?> <?= $chatIsWidget ? 'chat-shell-widget' : '' ?>" id="chatApp"
     data-mode="<?= $chatMode ?>"
     data-role="<?= $chatIsAdmin ? 'admin' : 'student' ?>"
     data-api="<?= e($rootPrefix . 'api/messages.php') ?>"
     data-me-id="<?= (int)$user['id'] ?>"
     data-me-name="<?= e($user['name']) ?>"
     data-max-length="<?= CHAT_MAX_LENGTH ?>"
     data-edit-window="<?= CHAT_EDIT_WINDOW ?>"
     data-reactions="<?= e(json_encode(CHAT_REACTIONS, JSON_UNESCAPED_UNICODE)) ?>"
     data-open-conv="<?= $chatOpenConv ?>"
     data-open-student="<?= $chatOpenStudent ?>"
     data-open-new="<?= !$chatIsWidget && !empty($_GET['new']) ? '1' : '0' ?>">

  <?php if ($chatIsAdmin): ?>
  <aside class="chat-sidebar" aria-label="Conversations">
    <div class="chat-sidebar-head">
      <?php if (!$chatIsWidget): // the widget's own title bar carries the title and New-message button ?>
      <div class="chat-sidebar-title">
        <span>Chats</span>
        <button type="button" class="btn-icon" id="chatNewBtn" title="New message" aria-label="New message"><?= icon('edit', '', 16) ?></button>
      </div>
      <?php endif; ?>
      <div class="search-input-wrap chat-search">
        <span class="search-icon"><?= icon('search', '', 16) ?></span>
        <input type="search" class="form-control" id="chatSearch" placeholder="Search students" aria-label="Search conversations" autocomplete="off">
      </div>
    </div>
    <div class="chat-list" id="chatList" role="list">
      <div class="chat-list-skeleton"><span></span><span></span><span></span></div>
    </div>
  </aside>
  <?php endif; ?>

  <section class="chat-main" aria-label="Conversation">
    <?php if ($chatIsAdmin && !$chatIsWidget): // the widget always shows the list instead of a placeholder ?>
    <div class="chat-placeholder" id="chatPlaceholder">
      <div class="empty-state">
        <div class="empty-state-icon"><?= icon('message-square', '', 22) ?></div>
        <div class="empty-state-title">Your messages</div>
        <p>Choose a conversation on the left, or start a new one with any student.</p>
        <button type="button" class="btn btn-primary" data-chat-new><?= icon('edit', '', 16) ?> New message</button>
      </div>
    </div>
    <?php endif; ?>

    <div class="chat-thread" id="chatThread" <?= $chatIsAdmin ? 'hidden' : '' ?>>
      <header class="chat-head">
        <?php if ($chatIsAdmin): ?>
          <button type="button" class="btn-icon chat-back" id="chatBack" aria-label="Back to conversations"><?= icon('arrow-left', '', 16) ?></button>
        <?php endif; ?>
        <div class="chat-avatar-wrap">
          <?php if ($chatIsAdmin): ?>
            <div class="avatar" id="chatHeadAvatar">?</div>
          <?php else: ?>
            <div class="avatar avatar-admin" id="chatHeadAvatar"><?= icon('users', '', 16) ?></div>
          <?php endif; ?>
          <span class="chat-presence" id="chatHeadPresence"></span>
        </div>
        <div class="chat-head-text">
          <div class="chat-head-name" id="chatHeadName"><?= $chatIsAdmin ? '' : 'SNSU Administration' ?></div>
          <div class="chat-head-status" id="chatHeadStatus">Connecting…</div>
        </div>
        <?php if ($chatIsAdmin): ?>
          <a href="<?= e($rootPrefix . 'admin/users.php') ?>" class="btn btn-ghost btn-sm chat-head-link" id="chatHeadProfile" title="Find this student in Users"><?= icon('user', '', 14) ?> <span>Account</span></a>
        <?php endif; ?>
      </header>

      <div class="chat-scroll" id="chatScroll" aria-live="polite">
        <div class="chat-loadmore" id="chatLoadMore" hidden><span class="btn-spinner"></span> Loading earlier messages…</div>
        <div class="chat-intro" id="chatIntro" hidden></div>
        <div class="chat-messages" id="chatMessages"></div>
        <div class="chat-typing" id="chatTyping" hidden>
          <div class="chat-bubble-avatar"><div class="avatar <?= $chatIsAdmin ? '' : 'avatar-admin' ?>" id="chatTypingAvatar"></div></div>
          <div>
            <div class="chat-typing-bubble" aria-hidden="true"><span></span><span></span><span></span></div>
            <div class="chat-typing-label" id="chatTypingLabel"><?= $chatIsAdmin ? 'Student is typing...' : 'Admin is typing...' ?></div>
          </div>
        </div>
      </div>

      <button type="button" class="chat-jump" id="chatJump" hidden><?= icon('arrow-right', '', 14) ?> New messages</button>

      <!-- Replying to / Editing bar (filled by chat.js) -->
      <div class="chat-context" id="chatContext" hidden>
        <span class="chat-context-icon" id="chatContextIcon" aria-hidden="true"></span>
        <div class="chat-context-text">
          <strong id="chatContextTitle"></strong>
          <span id="chatContextBody"></span>
        </div>
        <button type="button" class="chat-context-cancel" id="chatContextCancel" aria-label="Cancel" title="Cancel (Esc)">&times;</button>
      </div>

      <form class="chat-composer" id="chatComposer" data-no-enhance autocomplete="off">
        <textarea id="chatInput" class="chat-input" rows="1" maxlength="<?= CHAT_MAX_LENGTH ?>" data-no-counter
                  placeholder="<?= $chatIsAdmin ? 'Write a message…' : 'Message the administration…' ?>" aria-label="Message"></textarea>
        <button type="submit" class="chat-send" id="chatSend" aria-label="Send" title="Send (Enter)" disabled>
          <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M3.4 20.4 21 12 3.4 3.6 3.39 10.13 15 12 3.39 13.87z" fill="currentColor"/></svg>
        </button>
      </form>
      <div class="chat-composer-hint">Enter to send · Shift + Enter for a new line</div>
    </div>
  </section>
</div>

<?php if ($chatIsAdmin): ?>
<div class="modal-backdrop" id="chatNewModal" onclick="if(event.target === this) closeModal('chatNewModal')">
  <div class="modal-content" role="dialog" aria-labelledby="chatNewTitle" style="max-width: 480px;">
    <div class="modal-header">
      <div>
        <h3 class="card-title" id="chatNewTitle">New message</h3>
        <div class="card-subtitle">Find an active student by name, student number or email.</div>
      </div>
      <button type="button" class="alert-close" onclick="closeModal('chatNewModal')" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <div class="search-input-wrap" style="min-width: 0;">
        <span class="search-icon"><?= icon('search', '', 16) ?></span>
        <input type="search" class="form-control" id="chatStudentSearch" placeholder="Name, student number or email" autocomplete="off" aria-label="Search students">
      </div>
      <div class="chat-student-results" id="chatStudentResults">
        <p class="chat-student-hint">Start typing to search.</p>
      </div>
    </div>
  </div>
</div>

<!-- Forward a message to another student's conversation -->
<div class="modal-backdrop" id="chatForwardModal" onclick="if(event.target === this) closeModal('chatForwardModal')">
  <div class="modal-content" role="dialog" aria-labelledby="chatForwardTitle" style="max-width: 460px;">
    <div class="modal-header">
      <div>
        <h3 class="card-title" id="chatForwardTitle">Forward message</h3>
        <div class="card-subtitle">Send a copy to another student's conversation.</div>
      </div>
      <button type="button" class="alert-close" onclick="closeModal('chatForwardModal')" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
      <div class="chat-forward-preview" id="chatForwardPreview"></div>
      <div class="search-input-wrap" style="min-width: 0;">
        <span class="search-icon"><?= icon('search', '', 16) ?></span>
        <input type="search" class="form-control" id="chatForwardSearch" placeholder="Search students" autocomplete="off" aria-label="Search students to forward to">
      </div>
      <div class="chat-student-results" id="chatForwardList"></div>
    </div>
  </div>
</div>
<?php endif; ?>
