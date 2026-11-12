// assets/js/chat.js - Messenger-style real-time chat
//   page mode:   the full Messages pages (admin/messages.php, student/messages.php)
//   widget mode: the floating Messages button + panel on every other page (includes/chat_widget.php)
//
// Transport: long-polling against api/messages.php?action=sync. The server holds each request
// until something changes (new message, read receipt, typing, presence, inbox) and answers within
// ~0.7 s of the change, so messages and the typing indicator appear without refreshing the page.
// In widget mode the connection runs only while the panel is open.
document.addEventListener('DOMContentLoaded', () => {
  const app = document.getElementById('chatApp');
  if (!app) return;
  const IS_WIDGET = app.dataset.mode === 'widget';
  let panelOpen = !IS_WIDGET;      // the full page is always "open"
  /** Messages count as read only while the person can actually see the chat. */
  const viewing = () => document.visibilityState === 'visible' && panelOpen;

  const $ = (id) => document.getElementById(id);
  const M = window.ICMSMessages || {};
  const esc = M.esc || ((s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const ROLE = app.dataset.role;            // 'admin' | 'student'
  const IS_ADMIN = ROLE === 'admin';
  const API = app.dataset.api;
  const ME_ID = parseInt(app.dataset.meId, 10);
  const ME_NAME = app.dataset.meName;
  const MAX_LEN = parseInt(app.dataset.maxLength, 10) || 2000;
  const PEER_LABEL = IS_ADMIN ? 'Student is typing...' : 'Admin is typing...';
  const GROUP_GAP = 5 * 60;                 // seconds: same sender within this gap → one bubble group
  const SEP_GAP = 30 * 60;                  // seconds: show a time separator after this gap
  const EDIT_WINDOW = parseInt(app.dataset.editWindow, 10) || 900;   // seconds a sender may edit
  // Reaction list comes from PHP (CHAT_REACTIONS); escapes keep this source file emoji-free
  let REACTIONS = ['\u{1F44D}', '\u{2764}\u{FE0F}', '\u{1F606}', '\u{1F62E}', '\u{1F622}', '\u{1F620}'];
  try { REACTIONS = JSON.parse(app.dataset.reactions) || REACTIONS; } catch (e) {}

  const els = {
    list: $('chatList'), search: $('chatSearch'), placeholder: $('chatPlaceholder'), thread: $('chatThread'),
    headAvatar: $('chatHeadAvatar'), headPresence: $('chatHeadPresence'), headName: $('chatHeadName'),
    headStatus: $('chatHeadStatus'), headProfile: $('chatHeadProfile'), back: $('chatBack'),
    scroll: $('chatScroll'), loadMore: $('chatLoadMore'), intro: $('chatIntro'), messages: $('chatMessages'),
    typing: $('chatTyping'), typingAvatar: $('chatTypingAvatar'), typingLabel: $('chatTypingLabel'),
    jump: $('chatJump'), form: $('chatComposer'), input: $('chatInput'), send: $('chatSend'),
    context: $('chatContext'), contextIcon: $('chatContextIcon'), contextTitle: $('chatContextTitle'),
    contextBody: $('chatContextBody'), contextCancel: $('chatContextCancel'),
  };

  // ------------------------------------------------------------------ state
  const state = {
    conv: IS_ADMIN ? 0 : -1,   // students: -1 = "my conversation" (resolved by the server)
    info: null,
    messages: [],             // ascending, includes optimistic {tmp, pending, failed}
    seenUpto: 0,
    hasMore: false,
    loadingOlder: false,
    sig: '',
    list: null,
    prevUnread: {},           // conversation id → unread count (admin pop-ups)
    animate: new Set(),
    rev: '',                  // server change marker: edits / unsends / reactions after it arrive as "changed"
    replyTo: null,            // message being replied to
    editing: null,            // message being edited
  };
  let loopGen = 0;
  let controller = null;
  let tmpSeq = 0;
  let popFor = null;   // message id the open reaction picker / More menu belongs to
  let popKind = null;  // 'react' | 'more'

  // ------------------------------------------------------------------ helpers
  const lastRealId = () => {
    for (let i = state.messages.length - 1; i >= 0; i--) if (!state.messages[i].tmp) return state.messages[i].id;
    return 0;
  };
  const firstRealId = () => (state.messages.find((m) => !m.tmp) || {}).id || 0;
  const isMine = (m) => m.sender_type === ROLE;                              // my side of the chat (right)
  const isOwn = (m) => m.sender_type === ROLE && m.sender_id === ME_ID;     // written by me personally
  const firstName = (n) => String(n || '').replace(/^(Dr|Engr|Prof|Mr|Ms|Mrs|Atty)\.?\s+/i, '').trim().split(/\s+/)[0] || 'Someone';
  const snippet = (t, n = 90) => { const s = String(t || '').replace(/\s+/g, ' ').trim(); return s.length > n ? s.slice(0, n - 1) + '…' : s; };
  const canEdit = (m) => isOwn(m) && !m.tmp && !m.unsent && (Date.now() / 1000 - m.ts) < EDIT_WINDOW;
  const canForward = (m) => IS_ADMIN && !m.tmp && !m.unsent && m.sender_type === 'admin';
  const nearBottom = () => els.scroll.scrollHeight - els.scroll.scrollTop - els.scroll.clientHeight < 120;
  const scrollToBottom = (smooth) => els.scroll.scrollTo({ top: els.scroll.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
  const initials = (name) => {
    const clean = String(name || '').replace(/^(Dr|Engr|Prof|Mr|Ms|Mrs|Atty)\.?\s+/i, '').trim().split(/\s+/);
    return ((clean[0] || '?')[0] + (clean.length > 1 ? clean[clean.length - 1][0] : '')).toUpperCase();
  };

  function timeOnly(ts) {
    return new Date(ts * 1000).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  }
  function sepLabel(ts) {
    const d = new Date(ts * 1000);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const day = new Date(d); day.setHours(0, 0, 0, 0);
    const days = Math.round((today - day) / 86400000);
    const t = timeOnly(ts);
    if (days === 0) return 'Today ' + t;
    if (days === 1) return 'Yesterday ' + t;
    if (days < 7) return d.toLocaleDateString(undefined, { weekday: 'long' }) + ' ' + t;
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: d.getFullYear() === today.getFullYear() ? undefined : 'numeric' }) + ', ' + t;
  }
  function lastSeenLabel(ts) {
    if (!ts) return 'Offline';
    const mins = Math.max(1, Math.round((Date.now() / 1000 - ts) / 60));
    if (mins < 60) return 'Active ' + mins + 'm ago';
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return 'Active ' + hrs + 'h ago';
    return 'Last active ' + new Date(ts * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }
  /** Escape, then turn plain http(s) links into anchors. */
  function formatBody(text) {
    return esc(text).replace(/\bhttps?:\/\/[^\s<]+[^\s<.,;:!?)\]'"]/g, (url) => '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + url + '</a>');
  }

  function post(params) {
    const body = new FormData();
    Object.entries(params).forEach(([k, v]) => body.append(k, v));
    body.append('csrf_token', csrf);
    return fetch(API, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf } })
      .then((r) => r.json().catch(() => ({ status: 'error', message: 'Unexpected server response.' })).then((j) => { j.httpStatus = r.status; return j; }));
  }

  const TICK_SENT = '<svg class="chat-tick" viewBox="0 0 16 11" aria-label="Sent"><path d="M1.5 5.8l3.2 3.2L11 2.2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  const TICK_SEEN = '<svg class="chat-tick seen" viewBox="0 0 16 11" aria-label="Seen"><path d="M1 5.8l3.2 3.2L10.5 2.2M6.5 8.6l.4.4L14.8 2.2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  const svg = (d, s = 16) => `<svg viewBox="0 0 24 24" width="${s}" height="${s}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;
  const IC = {
    smile: svg('<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5s1.3 1.8 3.5 1.8 3.5-1.8 3.5-1.8"/><line x1="9" y1="9.5" x2="9.01" y2="9.5"/><line x1="15" y1="9.5" x2="15.01" y2="9.5"/>'),
    reply: svg('<polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/>'),
    more: svg('<circle cx="5" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="19" cy="12" r="1.2"/>'),
    forward: svg('<polyline points="15 14 20 9 15 4"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/>', 13),
    edit: svg('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>'),
    unsend: svg('<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><polyline points="3 3 3 8 8 8"/>'),
    trash: svg('<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/>'),
    copy: svg('<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'),
    fwdMenu: svg('<polyline points="15 14 20 9 15 4"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/>'),
  };

  // ------------------------------------------------------------------ rendering: messages
  function avatarFor(m) {
    if (m.sender_type === 'admin') {
      return '<div class="avatar avatar-admin" title="' + esc(m.sender_name) + '">' + esc(initials(m.sender_name)) + '</div>';
    }
    return '<div class="avatar" title="' + esc(m.sender_name) + '">' + esc(initials(m.sender_name)) + '</div>';
  }

  function renderMessages() {
    const out = [];
    const msgs = state.messages;
    // Index of my side's newest delivered message (gets the "Seen"/"Sent" line)
    let lastMineIdx = -1;
    for (let i = msgs.length - 1; i >= 0; i--) if (isMine(msgs[i]) && !msgs[i].tmp) { lastMineIdx = i; break; }

    msgs.forEach((m, i) => {
      const prev = msgs[i - 1];
      const next = msgs[i + 1];
      const sameAsPrev = prev && prev.sender_type === m.sender_type && prev.sender_id === m.sender_id && m.ts - prev.ts < GROUP_GAP;
      const sameAsNext = next && next.sender_type === m.sender_type && next.sender_id === m.sender_id && next.ts - m.ts < GROUP_GAP;
      if (!prev || m.ts - prev.ts >= SEP_GAP) out.push('<div class="chat-sep">' + esc(sepLabel(m.ts)) + '</div>');

      const mine = isMine(m);
      const seen = mine && !m.tmp && (m.read || m.id <= state.seenUpto);
      const startsGroup = !sameAsPrev || (prev && m.ts - prev.ts >= SEP_GAP);
      const cls = ['chat-row', mine ? 'mine' : 'theirs'];
      if (startsGroup) cls.push('group-start');
      if (!sameAsNext) cls.push('group-end');
      if (m.pending) cls.push('pending');
      if (m.failed) cls.push('failed');
      if (state.animate.has(m.id)) cls.push('is-new');

      // Name label above a group when it is not obvious who wrote it (several admins share the inbox)
      let sender = '';
      if (startsGroup && m.sender_type === 'admin' && !(IS_ADMIN && m.sender_id === ME_ID)) {
        sender = '<div class="chat-sender">' + esc(m.sender_name) + '</div>';
      }
      if (m.reactions && m.reactions.length) cls.push('has-reactions');
      if (state.editing && state.editing.id === m.id) cls.push('is-editing');
      if (popFor !== null && popFor === m.id) cls.push('menu-open');   // keep its toolbar visible while its menu is open

      const meta = '<span class="chat-meta">' + (m.edited ? '<span class="chat-edited">Edited</span> · ' : '')
        + esc(timeOnly(m.ts)) + (mine && !m.tmp && !m.unsent ? (seen ? TICK_SEEN : TICK_SENT) : '') + '</span>';
      let status = '';
      if (m.failed) status = '<div class="chat-status-line" data-retry="' + esc(m.id) + '">Not sent · Tap to retry</div>';
      else if (m.pending) status = '<div class="chat-status-line">Sending…</div>';
      else if (i === lastMineIdx && !m.unsent) status = '<div class="chat-status-line">' + (seen ? 'Seen' : 'Sent') + '</div>';

      const fwd = m.forwarded && !m.unsent
        ? '<div class="chat-fwd">' + IC.forward + (isOwn(m) ? 'You forwarded a message' : esc(firstName(m.sender_name)) + ' forwarded a message') + '</div>' : '';
      const bubble = m.unsent
        ? '<div class="chat-bubble chat-bubble-unsent" title="' + esc(sepLabel(m.ts)) + '">' + (isOwn(m) ? 'You' : esc(firstName(m.sender_name))) + ' unsent a message</div>'
        : '<div class="chat-bubble" title="' + esc(sepLabel(m.ts)) + '">' + formatBody(m.body) + meta + '</div>';

      out.push(
        '<div class="' + cls.join(' ') + '" data-id="' + esc(m.id) + '">'
        + (mine ? '' : '<div class="chat-bubble-avatar">' + (!sameAsNext ? avatarFor(m) : '') + '</div>')
        + '<div class="chat-col">' + sender + fwd + renderQuote(m)
        + '<div class="chat-bubble-wrap">' + bubble + renderReactions(m) + renderActions(m) + '</div>'
        + status + '</div></div>'
      );
    });
    els.messages.innerHTML = out.join('');
    state.animate.clear();
    if (popFor !== null) placePopover();   // live update re-drew the rows: re-anchor an open menu

    // Intro card for an empty conversation
    if (!msgs.length && state.info !== undefined) {
      els.intro.hidden = false;
      els.intro.innerHTML = IS_ADMIN && state.info
        ? '<div class="avatar">' + esc(state.info.initials) + '</div><strong>' + esc(state.info.name) + '</strong>'
          + esc(state.info.student_no) + ' · ' + esc(state.info.program || '') + '<br>Say hello — your message will reach the student instantly.'
        : '<div class="avatar avatar-admin">AD</div><strong>SNSU Administration</strong>Ask a question or follow up on a concern. An administrator will reply here.';
    } else {
      els.intro.hidden = true;
    }
  }

  /** "You replied to Juan" + the quoted text (fresh from the timeline when the original is loaded). */
  function renderQuote(m) {
    if (!m.reply) return '';
    const live = state.messages.find((x) => x.id === m.reply.id);
    const r = live || m.reply;
    const target = (r.sender_type === ROLE && r.sender_id === ME_ID) ? (isOwn(m) ? 'yourself' : 'you') : firstName(r.sender_name);
    const who = (isOwn(m) ? 'You' : firstName(m.sender_name)) + ' replied to ' + target;
    const body = r.unsent ? 'Message unsent' : (r.missing ? 'Original message not available' : snippet(r.body, 110));
    return '<div class="chat-quote' + (r.unsent || r.missing ? ' is-gone' : '') + '">'
      + '<div class="chat-quote-label">' + IC.reply.replace('width="16" height="16"', 'width="12" height="12"') + esc(who) + '</div>'
      + '<button type="button" class="chat-quote-body" data-jump="' + esc(m.reply.id) + '" title="Show original">' + esc(body) + '</button></div>';
  }

  /** Reaction pill under the bubble: up to three emojis + total; names in the tooltip. */
  function renderReactions(m) {
    if (m.unsent || !m.reactions || !m.reactions.length) return '';
    const total = m.reactions.reduce((n, r) => n + r.count, 0);
    const mine = m.reactions.some((r) => r.mine);
    const tip = m.reactions.map((r) => r.emoji + ' ' + r.names.join(', ')).join('\n');
    return '<button type="button" class="chat-reactions' + (mine ? ' mine' : '') + '" data-act="react" title="' + esc(tip) + '" aria-label="Reactions: ' + esc(tip) + '">'
      + m.reactions.slice(0, 3).map((r) => '<span>' + esc(r.emoji) + '</span>').join('')
      + (total > 1 ? '<span class="chat-reactions-count">' + total + '</span>' : '') + '</button>';
  }

  /** Hover / tap toolbar: React · Reply · More */
  function renderActions(m) {
    if (m.tmp) return '';
    const btn = (act, icon, label) => '<button type="button" class="chat-action" data-act="' + act + '" title="' + label + '" aria-label="' + label + '">' + icon + '</button>';
    return '<div class="chat-actions" role="toolbar" aria-label="Message actions">'
      + (m.unsent ? '' : btn('react', IC.smile, 'React') + btn('reply', IC.reply, 'Reply'))
      + btn('more', IC.more, 'More') + '</div>';
  }

  /** Merge server messages into the timeline (dedupe; adopt matching optimistic bubbles). */
  function mergeMessages(incoming) {
    let added = 0;
    incoming.forEach((m) => {
      if (state.messages.some((x) => x.id === m.id)) return;
      if (isMine(m) && m.sender_id === ME_ID) {
        const tmpIdx = state.messages.findIndex((x) => x.tmp && x.pending && x.body === m.body);
        if (tmpIdx !== -1) { state.messages[tmpIdx] = m; return; }
      }
      state.messages.push(m);
      state.animate.add(m.id);
      added++;
    });
    // Keep real messages ordered by id, optimistic ones last
    state.messages.sort((a, b) => (a.tmp ? Infinity : a.id) - (b.tmp ? Infinity : b.id) || (a.ts - b.ts));
    return added;
  }

  // ------------------------------------------------------------------ rendering: header, typing, list
  function renderPeer(peer) {
    if (!peer) return;
    state.peer = peer;
    els.headPresence.classList.toggle('online', !!peer.online);
    els.headStatus.classList.toggle('online', !!peer.online);
    els.headStatus.textContent = peer.online ? 'Active now' : (IS_ADMIN ? lastSeenLabel(peer.last_seen) : 'Offline · we will reply as soon as possible');

    const wasHidden = els.typing.hidden;
    els.typing.hidden = !peer.typing;
    if (peer.typing) {
      els.typingLabel.textContent = PEER_LABEL;
      if (IS_ADMIN) {
        els.typingAvatar.textContent = state.info ? state.info.initials : '';
        els.typingLabel.title = state.info ? state.info.name + ' is typing' : '';
      } else {
        els.typingAvatar.textContent = initials(peer.typing_name || 'Admin');
        els.typingLabel.title = (peer.typing_name || 'An administrator') + ' is typing';
      }
      if (wasHidden && nearBottom()) scrollToBottom(true);
    }
  }

  function renderHeader(info) {
    if (!IS_ADMIN || !info) return;
    els.headName.textContent = info.name;
    els.headAvatar.textContent = info.initials;
    if (els.headProfile) els.headProfile.href = els.headProfile.href.split('?')[0] + '?role=student&q=' + encodeURIComponent(info.student_no);
    // Inbox-list rows carry no account status; the full record (with status) arrives from the server right after
    if (info.status && info.status !== 'Active') {
      els.input.disabled = true;
      els.input.placeholder = 'This student account is ' + info.status.toLowerCase() + '.';
    } else {
      els.input.disabled = false;
      els.input.placeholder = 'Write a message…';
    }
  }

  function renderList() {
    if (!IS_ADMIN || !els.list) return;
    const list = state.list || [];
    const q = (els.search.value || '').trim().toLowerCase();
    const rows = list.filter((c) => !q || c.name.toLowerCase().includes(q) || String(c.student_no).toLowerCase().includes(q));
    if (!list.length) {
      els.list.innerHTML = '<div class="chat-list-empty">No conversations yet.<br>Use <strong>New message</strong> to contact a student.</div>';
      return;
    }
    if (!rows.length) {
      els.list.innerHTML = '<div class="chat-list-empty">No conversation matches “' + esc(q) + '”.</div>';
      return;
    }
    els.list.innerHTML = rows.map((c) =>
      '<button type="button" role="listitem" class="chat-list-item' + (c.id === state.conv ? ' active' : '') + (c.unread ? ' unread' : '') + '" data-conv="' + c.id + '">'
      + '<div class="chat-avatar-wrap"><div class="avatar">' + esc(c.initials) + '</div><span class="chat-presence' + (c.online ? ' online' : '') + '"></span></div>'
      + '<div class="msg-item-text"><div class="msg-item-top"><span class="msg-item-name">' + esc(c.name) + '</span>'
      + '<span class="msg-item-time">' + esc((M.shortTime || (() => ''))(c.ts)) + '</span></div>'
      + (c.typing ? '<div class="msg-item-preview msg-item-typing">typing…</div>' : '<div class="msg-item-preview">' + esc(c.preview) + '</div>')
      + '</div>'
      + (c.unread ? '<span class="chat-unread-pill" aria-label="' + c.unread + ' unread">' + c.unread + '</span>' : '')
      + '</button>').join('');
  }

  /** Admin pane when no chat is open: a prompt to pick one, or a clean empty state when the inbox is empty. */
  function renderPlaceholder() {
    if (!els.placeholder || state.list === null) return;
    const empty = state.list.length === 0;
    const title = els.placeholder.querySelector('.empty-state-title');
    const text = els.placeholder.querySelector('.empty-state p');
    if (title) title.textContent = empty ? 'No conversations yet' : 'Select a conversation';
    if (text) text.textContent = empty
      ? 'When a student messages the administration, the conversation appears here. You can also start one yourself.'
      : 'Pick a student on the left to see the chat history, or start a new conversation.';
  }

  /** Admin: pop-up for messages arriving in conversations that are not open. */
  function notifyFromList(list) {
    const first = state.list === null;
    // In the widget the list itself is on screen when no chat is open, so no pop-up is needed then
    const listOnScreen = IS_WIDGET && state.conv === 0 && viewing();
    list.forEach((c) => {
      const before = state.prevUnread[c.id] || 0;
      if (!first && !listOnScreen && c.unread > before && c.last_from === 'student' && (c.id !== state.conv || !viewing()) && M.notify) {
        M.notify({ id: c.last_id, sender_name: c.name, body: c.preview }, M.targetFor ? M.targetFor(c.id) : 'messages.php?conv=' + c.id);
      }
      state.prevUnread[c.id] = c.unread;
    });
  }

  // ------------------------------------------------------------------ sync loop
  function apply(data, init) {
    if (data.conversation && (IS_ADMIN ? data.conversation.id === state.conv : true)) {
      state.info = data.conversation;
      renderHeader(state.info);
    }
    if (data.list) {
      notifyFromList(data.list);
      state.list = data.list;
      renderList();
      renderPlaceholder();
      // Opened Messages without a specific chat: go straight to the most relevant conversation
      if (state.autoOpen && state.conv === 0) {
        state.autoOpen = false;
        const target = data.list.find((c) => c.unread > 0) || data.list[0];
        if (target && window.matchMedia('(min-width: 901px)').matches) { openConversation(target.id); return; }
      }
    }
    if (typeof data.unread === 'number' && M.setUnread) M.setUnread(data.unread);
    if (state.conv === 0) { state.sig = data.sig; return; }

    let changed = false;
    let incoming = 0;
    const stick = nearBottom();
    if (init) {
      state.messages = data.messages || [];
      state.hasMore = !!data.has_more;
      changed = true;
    } else if (data.messages && data.messages.length) {
      incoming = mergeMessages(data.messages);
      changed = true;
    }
    if (data.seen_upto !== state.seenUpto) { state.seenUpto = data.seen_upto; changed = true; }
    // Edits, unsends and reactions on messages already on screen
    if (!init && data.changed && data.changed.length) {
      data.changed.forEach((cm) => {
        const i = state.messages.findIndex((x) => x.id === cm.id);
        if (i !== -1) state.messages[i] = cm;
        if (state.replyTo && state.replyTo.id === cm.id && cm.unsent) setContext(null);   // can't reply to an unsent message
      });
      changed = true;
    }
    if (data.rev) state.rev = data.rev;
    if (changed) renderMessages();
    renderPeer(data.peer);

    if (init) {
      scrollToBottom(false);
    } else if (incoming) {
      const fromPeer = data.messages.some((m) => !isMine(m));
      if (stick || !fromPeer) scrollToBottom(true);
      else els.jump.hidden = false;
      if (fromPeer && !viewing() && !IS_ADMIN && M.notify) {
        const m = data.messages.filter((x) => !isMine(x)).pop();
        M.notify(m, null);
      }
    }
    state.sig = data.sig;
  }

  async function loop(gen, init) {
    let first = true;
    let failures = 0;
    while (gen === loopGen) {
      const params = new URLSearchParams({
        action: 'sync',
        wait: first ? '0' : '1',
        after: String(lastRealId()),
        sig: first ? '' : state.sig,
        visible: viewing() ? '1' : '0',
      });
      if (first && init && state.conv !== 0) params.set('init', '1');
      else if (state.rev) params.set('rev', state.rev);
      if (IS_ADMIN) {
        params.set('list', '1');
        if (state.conv > 0) params.set('conv', String(state.conv));
      }
      controller = new AbortController();
      let data;
      try {
        const res = await fetch(API + '?' + params.toString(), { credentials: 'same-origin', signal: controller.signal, cache: 'no-store' });
        if (res.status === 401) { location.reload(); return; }
        if (res.status === 404 && IS_ADMIN) { if (gen === loopGen) closeConversation(); return; }
        data = await res.json();
      } catch (e) {
        if (gen !== loopGen) return;          // aborted on purpose
        failures++;
        els.headStatus.textContent = 'Reconnecting…';
        await new Promise((r) => setTimeout(r, Math.min(15000, 1500 * failures)));
        continue;
      }
      if (gen !== loopGen) return;
      failures = 0;
      if (data && data.status === 'success') apply(data, first && init);
      first = false;
      // On shared hosting the server answers at once and asks the browser to pause between syncs
      if (data && data.retry_ms > 0) {
        await new Promise((r) => setTimeout(r, data.retry_ms));
      }
    }
  }

  /** (Re)start the live connection. init=true reloads the open conversation from scratch. */
  function restart(init) {
    loopGen++;
    if (controller) controller.abort();
    if (!panelOpen) return;          // closed widget: messages.js keeps the badge up to date instead
    M.live = true;
    loop(loopGen, init);
  }

  /** Stop the live connection (widget minimized). */
  function stopLive() {
    loopGen++;
    if (controller) controller.abort();
    M.live = false;
    if (M.refresh) M.refresh();       // hand the unread badge back to the background check right away
  }

  // ------------------------------------------------------------------ conversation selection (admin)
  function openConversation(id, info) {
    stopTyping();
    state.autoOpen = false;
    state.conv = id;
    state.info = info || (state.list || []).find((c) => c.id === id) || null;
    state.messages = [];
    state.seenUpto = 0;
    state.hasMore = false;
    state.rev = '';
    setContext(null);
    // Show the chat pane first so a rendering problem can never leave the placeholder on screen
    if (els.placeholder) els.placeholder.hidden = true;
    els.thread.hidden = false;
    app.classList.add('show-thread');
    els.headName.textContent = 'Loading…';
    els.headAvatar.textContent = '';
    els.headStatus.textContent = 'Loading conversation…';
    els.headPresence.classList.remove('online');
    els.typing.hidden = true;
    els.jump.hidden = true;
    els.messages.innerHTML = '';
    els.intro.hidden = true;
    renderHeader(state.info);
    renderList();
    if (!IS_WIDGET) {                 // the widget never changes the address of the page it sits on
      const url = new URL(location.href);
      url.searchParams.set('conv', String(id));
      url.searchParams.delete('new');
      url.searchParams.delete('student');
      history.replaceState(null, '', url);
    }
    restart(true);
    if (IS_WIDGET || window.matchMedia('(min-width: 901px)').matches) els.input.focus({ preventScroll: true });
  }

  /** Back to the conversation list. reconnect=false when the widget is being minimized. */
  function closeConversation(reconnect = true) {
    stopTyping();
    state.conv = 0;
    state.info = null;
    state.messages = [];
    state.peer = null;
    state.rev = '';
    setContext(null);
    els.thread.hidden = true;
    els.typing.hidden = true;
    if (els.placeholder) els.placeholder.hidden = false;
    app.classList.remove('show-thread');
    if (!IS_WIDGET) {
      const url = new URL(location.href);
      url.searchParams.delete('conv');
      history.replaceState(null, '', url);
    }
    renderList();
    if (reconnect) restart(false);
  }

  // ------------------------------------------------------------------ older messages
  els.scroll.addEventListener('scroll', () => {
    if (nearBottom()) els.jump.hidden = true;
    if (els.scroll.scrollTop > 60 || !state.hasMore || state.loadingOlder || !state.conv) return;
    state.loadingOlder = true;
    els.loadMore.hidden = false;
    const conv = state.conv;
    const params = new URLSearchParams({ action: 'history', before: String(firstRealId()) });
    if (IS_ADMIN) params.set('conv', String(conv));
    fetch(API + '?' + params, { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((res) => {
        if (conv !== state.conv || !res || res.status !== 'success') return;
        const before = els.scroll.scrollHeight;
        const known = new Set(state.messages.map((m) => m.id));
        state.messages = res.messages.filter((m) => !known.has(m.id)).concat(state.messages);
        state.hasMore = !!res.has_more;
        renderMessages();
        els.scroll.scrollTop += els.scroll.scrollHeight - before;
      })
      .catch(() => {})
      .finally(() => { state.loadingOlder = false; els.loadMore.hidden = true; });
  });
  els.jump.addEventListener('click', () => { scrollToBottom(true); els.jump.hidden = true; });

  // ------------------------------------------------------------------ typing signal
  let typingActive = false;
  let typingSentAt = 0;
  function convParam() { return IS_ADMIN ? String(state.conv) : '0'; }
  function sendTyping(on) {
    if (!state.conv) return;
    typingActive = on;
    typingSentAt = Date.now();
    post({ action: 'typing', conv: convParam(), typing: on ? '1' : '0' }).catch(() => {});
  }
  function stopTyping() { if (typingActive) sendTyping(false); }

  function autosize() {
    els.input.style.height = 'auto';
    els.input.style.height = Math.min(els.input.scrollHeight, 140) + 'px';
  }

  els.input.addEventListener('input', () => {
    autosize();
    const hasText = els.input.value.trim().length > 0;
    els.send.disabled = !hasText;
    if (hasText && (!typingActive || Date.now() - typingSentAt > 2500)) sendTyping(true);
    else if (!hasText && typingActive) sendTyping(false);
  });
  els.input.addEventListener('blur', stopTyping);
  els.input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
      e.preventDefault();
      els.form.requestSubmit ? els.form.requestSubmit() : els.form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  });

  // ------------------------------------------------------------------ sending
  function deliver(tmp) {
    tmp.pending = true;
    tmp.failed = false;
    renderMessages();
    const conv = state.conv;
    const payload = { action: 'send', conv: convParam(), body: tmp.body };
    if (tmp.reply) payload.reply_to = String(tmp.reply.id);
    post(payload).then((res) => {
      if (conv !== state.conv) return;
      const idx = state.messages.indexOf(tmp);
      if (res.status === 'success') {
        if (idx !== -1) {
          if (state.messages.some((m) => m.id === res.message.id)) state.messages.splice(idx, 1);
          else state.messages[idx] = res.message;
        }
        state.messages.sort((a, b) => (a.tmp ? Infinity : a.id) - (b.tmp ? Infinity : b.id));
      } else {
        tmp.pending = false;
        tmp.failed = true;
        if (typeof showToast === 'function') showToast(esc(res.message || 'Message not sent.'), 'error', 'Messages');
        if (res.httpStatus === 401) location.reload();
      }
      renderMessages();
      scrollToBottom(true);
    }).catch(() => {
      tmp.pending = false;
      tmp.failed = true;
      renderMessages();
    });
  }

  els.form.addEventListener('submit', (e) => {
    e.preventDefault();
    const body = els.input.value.trim();
    if (!body || !state.conv || els.input.disabled) return;
    if (body.length > MAX_LEN) {
      if (typeof showToast === 'function') showToast('Messages can be at most ' + MAX_LEN + ' characters.', 'warning', 'Messages');
      return;
    }
    // Editing an existing message
    if (state.editing) {
      const target = state.editing;
      if (body === target.body) { setContext(null); return; }
      els.send.disabled = true;
      post({ action: 'edit', id: String(target.id), body }).then((res) => {
        if (res.status === 'success') {
          replaceMessage(res.message);
          setContext(null);
        } else {
          els.send.disabled = false;
          toast(res.message || 'The message could not be edited.', 'error');
        }
      }).catch(() => { els.send.disabled = false; toast('The message could not be edited.', 'error'); });
      return;
    }
    const tmp = { id: 'tmp-' + (++tmpSeq), tmp: true, sender_type: ROLE, sender_id: ME_ID, sender_name: ME_NAME, body, ts: Math.floor(Date.now() / 1000), read: false,
                  reply: state.replyTo ? { id: state.replyTo.id, sender_type: state.replyTo.sender_type, sender_id: state.replyTo.sender_id, sender_name: state.replyTo.sender_name, body: state.replyTo.body } : null,
                  reactions: [] };
    setContext(null);
    state.messages.push(tmp);
    state.animate.add(tmp.id);
    els.input.value = '';
    autosize();
    els.send.disabled = true;
    typingActive = false;            // the server clears the typing flag when the message is saved
    deliver(tmp);
    scrollToBottom(true);
    els.input.focus();
  });

  // ------------------------------------------------------------------ message actions (react, reply, edit, unsend, delete, forward, copy)
  const toast = (msg, type = 'info') => { if (typeof showToast === 'function') showToast(esc(msg), type, 'Messages'); };
  const byId = (id) => state.messages.find((m) => String(m.id) === String(id));

  function replaceMessage(msg) {
    if (!msg) return;
    const i = state.messages.findIndex((x) => x.id === msg.id);
    if (i !== -1) state.messages[i] = msg;
    renderMessages();
  }

  /** Bar above the composer. mode: 'reply' | 'edit' | null */
  let draftBeforeEdit = '';
  function setContext(mode, m) {
    const wasEditing = !!state.editing;
    state.replyTo = mode === 'reply' ? m : null;
    state.editing = mode === 'edit' ? m : null;
    if (!els.context) return;
    if (!mode) {
      els.context.hidden = true;
      if (wasEditing) { els.input.value = draftBeforeEdit; draftBeforeEdit = ''; autosize(); els.send.disabled = !els.input.value.trim(); }
      renderMessages();
      return;
    }
    els.context.hidden = false;
    els.context.dataset.mode = mode;
    if (mode === 'reply') {
      els.contextIcon.innerHTML = IC.reply;
      els.contextTitle.textContent = 'Replying to ' + (isOwn(m) ? 'yourself' : m.sender_name);
      els.contextBody.textContent = snippet(m.body, 120);
    } else {
      els.contextIcon.innerHTML = IC.edit;
      els.contextTitle.textContent = 'Editing message';
      els.contextBody.textContent = snippet(m.body, 120);
      if (!wasEditing) draftBeforeEdit = els.input.value;
      els.input.value = m.body;
      autosize();
      els.send.disabled = false;
    }
    renderMessages();
    els.input.focus();
    if (mode === 'edit') els.input.setSelectionRange(els.input.value.length, els.input.value.length);
  }
  if (els.contextCancel) els.contextCancel.addEventListener('click', () => setContext(null));
  els.input.addEventListener('keydown', (e) => { if (e.key === 'Escape' && (state.replyTo || state.editing)) { e.stopPropagation(); setContext(null); } });

  // ---- Floating popovers (one picker, one menu, shared by every message) ----
  const picker = document.createElement('div');
  picker.className = 'chat-popover chat-picker';
  picker.setAttribute('role', 'menu');
  picker.hidden = true;
  picker.innerHTML = REACTIONS.map((r) => '<button type="button" role="menuitem" data-emoji="' + esc(r) + '" aria-label="React ' + esc(r) + '">' + esc(r) + '</button>').join('');
  const menu = document.createElement('div');
  menu.className = 'chat-popover chat-menu';
  menu.setAttribute('role', 'menu');
  menu.hidden = true;
  document.body.append(picker, menu);
  function closePopovers() {
    picker.hidden = true; menu.hidden = true; popFor = null; popKind = null;
    els.messages.querySelectorAll('.chat-row.menu-open').forEach((r) => r.classList.remove('menu-open'));
  }
  /** The button a popover hangs from, looked up again each time (rows are re-rendered by live updates). */
  function popAnchor() {
    const row = popFor === null ? null : els.messages.querySelector('.chat-row[data-id="' + CSS.escape(String(popFor)) + '"]');
    return row ? row.querySelector('.chat-actions [data-act="' + (popKind === 'react' ? 'react' : 'more') + '"]') : null;
  }
  function placePopover() {
    const pop = popKind === 'react' ? picker : menu;
    const anchor = popAnchor();
    if (!anchor || pop.hidden) { if (popFor !== null) closePopovers(); return; }
    anchor.closest('.chat-row').classList.add('menu-open');
    const a = anchor.getBoundingClientRect();
    const s = els.scroll.getBoundingClientRect();
    if (a.bottom < s.top || a.top > s.bottom) { closePopovers(); return; }   // its message scrolled out of view
    // Layout size (offsetWidth/Height) ignores the pop-in scale animation, so the first placement is exact
    const p = { width: pop.offsetWidth, height: pop.offsetHeight };
    let left = a.left + a.width / 2 - p.width / 2;
    left = Math.max(8, Math.min(left, innerWidth - p.width - 8));
    let top = a.top - p.height - 8;
    if (top < 8) top = a.bottom + 8;
    pop.style.left = left + 'px';
    pop.style.top = top + 'px';
  }
  function openPopover(pop, anchor, id) {
    closePopovers();
    popFor = id;
    popKind = pop === picker ? 'react' : 'more';
    pop.hidden = false;
    placePopover();
    pop.querySelector('button')?.focus({ preventScroll: true });
  }
  document.addEventListener('click', (e) => {
    if (!picker.hidden || !menu.hidden) {
      if (!picker.contains(e.target) && !menu.contains(e.target) && !e.target.closest('[data-act]')) closePopovers();
    }
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && (!picker.hidden || !menu.hidden)) { e.stopPropagation(); closePopovers(); } }, true);
  // Follow the message while the chat scrolls or new messages arrive, instead of snapping shut
  els.scroll.addEventListener('scroll', () => { if (popFor !== null) placePopover(); }, { passive: true });
  window.addEventListener('resize', () => { if (popFor !== null) placePopover(); });

  picker.addEventListener('click', (e) => {
    const b = e.target.closest('[data-emoji]');
    if (!b || !popFor) return;
    const id = popFor;
    closePopovers();
    react(id, b.dataset.emoji);
  });

  function react(id, emoji) {
    post({ action: 'react', id: String(id), emoji }).then((res) => {
      if (res.status === 'success') replaceMessage(res.message);
      else toast(res.message || 'Could not add the reaction.', 'error');
    }).catch(() => toast('Could not add the reaction.', 'error'));
  }

  function openMenu(anchor, m) {
    const items = [];
    if (canEdit(m)) items.push(['edit', IC.edit, 'Edit']);
    if (!m.unsent) items.push(['copy', IC.copy, 'Copy text']);
    if (canForward(m)) items.push(['forward', IC.fwdMenu, 'Forward']);
    if (isOwn(m) && !m.unsent) items.push(['unsend', IC.unsend, 'Unsend for everyone', 'danger']);
    items.push(['delete', IC.trash, 'Remove for you', 'danger']);
    menu.innerHTML = items.map(([act, ic, label, tone]) => '<button type="button" role="menuitem" data-menu="' + act + '" class="' + (tone || '') + '">' + ic + '<span>' + label + '</span></button>').join('');
    openPopover(menu, anchor, m.id);
  }

  menu.addEventListener('click', (e) => {
    const b = e.target.closest('[data-menu]');
    if (!b || !popFor) return;
    const m = byId(popFor);
    closePopovers();
    if (!m) return;
    const act = b.dataset.menu;
    if (act === 'edit') {
      if (!canEdit(m)) { toast('Messages can only be edited within ' + Math.round(EDIT_WINDOW / 60) + ' minutes of sending.', 'warning'); return; }
      setContext('edit', m);
    } else if (act === 'copy') {
      (navigator.clipboard ? navigator.clipboard.writeText(m.body) : Promise.reject()).then(() => toast('Message copied.', 'success')).catch(() => toast('Copy is not available in this browser.', 'warning'));
    } else if (act === 'forward') {
      openForward(m);
    } else if (act === 'unsend') {
      confirmThen('Unsend for everyone?', 'This message will be removed for everyone in the chat. They may have already seen it.', 'Unsend', () =>
        post({ action: 'unsend', id: String(m.id) }).then((res) => {
          if (res.status === 'success') { if (state.editing && state.editing.id === m.id) setContext(null); replaceMessage(res.message); }
          else toast(res.message || 'Could not unsend the message.', 'error');
        }));
    } else if (act === 'delete') {
      confirmThen('Remove for you?', 'This message will be removed from your view. Others in the chat will still see it.', 'Remove', () =>
        post({ action: 'delete', id: String(m.id) }).then((res) => {
          if (res.status === 'success') {
            if ((state.replyTo && state.replyTo.id === m.id) || (state.editing && state.editing.id === m.id)) setContext(null);
            state.messages = state.messages.filter((x) => x.id !== m.id);
            renderMessages();
          } else toast(res.message || 'Could not remove the message.', 'error');
        }));
    }
  });

  function confirmThen(title, message, confirmText, onConfirm) {
    if (typeof showConfirmModal === 'function') {
      showConfirmModal({ title, message: esc(message), confirmText, type: 'danger', onConfirm });
    } else if (window.confirm(message)) {
      onConfirm();
    }
  }

  function jumpTo(id) {
    const row = els.messages.querySelector('.chat-row[data-id="' + CSS.escape(String(id)) + '"]');
    if (!row) { toast('The original message is further up. Scroll up to load it.', 'info'); return; }
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    row.classList.remove('flash'); void row.offsetWidth; row.classList.add('flash');
  }

  els.messages.addEventListener('click', (e) => {
    const retry = e.target.closest('[data-retry]');
    if (retry) {
      const tmp = state.messages.find((m) => String(m.id) === retry.dataset.retry);
      if (tmp) deliver(tmp);
      return;
    }
    const jump = e.target.closest('[data-jump]');
    if (jump) { jumpTo(jump.dataset.jump); return; }
    const actBtn = e.target.closest('[data-act]');
    const row = e.target.closest('.chat-row');
    if (actBtn && row) {
      const m = byId(row.dataset.id);
      if (!m) return;
      const act = actBtn.dataset.act;
      if (popFor === m.id && ((act === 'react' && !picker.hidden) || (act === 'more' && !menu.hidden))) { closePopovers(); return; }
      if (act === 'react') openPopover(picker, actBtn, m.id);
      else if (act === 'more') openMenu(actBtn, m);
      else if (act === 'reply') { closePopovers(); setContext('reply', m); }
      return;
    }
    // Touch screens have no hover: tapping a bubble shows its actions
    if (row && e.target.closest('.chat-bubble') && !e.target.closest('a')) {
      const open = row.classList.contains('show-actions');
      els.messages.querySelectorAll('.chat-row.show-actions').forEach((r) => r.classList.remove('show-actions'));
      if (!open) row.classList.add('show-actions');
    }
  });
  // Double-click a bubble: quick heart reaction (like Messenger's double-tap)
  const HEART = '\u{2764}\u{FE0F}';
  els.messages.addEventListener('dblclick', (e) => {
    const row = e.target.closest('.chat-row');
    if (!row || !e.target.closest('.chat-bubble:not(.chat-bubble-unsent)')) return;
    const m = byId(row.dataset.id);
    if (m && !m.tmp && !m.unsent) { window.getSelection()?.removeAllRanges(); react(m.id, HEART); }
  });

  // ---- Forward (administrators) ----
  const fwdModal = $('chatForwardModal');
  const fwdList = $('chatForwardList');
  const fwdSearch = $('chatForwardSearch');
  const fwdPreview = $('chatForwardPreview');
  let fwdMessage = null;
  let fwdSeq = 0;
  const fwdSent = new Set();

  function fwdRow(key, name, initials, sub, attrs) {
    const sent = fwdSent.has(key);
    return '<div class="chat-list-item chat-fwd-row"><div class="avatar">' + esc(initials) + '</div>'
      + '<div class="msg-item-text"><div class="msg-item-name">' + esc(name) + '</div><div class="msg-item-preview">' + esc(sub) + '</div></div>'
      + '<button type="button" class="btn btn-sm ' + (sent ? 'btn-secondary' : 'btn-primary') + '" ' + attrs + ' data-key="' + esc(key) + '"' + (sent ? ' disabled' : '') + '>' + (sent ? 'Sent' : 'Send') + '</button></div>';
  }
  function renderForwardList(students) {
    if (!fwdList) return;
    const q = (fwdSearch.value || '').trim().toLowerCase();
    if (students) {
      fwdList.innerHTML = students.length
        ? students.map((s) => fwdRow('s' + s.student_id, s.name, s.initials, s.student_no + ' · ' + (s.program || ''), 'data-fwd-student="' + s.student_id + '"')).join('')
        : '<p class="chat-student-hint">No active student matches “' + esc(q) + '”.</p>';
      return;
    }
    const recent = (state.list || []).slice(0, 12);
    fwdList.innerHTML = recent.length
      ? '<p class="chat-forward-heading">Recent conversations</p>' + recent.map((c) => fwdRow('s' + c.student_id, c.name, c.initials, c.student_no, 'data-fwd-conv="' + c.id + '"')).join('')
      : '<p class="chat-student-hint">Search for a student to forward to.</p>';
  }
  function openForward(m) {
    if (!fwdModal) return;
    fwdMessage = m;
    fwdSent.clear();
    fwdSearch.value = '';
    fwdPreview.innerHTML = '<span class="chat-forward-label">' + IC.forward + ' Message</span><div class="chat-forward-text">' + esc(snippet(m.body, 220)) + '</div>';
    renderForwardList(null);
    if (typeof openModal === 'function') openModal('chatForwardModal');
    setTimeout(() => fwdSearch.focus(), 120);
  }
  if (fwdModal) {
    let timer = null;
    fwdSearch.addEventListener('input', () => {
      clearTimeout(timer);
      const q = fwdSearch.value.trim();
      if (q.length < 2) { renderForwardList(null); return; }
      timer = setTimeout(() => {
        const seq = ++fwdSeq;
        fetch(API + '?action=students&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
          .then((r) => r.json()).then((res) => { if (seq === fwdSeq && res && res.status === 'success') renderForwardList(res.students); }).catch(() => {});
      }, 250);
    });
    fwdList.addEventListener('click', (e) => {
      const b = e.target.closest('[data-fwd-conv], [data-fwd-student]');
      if (!b || !fwdMessage || b.disabled) return;
      b.disabled = true;
      b.textContent = 'Sending…';
      const payload = { action: 'forward', id: String(fwdMessage.id) };
      if (b.dataset.fwdConv) payload.to_conv = b.dataset.fwdConv; else payload.to_student = b.dataset.fwdStudent;
      post(payload).then((res) => {
        if (res.status === 'success') {
          fwdSent.add(b.dataset.key);
          b.textContent = 'Sent';
          b.classList.replace('btn-primary', 'btn-secondary');
        } else {
          b.disabled = false;
          b.textContent = 'Send';
          toast(res.message || 'Could not forward the message.', 'error');
        }
      }).catch(() => { b.disabled = false; b.textContent = 'Send'; });
    });
  }

  // ------------------------------------------------------------------ admin: list, search, new message
  if (IS_ADMIN) {
    els.list.addEventListener('click', (e) => {
      const item = e.target.closest('[data-conv]');
      if (item) openConversation(parseInt(item.dataset.conv, 10));
    });
    els.search.addEventListener('input', renderList);
    els.back.addEventListener('click', () => closeConversation());

    const results = $('chatStudentResults');
    const finder = $('chatStudentSearch');
    let searchTimer = null;
    let searchSeq = 0;
    const openNew = () => {
      if (typeof openModal === 'function') openModal('chatNewModal');
      setTimeout(() => finder.focus(), 120);
    };
    document.querySelectorAll('[data-chat-new], #chatNewBtn').forEach((b) => b.addEventListener('click', openNew));

    finder.addEventListener('input', () => {
      clearTimeout(searchTimer);
      const q = finder.value.trim();
      if (q.length < 2) { results.innerHTML = '<p class="chat-student-hint">Type at least 2 characters.</p>'; return; }
      searchTimer = setTimeout(() => {
        const seq = ++searchSeq;
        fetch(API + '?action=students&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
          .then((r) => r.json())
          .then((res) => {
            if (seq !== searchSeq || !res || res.status !== 'success') return;
            if (!res.students.length) { results.innerHTML = '<p class="chat-student-hint">No active student matches “' + esc(q) + '”.</p>'; return; }
            results.innerHTML = res.students.map((s) =>
              '<button type="button" class="chat-list-item" data-student="' + s.student_id + '">'
              + '<div class="avatar">' + esc(s.initials) + '</div>'
              + '<div class="msg-item-text"><div class="msg-item-name">' + esc(s.name) + '</div>'
              + '<div class="msg-item-preview">' + esc(s.student_no) + ' · ' + esc(s.program || '') + '</div></div>'
              + (s.conversation_id ? '<span class="badge-tag badge-blue">Open chat</span>' : '')
              + '</button>').join('');
          })
          .catch(() => {});
      }, 250);
    });

    function startWithStudent(studentId) {
      return post({ action: 'open', student_id: String(studentId) }).then((res) => {
        if (res.status !== 'success') {
          if (typeof showToast === 'function') showToast(esc(res.message || 'Could not open the conversation.'), 'error', 'Messages');
          return;
        }
        if (typeof closeModal === 'function') closeModal('chatNewModal');
        finder.value = '';
        results.innerHTML = '<p class="chat-student-hint">Start typing to search.</p>';
        openConversation(res.conversation.id, res.conversation);
      });
    }
    results.addEventListener('click', (e) => {
      const item = e.target.closest('[data-student]');
      if (item) startWithStudent(parseInt(item.dataset.student, 10));
    });

    if (!IS_WIDGET) {
      // Deep links: ?conv=ID, ?student=ID, ?new=1
      const openConv = parseInt(app.dataset.openConv, 10);
      const openStudent = parseInt(app.dataset.openStudent, 10);
      if (openConv > 0) openConversation(openConv);
      else if (openStudent > 0) { restart(false); startWithStudent(openStudent); }
      else { state.autoOpen = app.dataset.openNew !== '1'; restart(false); }
      if (app.dataset.openNew === '1') openNew();
    }
  } else if (!IS_WIDGET) {
    restart(true);
  }

  // ------------------------------------------------------------------ floating widget: open / minimize
  if (IS_WIDGET) {
    const widget = document.getElementById('chatWidget');
    const panel = document.getElementById('chatPanel');
    const fab = document.getElementById('chatFab');
    const closeBtn = document.getElementById('chatPanelClose');

    /** Open the panel. Admins always land on the conversation list unless a conversation is named. */
    function openWidget(convId) {
      const wasOpen = panelOpen;
      panelOpen = true;
      widget.classList.add('open');
      panel.setAttribute('aria-hidden', 'false');
      fab.setAttribute('aria-expanded', 'true');
      if (IS_ADMIN) {
        if (convId > 0) { openConversation(convId); return; }
        if (state.conv !== 0) closeConversation(false);
        restart(false);
        if (window.matchMedia('(min-width: 641px)').matches) setTimeout(() => els.search.focus({ preventScroll: true }), 180);
      } else if (!wasOpen) {
        state.messages = [];
        restart(true);
        setTimeout(() => els.input.focus({ preventScroll: true }), 180);
      }
    }

    function closeWidget() {
      if (!panelOpen) return;
      panelOpen = false;
      stopTyping();
      stopLive();
      widget.classList.remove('open');
      panel.setAttribute('aria-hidden', 'true');
      fab.setAttribute('aria-expanded', 'false');
      // Next time the panel opens it starts from the conversation list
      if (IS_ADMIN && state.conv !== 0) setTimeout(() => { if (!panelOpen) closeConversation(false); }, 260);
      fab.focus({ preventScroll: true });
    }

    fab.addEventListener('click', () => (panelOpen ? closeWidget() : openWidget()));
    closeBtn.addEventListener('click', closeWidget);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && panelOpen && !document.querySelector('.modal-backdrop.active')) closeWidget();
    });
    window.ICMSChat = { open: openWidget, close: closeWidget, isOpen: () => panelOpen };
  }

  // ------------------------------------------------------------------ visibility: read receipts follow what the person actually sees
  document.addEventListener('visibilitychange', () => {
    if (!panelOpen) return;
    if (!document.hidden) restart(false);
    else stopTyping();
  });
  window.addEventListener('pagehide', () => {
    loopGen++;
    if (controller) controller.abort();
    if (typingActive && navigator.sendBeacon) {
      const fd = new FormData();
      fd.append('action', 'typing'); fd.append('conv', convParam()); fd.append('typing', '0'); fd.append('csrf_token', csrf);
      navigator.sendBeacon(API, fd);
    }
  });
  // Refresh "Active 5m ago" labels and list times every minute
  setInterval(() => {
    renderList();
    if (state.peer && state.conv && !state.peer.online) {
      els.headStatus.textContent = IS_ADMIN ? lastSeenLabel(state.peer.last_seen) : els.headStatus.textContent;
    }
  }, 60000);
});
