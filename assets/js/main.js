// assets/js/main.js - Global UI interactions

document.addEventListener('DOMContentLoaded', () => {
  // Modals declared inside page content would be trapped in its stacking context
  // (below the sidebar and top bar). Move them to <body> so they always cover the screen.
  document.querySelectorAll('.modal-backdrop').forEach((m) => {
    if (m.parentElement !== document.body) document.body.appendChild(m);
  });

  // Mobile sidebar toggle
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebar = document.getElementById('appSidebar');

  const overlay = document.getElementById('sidebarOverlay');
  const setSidebar = (open) => {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    if (overlay) overlay.classList.toggle('show', open);
  };
  if (mobileMenuBtn && sidebar) {
    mobileMenuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      setSidebar(!sidebar.classList.contains('open'));
    });
    if (overlay) overlay.addEventListener('click', () => setSidebar(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setSidebar(false); });
  }

  // Star Rating Interactive Widget (Figures 3 & 13)
  const starContainer = document.querySelector('.star-rating');
  if (starContainer) {
    const stars = starContainer.querySelectorAll('.star');
    const ratingInput = document.getElementById('ratingValue');

    stars.forEach(star => {
      star.addEventListener('click', () => {
        const val = parseInt(star.getAttribute('data-value'), 10);
        if (ratingInput) ratingInput.value = val;
        
        stars.forEach(s => {
          const sVal = parseInt(s.getAttribute('data-value'), 10);
          if (sVal <= val) {
            s.classList.add('selected');
          } else {
            s.classList.remove('selected');
          }
        });
      });
    });
  }
});

// Notification Dropdown Helpers
function toggleNotificationDropdown(e) {
  if (e) e.stopPropagation();
  const dropdown = document.getElementById('notificationDropdown');
  if (dropdown) {
    dropdown.classList.toggle('active');
  }
}

function notifApiUrl() {
  const wrap = document.querySelector('[data-notif-api]');
  return wrap ? wrap.getAttribute('data-notif-api') : null;
}

function notifPost(params) {
  const url = notifApiUrl();
  if (!url) return Promise.resolve(null);
  const body = new FormData();
  Object.entries(params).forEach(([k, v]) => body.append(k, v));
  return fetch(url, {
    method: 'POST',
    body,
    credentials: 'same-origin',
    headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
  }).then(r => r.json()).catch(() => null);
}

function setUnreadCount(count) {
  const badge = document.getElementById('notifBadgeCount');
  if (badge) {
    badge.textContent = count;
    badge.style.display = count > 0 ? '' : 'none';
  }
  const unreadBadge = document.getElementById('notifUnreadBadge');
  if (unreadBadge) unreadBadge.textContent = count + ' New';
}

function markAllNotificationsRead(e) {
  if (e) e.stopPropagation();
  notifPost({ action: 'mark_all_read' }).then(res => {
    if (!res || res.status !== 'success') return;
    document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
    setUnreadCount(0);
    showToast('All notifications marked as read.', 'success', 'Notifications');
  });
}

// Mark a single notification read before following its link
document.addEventListener('click', (e) => {
  const item = e.target.closest('.notification-item[data-notif-id]');
  if (!item) return;
  const href = item.getAttribute('href');
  e.preventDefault();
  notifPost({ action: 'mark_read', id: item.getAttribute('data-notif-id') }).finally(() => {
    if (href && href !== '#') window.location.href = href;
    else { item.classList.remove('unread'); }
  });
});

// Poll unread count so new responses / RRT alerts appear without a reload
(function pollNotifications() {
  const url = notifApiUrl();
  if (!url) return;
  setInterval(() => {
    if (document.hidden) return;
    fetch(url, { credentials: 'same-origin' })
      .then(r => r.json())
      .then(res => {
        if (!res || res.status !== 'success') return;
        const badge = document.getElementById('notifBadgeCount');
        const prev = badge ? parseInt(badge.textContent || '0', 10) : 0;
        setUnreadCount(res.unread);
        if (res.unread > prev && res.latest) {
          showToast(res.latest.message, res.latest.type === 'rrt' ? 'error' : 'info', res.latest.title);
        }
      })
      .catch(() => {});
  }, 30000);
})();

// Close notification dropdown when clicking outside or pressing Escape
document.addEventListener('click', (e) => {
  const dropdown = document.getElementById('notificationDropdown');
  const btn = document.getElementById('notificationBtn');
  if (dropdown && dropdown.classList.contains('active')) {
    if (!dropdown.contains(e.target) && (!btn || !btn.contains(e.target))) {
      dropdown.classList.remove('active');
    }
  }
});

// Modal helpers
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('active');
    // Only restore scroll if no other modals are active
    if (!document.querySelector('.modal-backdrop.active')) {
      document.body.style.overflow = '';
    }
  }
}

function closeAllModals() {
  document.querySelectorAll('.modal-backdrop.active').forEach(m => {
    m.classList.remove('active');
  });
  document.body.style.overflow = '';
}

// Global Keyboard Listener for Modals & Dropdowns (Escape key)
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeAllModals();
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) dropdown.classList.remove('active');
  }
});

// Universal Action Confirmation Modal
function showConfirmModal(options) {
  const modal = document.getElementById('confirmationModal');
  if (!modal) {
    if (confirm(options.message || 'Are you sure you want to proceed?')) {
      if (options.actionUrl) window.location.href = options.actionUrl;
      if (typeof options.onConfirm === 'function') options.onConfirm();
    }
    return;
  }

  const titleEl = document.getElementById('confirmModalTitle');
  const msgEl = document.getElementById('confirmModalMessage');
  const btnEl = document.getElementById('confirmModalActionBtn');
  const iconWrap = document.getElementById('confirmModalIconWrap');

  if (titleEl) titleEl.textContent = options.title || 'Confirm Action';
  if (msgEl) msgEl.innerHTML = options.message || 'Are you sure you want to proceed? This cannot be undone.';

  if (btnEl) {
    btnEl.textContent = options.confirmText || 'Confirm';
    btnEl.className = 'btn ' + (options.btnClass || 'btn-primary');

    const type = options.type || 'danger';
    if (type === 'danger') {
      btnEl.style.backgroundColor = '#dc2626';
      btnEl.style.borderColor = '#dc2626';
      btnEl.style.color = '#ffffff';
    } else if (type === 'warning') {
      btnEl.style.backgroundColor = '#d97706';
      btnEl.style.borderColor = '#d97706';
      btnEl.style.color = '#ffffff';
    } else {
      btnEl.style.backgroundColor = '#2563eb';
      btnEl.style.borderColor = '#2563eb';
      btnEl.style.color = '#ffffff';
    }

    if (options.actionUrl) {
      btnEl.href = options.actionUrl;
      btnEl.onclick = null;
    } else if (typeof options.onConfirm === 'function') {
      btnEl.href = 'javascript:void(0)';
      btnEl.onclick = function(e) {
        e.preventDefault();
        closeModal('confirmationModal');
        options.onConfirm();
      };
    }
  }

  if (iconWrap) {
    const type = options.type || 'danger';
    iconWrap.className = 'confirm-icon-wrap ' + 
      (type === 'warning' ? 'confirm-icon-warning' : (type === 'info' ? 'confirm-icon-info' : 'confirm-icon-danger'));
  }

  openModal('confirmationModal');
}

// Global data-confirm listener for links and buttons
document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-confirm]');
  if (trigger) {
    e.preventDefault();
    const message = trigger.getAttribute('data-confirm');
    const title = trigger.getAttribute('data-confirm-title') || 'Confirm Action';
    const confirmText = trigger.getAttribute('data-confirm-text') || 'Confirm';
    const btnClass = trigger.getAttribute('data-confirm-btn') || 'btn-primary';
    const type = trigger.getAttribute('data-confirm-type') || 'danger';
    const actionUrl = trigger.getAttribute('href');

    const form = trigger.closest('form');
    if ((!actionUrl || actionUrl === '#' || actionUrl.startsWith('javascript:')) && form) {
      showConfirmModal({
        title,
        message,
        confirmText,
        btnClass,
        type,
        onConfirm: () => form.submit()
      });
      return;
    }

    showConfirmModal({
      title,
      message,
      confirmText,
      btnClass,
      type,
      actionUrl
    });
  }
});

// Toast Notification System (Top-Right Upper Corner)
function showToast(message, type = 'success', title = '', duration = 4500) {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const defaultTitles = {
    success: 'Success',
    error: 'Error Notice',
    warning: 'Attention Needed',
    info: 'Information'
  };

  const icons = {
    success: `<svg class="svg-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
    error: `<svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    warning: `<svg class="svg-icon" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
    info: `<svg class="svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`
  };

  const toastType = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
  const toastTitle = title || defaultTitles[toastType];

  const toast = document.createElement('div');
  toast.className = `toast toast-${toastType}`;

  toast.innerHTML = `
    <div class="toast-icon-wrap">
      ${icons[toastType]}
    </div>
    <div class="toast-content">
      <div class="toast-title">${toastTitle}</div>
      <div class="toast-message">${message}</div>
    </div>
    <button type="button" class="toast-close" title="Dismiss" aria-label="Close">&times;</button>
    <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
  `;

  container.appendChild(toast);

  let dismissTimeout = setTimeout(() => hideToast(toast), duration);

  // Pause progress & dismiss on hover
  toast.addEventListener('mouseenter', () => {
    clearTimeout(dismissTimeout);
    const prog = toast.querySelector('.toast-progress');
    if (prog) prog.style.animationPlayState = 'paused';
  });

  toast.addEventListener('mouseleave', () => {
    const prog = toast.querySelector('.toast-progress');
    if (prog) prog.style.animationPlayState = 'running';
    dismissTimeout = setTimeout(() => hideToast(toast), 2200);
  });

  const closeBtn = toast.querySelector('.toast-close');
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      clearTimeout(dismissTimeout);
      hideToast(toast);
    });
  }
}

function hideToast(toast) {
  if (!toast || toast.classList.contains('toast-hiding')) return;
  toast.classList.add('toast-hiding');
  toast.addEventListener('animationend', () => {
    toast.remove();
  }, { once: true });
}

// ---------------------------------------------------------------------------
// Idle session timeout: warn 2 minutes before sign-out; keep the session alive
// while the person is actually typing or clicking (not while the tab sits idle).
// ---------------------------------------------------------------------------
(function idleSession() {
  const meta = document.querySelector('meta[name="session-timeout"]');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!meta || !csrf) return;

  const timeoutMs = parseInt(meta.content, 10) * 1000;
  const warnBeforeMs = 2 * 60 * 1000;
  const apiBase = (location.pathname.includes('/admin/') || location.pathname.includes('/student/')) ? '../api/' : 'api/';
  let lastServerTouch = Date.now();
  let lastActivity = Date.now();
  let warned = false;

  function ping() {
    lastServerTouch = Date.now();
    const body = new FormData();
    body.append('csrf_token', csrf);
    fetch(apiBase + 'keepalive.php', { method: 'POST', body, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf } })
      .then((r) => { if (r.status === 401) location.reload(); })
      .catch(() => {});
  }

  ['keydown', 'mousedown', 'touchstart', 'scroll'].forEach((ev) => {
    document.addEventListener(ev, () => {
      lastActivity = Date.now();
      if (warned) {
        warned = false;
        ping();
        showToast('You are still signed in.', 'success', 'Session extended');
        return;
      }
      // Activity refreshes the server session at most every 5 minutes
      if (Date.now() - lastServerTouch > 5 * 60 * 1000) ping();
    }, { passive: true });
  });

  setInterval(() => {
    const idleFor = Date.now() - Math.max(lastActivity, lastServerTouch);
    if (!warned && idleFor > timeoutMs - warnBeforeMs) {
      warned = true;
      showToast('You will be signed out in 2 minutes because of inactivity. Move the mouse or press a key to stay signed in.', 'warning', 'Session expiring', 15000);
    }
    if (idleFor > timeoutMs + 5000) {
      location.reload(); // the server sends the person to the sign-in page with a notice
    }
  }, 15000);
})();

/* =====================================================================
   UI enhancements: clickable rows, inline form validation, loading
   buttons, password strength and character counters
   ===================================================================== */
(function uiEnhancements() {
  const ERR_ICON = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

  // ---- Clickable table rows: <tr data-href="..."> ----
  document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-href]');
    if (!row || e.target.closest('a, button, input, select, textarea, label, form, summary')) return;
    if (window.getSelection && String(window.getSelection()).length) return; // let people copy text
    if (e.metaKey || e.ctrlKey) { window.open(row.dataset.href, '_blank'); return; }
    window.location.href = row.dataset.href;
  });

  // ---- Inline validation (POST forms) ----
  const enhanced = (form) => form.method.toLowerCase() === 'post' && !form.hasAttribute('data-no-enhance') && form.id !== 'loginForm';

  function messageFor(el) {
    const v = el.validity;
    if (v.customError) return el.validationMessage;
    if (v.valueMissing) {
      if (el.type === 'radio') return 'Please choose one.';
      if (el.type === 'checkbox') return 'Please tick this box to continue.';
      if (el.type === 'file') return 'Please choose a file.';
      return el.tagName === 'SELECT' ? 'Please choose an option.' : 'This field is required.';
    }
    if (v.typeMismatch) return el.type === 'email' ? 'Enter a valid email address, e.g. name@gmail.com.' : 'Enter a valid value.';
    if (v.tooShort) return `Use at least ${el.minLength} characters (now ${el.value.length}).`;
    if (v.tooLong) return `Use at most ${el.maxLength} characters.`;
    if (v.patternMismatch) return el.title || 'Use the format shown in the example.';
    if (v.rangeUnderflow || v.rangeOverflow) return `Enter a value from ${el.min || '…'} to ${el.max || '…'}.`;
    if (v.stepMismatch) return 'Use at most 2 decimal places.';
    if (v.badInput) return 'Enter a number.';
    return el.validationMessage || 'Please check this field.';
  }

  function errorAnchor(el) {
    if (el.type === 'radio' || el.type === 'checkbox') return el.closest('.form-group') || el.closest('label') || el;
    return el.closest('.input-icon-wrap') || el.closest('.file-inline') || el;
  }

  function clearError(el) {
    el.classList.remove('is-invalid');
    el.removeAttribute('aria-invalid');
    const anchor = errorAnchor(el);
    const group = el.closest('.form-group');
    if (group) group.classList.remove('has-error');
    const next = (anchor.classList && anchor.classList.contains('form-group')) ? anchor.querySelector(':scope > .field-error') : anchor.nextElementSibling;
    if (next && next.classList && next.classList.contains('field-error')) next.remove();
  }

  function showError(el, msg) {
    clearError(el);
    el.classList.add('is-invalid');
    el.setAttribute('aria-invalid', 'true');
    const box = document.createElement('div');
    box.className = 'field-error';
    box.setAttribute('role', 'alert');
    box.innerHTML = ERR_ICON + '<span></span>';
    box.querySelector('span').textContent = msg;
    const anchor = errorAnchor(el);
    if (anchor.classList && anchor.classList.contains('form-group')) {
      anchor.classList.add('has-error');
      anchor.appendChild(box);
    } else {
      anchor.insertAdjacentElement('afterend', box);
    }
  }

  // Confirm fields: <input data-match="#otherId">
  function checkMatch(el) {
    if (!el.dataset.match) return;
    const other = document.querySelector(el.dataset.match);
    el.setCustomValidity(other && el.value && other.value !== el.value ? 'Passwords do not match.' : '');
  }

  function validate(form) {
    let first = null;
    const seenRadio = new Set();
    form.querySelectorAll('input, select, textarea').forEach((el) => {
      if (el.disabled || el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return;
      checkMatch(el);
      if (el.type === 'radio') {
        if (seenRadio.has(el.name)) return;
        seenRadio.add(el.name);
      }
      if (el.checkValidity()) { clearError(el); return; }
      showError(el, messageFor(el));
      first = first || el;
    });
    if (first) {
      const target = first.type === 'radio' ? first.closest('.form-group') || first : first;
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => { try { first.focus({ preventScroll: true }); } catch (e) {} }, 250);
    }
    return !first;
  }

  // Capture phase: runs before page-specific submit handlers, so an invalid form never reaches them
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !enhanced(form)) return;
    if (e.submitter && e.submitter.hasAttribute('formnovalidate')) return;
    if (!validate(form)) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }, true);

  // Bubble phase: valid submission -> show a loading state on the button (once)
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement) || !enhanced(form)) return;
    const btn = e.submitter || form.querySelector('button[type="submit"], button:not([type])');
    if (!btn || btn.hasAttribute('data-no-loading') || btn.classList.contains('is-loading')) return;
    if (btn.disabled) return; // the page already handles it
    const width = btn.offsetWidth;
    if (btn.name) { // keep the clicked button's value in the request
      const keep = document.createElement('input');
      keep.type = 'hidden'; keep.name = btn.name; keep.value = btn.value;
      form.appendChild(keep);
    }
    btn.style.minWidth = width + 'px';
    btn.classList.add('is-loading');
    btn.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span> ' + (btn.dataset.loadingText || 'Please wait...');
    setTimeout(() => { btn.disabled = true; }, 0);
  });

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach((form) => {
      if (!enhanced(form)) return;
      form.noValidate = true; // we show our own messages instead of the browser bubbles
      form.addEventListener('input', (e) => {
        const el = e.target;
        if (el.dataset && el.dataset.match) checkMatch(el);
        form.querySelectorAll('[data-match]').forEach(checkMatch);
        if (el.classList && el.classList.contains('is-invalid') && el.checkValidity()) clearError(el);
      });
      form.addEventListener('change', (e) => {
        const el = e.target;
        if (el.type === 'radio') form.querySelectorAll(`input[name="${CSS.escape(el.name)}"]`).forEach(clearError);
        else if (el.classList && el.classList.contains('is-invalid') && el.checkValidity()) clearError(el);
      });
    });

    // ---- Password strength: <input data-strength> ----
    document.querySelectorAll('input[type="password"][data-strength]').forEach((input) => {
      const meter = document.createElement('div');
      meter.className = 'pw-meter';
      meter.dataset.level = '0';
      meter.innerHTML = '<div class="pw-meter-bars"><span></span><span></span><span></span><span></span></div>'
        + '<div class="pw-meter-row"><span class="pw-meter-label">Password strength</span><span class="pw-rules">'
        + '<span data-rule="len">8+ characters</span><span data-rule="letter">a letter</span><span data-rule="digit">a number</span></span></div>';
      (input.closest('.input-icon-wrap') || input).insertAdjacentElement('afterend', meter);
      const label = meter.querySelector('.pw-meter-label');
      const update = () => {
        const v = input.value;
        const rules = { len: v.length >= 8, letter: /[A-Za-z]/.test(v), digit: /\d/.test(v) };
        Object.entries(rules).forEach(([k, ok]) => meter.querySelector(`[data-rule="${k}"]`).classList.toggle('ok', ok));
        let level = 0;
        if (v) {
          level = 1;
          if (rules.len && rules.letter && rules.digit) level = 2;
          if (level === 2 && (v.length >= 12 || (/[^A-Za-z0-9]/.test(v) && /[a-z]/.test(v) && /[A-Z]/.test(v)))) level = 3;
          if (level >= 2 && v.length >= 12 && /[^A-Za-z0-9]/.test(v) && /[a-z]/.test(v) && /[A-Z]/.test(v)) level = 4;
        }
        meter.dataset.level = String(level);
        label.textContent = ['Password strength', 'Too weak', 'Good', 'Strong', 'Very strong'][level];
      };
      input.addEventListener('input', update);
      update();
    });

    // ---- Character counters for any textarea with maxlength (unless it already has one) ----
    document.querySelectorAll('textarea[maxlength]:not([data-counter]):not([data-no-counter])').forEach((ta) => {
      const max = parseInt(ta.getAttribute('maxlength'), 10);
      if (!max) return;
      const out = document.createElement('small');
      out.className = 'char-auto-counter';
      ta.insertAdjacentElement('afterend', out);
      const update = () => {
        out.textContent = `${ta.value.length} / ${max}`;
        out.classList.toggle('near-limit', ta.value.length > max * 0.9);
      };
      ta.addEventListener('input', update);
      update();
    });
  });
})();
