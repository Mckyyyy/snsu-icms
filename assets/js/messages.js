// assets/js/messages.js - Unread message badges and new-message pop-ups on every signed-in page.
// The badge sits on the floating Messages button (and the sidebar item). While a live chat connection
// is open (chat panel open, or the full Messages page), chat.js reports the unread count through
// ICMSMessages.setUnread() and this file pauses its own lightweight background check.
(function () {
  const root = document.getElementById('msgRoot');
  if (!root) return;

  const api = root.dataset.msgApi;
  const pageUrl = root.dataset.msgPage;
  const role = root.dataset.msgRole;
  const baseTitle = document.title.replace(/^\(\d+\)\s+/, '');
  const toastKey = 'icms_msg_toasted_' + role;

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /** Short Messenger-style relative time: "now", "5m", "3h", "Mon", "Nov 2". */
  function shortTime(ts) {
    if (!ts) return '';
    const d = new Date(ts * 1000);
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 6 * 86400) return d.toLocaleDateString(undefined, { weekday: 'short' });
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }

  function setUnread(n) {
    n = Math.max(0, parseInt(n, 10) || 0);
    document.querySelectorAll('[data-msg-badge]').forEach((b) => {
      const changed = b.textContent !== String(n > 99 ? '99+' : n);
      b.textContent = n > 99 ? '99+' : n;
      b.style.display = n > 0 ? '' : 'none';
      if (changed && n > 0 && b.classList.contains('chat-fab-badge')) {  // small "pop" when the count changes
        b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump');
      }
    });
    const fab = document.getElementById('chatFab');
    if (fab) fab.setAttribute('aria-label', n > 0 ? `Open messages (${n} unread)` : 'Open messages');
    document.title = (n > 0 ? '(' + n + ') ' : '') + baseTitle;
  }

  /**
   * Pop-up for a new incoming message (once per message, across pages).
   * target: a URL, or a function (e.g. open the chat widget on that conversation).
   */
  function notify(msg, target) {
    if (!msg || typeof showToast !== 'function') return;
    let last = 0;
    try { last = parseInt(localStorage.getItem(toastKey) || '0', 10); } catch (e) {}
    if (msg.id <= last) return;
    try { localStorage.setItem(toastKey, String(msg.id)); } catch (e) {}
    const who = role === 'admin' ? msg.sender_name : 'Administration · ' + msg.sender_name;
    const text = msg.body.length > 120 ? msg.body.slice(0, 117) + '…' : msg.body;
    showToast(esc(text), 'info', 'New message · ' + esc(who), 6000);
    const container = document.getElementById('toastContainer');
    const toast = container && container.lastElementChild;
    if (toast) {
      toast.classList.add('toast-link');
      toast.addEventListener('click', (e) => {
        if (e.target.closest('.toast-close')) return;
        if (typeof target === 'function') { target(); toast.querySelector('.toast-close')?.click(); }
        else window.location.href = target || pageUrl;
      });
    }
  }

  /** Where a pop-up for a conversation should lead: the chat widget when this page has one. */
  function targetFor(conversationId) {
    if (window.ICMSChat && typeof window.ICMSChat.open === 'function') return () => window.ICMSChat.open(conversationId);
    return pageUrl + (role === 'admin' && conversationId ? '?conv=' + conversationId : '');
  }

  const M = { setUnread, notify, targetFor, shortTime, esc, live: false };
  window.ICMSMessages = M;

  // ---- Safety net for the floating Messages button ----
  // chat.js opens the panel. If chat.js failed to load or stopped on an error (e.g. a stale cached copy
  // after an update, or a browser problem on the live server), the button would do nothing. Remember
  // the first script error, and in that case show it and open the full Messages page instead.
  let firstError = '';
  window.addEventListener('error', (e) => {
    if (!firstError) {
      const where = e.filename ? ' (' + String(e.filename).split('/').pop().split('?')[0] + ':' + (e.lineno || '?') + ')' : '';
      firstError = (e.message || 'Script error') + where;
    }
  });
  const fabEl = document.getElementById('chatFab');
  if (fabEl) {
    fabEl.addEventListener('click', () => {
      if (window.ICMSChat) return; // chat.js is running and handles the click
      if (typeof showToast === 'function') {
        showToast(esc(firstError || 'The chat could not start on this page.') + ' Opening the Messages page instead.',
          'warning', 'Chat problem', 8000);
      }
      setTimeout(() => { window.location.href = pageUrl; }, 1500);
    });
  }

  // ---- Background check while no live chat connection is open ----
  let timer = null;
  function tick() {
    clearTimeout(timer);
    if (M.live) { timer = setTimeout(tick, 5000); return; }
    fetch(api + '?action=summary', { credentials: 'same-origin' })
      .then((r) => (r.status === 401 ? null : r.json()))
      .catch(() => null)
      .then((res) => {
        if (res && res.status === 'success' && !M.live) {
          setUnread(res.unread);
          if (res.latest) notify(res.latest, targetFor(res.latest.conversation_id));
        }
        timer = setTimeout(tick, document.hidden ? 20000 : 4000);
      });
  }
  M.refresh = () => tick();
  timer = setTimeout(tick, 1500);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
})();
