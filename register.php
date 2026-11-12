<?php
// register.php - Student self-registration (Use case: Student → Register)
//
// Two separate paths:
//   1. Find your name  - search the Registrar's masterlist. Picking a name pops up the record
//                        (college, program, email); the student confirms their student number and
//                        creates a password (actions/register_claim.php). Active right away.
//   2. Register manually - for students not on the masterlist. Full form (actions/register_action.php);
//                        the account waits for administrator approval.
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth_check.php';

if (isLoggedIn()) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit;
}

// Re-fill the manual form after a validation error (passwords are never re-filled)
$old = $_SESSION['register_old'] ?? [];
unset($_SESSION['register_old']);
$startManual = !empty($old) || isset($_GET['manual']);

$pageTitle      = 'Create Student Account | ICMS-DPT-RRT';
$authHeading    = 'Create Student Account';
$authSubheading = 'Register to submit and track your concerns';
$authTall       = true;
require __DIR__ . '/includes/auth_layout_start.php';
?>
    <style>
      #searchView[hidden], #registerForm[hidden], .claim-email-row[hidden], .claim-email-input[hidden] { display: none; }
      .reg-step { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 700; color: var(--brand-blue); background: var(--brand-blue-light, #eef4ff); border-radius: 999px; padding: 3px 10px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.05em; }
      .reg-search-input { padding-left: 40px; height: 48px; font-size: 1rem; }
      .reg-search-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; display: flex; }
      .reg-or { display: flex; align-items: center; gap: 10px; margin: 22px 0 14px; color: var(--text-muted); font-size: 0.78rem; }
      .reg-or::before, .reg-or::after { content: ""; flex: 1; height: 1px; background: var(--border-subtle); }
      .reg-manual-card { border: 1px dashed var(--border-color); border-radius: 10px; padding: 14px; background: var(--bg-subtle); }
      .reg-back { display: inline-flex; align-items: center; gap: 4px; font-size: 0.84rem; font-weight: 600; color: var(--brand-blue); background: none; border: 0; padding: 0; cursor: pointer; margin-bottom: 12px; }
      .claim-pwd-step[hidden], #claimNoHelp[hidden] { display: none; }
      #claimNo.is-valid { border-color: #16a34a; background: #f0fdf4; cursor: pointer; }
      .claim-verify-row { display: flex; gap: 8px; }
      .claim-verify-row .form-control { flex: 1; min-width: 0; }
      .claim-verified { display: none; align-items: center; gap: 6px; margin-top: 6px; font-size: 0.8rem; font-weight: 600; color: #15803d; }
      .claim-verified.show { display: flex; }
      .claim-pwd-step { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-subtle); }
      /* Manual registration: wider pane + sectioned form */
      .login-container.auth-wide { max-width: 1060px; }
      .login-container.auth-wide .login-hero { flex: 0.72; }
      .login-container.auth-wide .login-form-pane { flex: 1.28; padding: 36px 40px; }
      .reg-manual-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
      .reg-manual-top .reg-back, .reg-manual-top .reg-step { margin-bottom: 0; }
      .reg-section { margin-bottom: 18px; }
      .reg-section-title { display: flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 700; color: var(--text-primary); margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid var(--border-subtle); }
      .reg-num { width: 20px; height: 20px; border-radius: 50%; background: var(--brand-blue); color: #fff; font-size: 0.7rem; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
      .reg-section .form-group { margin-bottom: 12px; }
      .reg-section .form-row-2 .form-group { margin-bottom: 12px; }
      .reg-section .form-control { min-width: 0; }
      .reg-section select.form-control { text-overflow: ellipsis; }
      @media (max-width: 768px) { .login-container.auth-wide .login-form-pane { padding: 28px 20px; } }
      .reg-note { margin: 4px 0 14px; display: flex; gap: 8px; align-items: flex-start; font-size: 0.8rem; padding: 10px 12px; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    </style>

    <!-- ===== Path 1: find your name on the masterlist ===== -->
    <div id="searchView" <?= $startManual ? 'hidden' : '' ?>>
      <span class="reg-step">Step 1 · Find your record</span>
      <div class="form-group">
        <label class="form-label" for="findName">Search your name</label>
        <div class="name-search">
          <span class="reg-search-icon"><?= icon('search', '', 18) ?></span>
          <input type="text" id="findName" class="form-control reg-search-input" placeholder="Type your name, e.g. Juan Dela Cruz"
                 maxlength="150" autocomplete="off" <?= $startManual ? '' : 'autofocus' ?>
                 role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="nameResults">
          <div class="name-search-menu" id="nameResultsWrap" hidden>
            <div class="name-search-head">On the Registrar's masterlist</div>
            <ul class="name-search-list" id="nameResults" role="listbox" aria-label="Matching students"></ul>
            <div class="name-search-foot" id="nameResultsFoot"></div>
          </div>
        </div>
        <span class="form-help">If the school already has your record, select your name. Your details will appear and you only need to create a password.</span>
      </div>

      <div class="reg-or">or</div>

      <div class="reg-manual-card">
        <div class="text-sm" style="font-weight: 600; margin-bottom: 4px;">Your name is not on the list?</div>
        <p class="text-xs text-muted" style="margin: 0 0 10px;">Register manually. An administrator will verify your account before you can sign in.</p>
        <button type="button" class="btn btn-secondary" style="width: 100%;" id="goManual">Register manually</button>
      </div>

      <div class="auth-links">
        Already have an account? <a href="index.php">Sign in</a>
      </div>
    </div>

    <!-- ===== Path 2: manual registration (not on the masterlist) ===== -->
    <form action="actions/register_action.php" method="POST" id="registerForm" novalidate <?= $startManual ? '' : 'hidden' ?>>
      <?= csrfField() ?>
      <div class="reg-manual-top">
        <button type="button" class="reg-back" id="goSearch">&larr; Back to name search</button>
        <span class="reg-step">Manual registration</span>
      </div>

      <div class="reg-note">
        <span style="flex-shrink: 0; margin-top: 1px;"><?= icon('info', '', 14) ?></span>
        <span>Use this only if your name is not on the Registrar's list. Your account stays <strong>Pending</strong> until an administrator approves it.</span>
      </div>

      <div class="reg-section">
        <div class="reg-section-title"><span class="reg-num">1</span> Personal information</div>
        <div class="form-group">
          <label class="form-label" for="regName">Full Name <span class="required">*</span></label>
          <input type="text" name="name" id="regName" class="form-control" placeholder="Juan P. Dela Cruz"
                 value="<?= e($old['name'] ?? '') ?>" required maxlength="150" autocomplete="name">
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="regStudentNo">Student Number <span class="required">*</span></label>
            <input type="text" name="student_no" id="regStudentNo" class="form-control" placeholder="SNSU-2025-01234"
                   value="<?= e($old['student_no'] ?? '') ?>" required maxlength="50" pattern="[A-Za-z0-9\-]{4,50}" title="Use letters, numbers and dashes only, e.g. SNSU-2025-01234.">
          </div>
          <div class="form-group">
            <label class="form-label" for="regEmail">Email Address <span class="required">*</span></label>
            <input type="email" name="email" id="regEmail" class="form-control" placeholder="you@gmail.com"
                   value="<?= e($old['email'] ?? '') ?>" required maxlength="150" autocomplete="email">
          </div>
        </div>
      </div>

      <div class="reg-section">
        <div class="reg-section-title"><span class="reg-num">2</span> School information</div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="regDepartment">College / Department <span class="required">*</span></label>
            <select name="department" id="regDepartment" class="form-control" required>
              <?php foreach (SNSU_DEPARTMENTS as $dept): ?>
                <option value="<?= e($dept) ?>" <?= ($old['department'] ?? '') === $dept ? 'selected' : '' ?>><?= e($dept) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="regProgram">Course / Program <span class="required">*</span></label>
            <input type="text" name="program" id="regProgram" class="form-control" placeholder="BS Information Technology"
                   value="<?= e($old['program'] ?? '') ?>" required maxlength="150">
          </div>
        </div>
      </div>

      <div class="reg-section">
        <div class="reg-section-title"><span class="reg-num">3</span> Account password</div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label" for="regPassword">Password <span class="required">*</span></label>
            <input type="password" name="password" id="regPassword" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password" data-strength>
          </div>
          <div class="form-group">
            <label class="form-label" for="regConfirm">Confirm Password <span class="required">*</span></label>
            <input type="password" name="password_confirm" id="regConfirm" class="form-control" required minlength="8" maxlength="72" autocomplete="new-password" data-match="#regPassword">
          </div>
        </div>
        <span class="form-help" style="margin-top: 0;">At least 8 characters with a letter and a number.</span>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 8px;">
        <span>Submit for Approval</span>
      </button>

      <div class="auth-links">
        Already have an account? <a href="index.php">Sign in</a>
      </div>
    </form>

    <!-- Selected student: record pops up (partly masked: this page is public) -->
    <div class="modal-backdrop" id="accountModal" onclick="if(event.target === this) closeModal('accountModal')">
      <div class="modal-content" role="dialog" aria-labelledby="acTitle" style="max-width: 480px;">
        <div class="modal-header">
          <div>
            <h3 class="card-title" id="acTitle">Your Record</h3>
            <div class="card-subtitle" id="acSubtitle"></div>
          </div>
          <button type="button" class="alert-close" onclick="closeModal('accountModal')" aria-label="Close">&times;</button>
        </div>

        <!-- View 1: on the masterlist, no account yet -> create a password -->
        <form id="claimForm" novalidate>
          <input type="hidden" name="claim_ref" id="claimRef" value="">
          <div class="modal-body">
            <div class="person" style="margin-bottom: 12px;">
              <div class="avatar avatar-lg" data-ac="avatar"></div>
              <div style="min-width: 0;">
                <div class="person-name" data-ac="name" style="font-size: 1.05rem;"></div>
                <div style="margin-top: 4px;"><span class="badge-tag badge-dot badge-blue">Found on the masterlist</span></div>
              </div>
            </div>

            <div class="name-matches-section-label">Your details from the school record</div>
            <div class="kv-list" style="margin-bottom: 14px;">
              <div class="kv-row"><span class="kv-key">Student number</span><span class="kv-val mono" data-ac="no"></span></div>
              <div class="kv-row"><span class="kv-key">College</span><span class="kv-val" data-ac="department"></span></div>
              <div class="kv-row"><span class="kv-key">Course / Program</span><span class="kv-val" data-ac="program"></span></div>
              <div class="kv-row claim-email-row"><span class="kv-key">Email (sign-in)</span><span class="kv-val" data-ac="email"></span></div>
            </div>

            <div class="claim-alert" id="claimError" role="alert" hidden></div>

            <div class="name-matches-section-label" style="margin-bottom: 8px;">Confirm it's you</div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" for="claimNo">Your student number <span class="required">*</span></label>
              <div class="claim-verify-row">
                <input type="text" name="student_no" id="claimNo" class="form-control mono" required maxlength="50" autocomplete="off">
              </div>
              <span class="form-help" id="claimNoHelp">So nobody else can claim your record. It starts with <strong class="mono" data-ac="no"></strong></span>
              <div class="claim-verified" id="claimVerified"><?= icon('check', '', 15) ?> Student number verified</div>
            </div>

            <div class="claim-pwd-step" id="claimPwdStep" hidden>
              <div class="name-matches-section-label" style="margin-bottom: 8px;">Create your password</div>
              <div class="form-group claim-email-input">
                <label class="form-label" for="claimEmail">Email address <span class="required">*</span></label>
                <input type="email" name="email" id="claimEmail" class="form-control" maxlength="150" placeholder="you@gmail.com" autocomplete="email">
                <span class="form-help">Your record has no email yet. It is used to sign in and to reset your password.</span>
              </div>
              <div class="form-row-2">
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" for="claimPassword">Password <span class="required">*</span></label>
                  <input type="password" name="password" id="claimPassword" class="form-control" minlength="8" maxlength="72" autocomplete="new-password">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" for="claimConfirm">Confirm password <span class="required">*</span></label>
                  <input type="password" name="password_confirm" id="claimConfirm" class="form-control" minlength="8" maxlength="72" autocomplete="new-password">
                </div>
              </div>
              <span class="form-help">At least 8 characters with a letter and a number.</span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('accountModal')">Not me</button>
            <button type="submit" class="btn btn-primary" id="claimSubmit">Verify Student Number</button>
          </div>
        </form>

        <!-- View 2: already has an account -> recover it -->
        <div id="existingView">
          <div class="modal-body">
            <div class="person" style="margin-bottom: 14px;">
              <div class="avatar avatar-lg" data-ac="avatar"></div>
              <div style="min-width: 0;">
                <div class="person-name" data-ac="name" style="font-size: 1.05rem;"></div>
                <div style="margin-top: 4px;"><span class="badge-tag badge-dot" id="acState"></span></div>
              </div>
            </div>
            <div class="name-matches-section-label">Current credentials</div>
            <div class="kv-list">
              <div class="kv-row"><span class="kv-key">Student number (sign-in)</span><span class="kv-val mono" data-ac="no"></span></div>
              <div class="kv-row"><span class="kv-key">Email (sign-in)</span><span class="kv-val" data-ac="email"></span></div>
              <div class="kv-row"><span class="kv-key">Password</span><span class="kv-val">Hidden</span></div>
              <div class="kv-note">For the student's protection, details are partly hidden and passwords are never shown.</div>
            </div>
            <div class="name-matches-section-label" style="margin-top: 18px;">Set a new password</div>
            <p class="text-sm text-secondary" style="margin: 0;" id="acResetHelp"></p>
          </div>
          <div class="modal-footer">
            <a href="index.php" class="btn btn-secondary">Sign in instead</a>
            <form action="actions/password_reset_action.php" method="POST" style="margin: 0;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="request">
              <input type="hidden" name="account_ref" id="acRef" value="">
              <button type="submit" class="btn btn-primary"><?= icon('lock', '', 16) ?> Reset My Password</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script>
      (function () {
        const $ = (id) => document.getElementById(id);
        const csrf = document.querySelector('#registerForm input[name="csrf_token"]').value;

        // ---- Switch between the two paths ----
        const container = document.querySelector('.login-container');
        container.classList.toggle('auth-wide', !$('registerForm').hidden);
        function showManual() {
          container.classList.add('auth-wide');
          $('searchView').hidden = true;
          $('registerForm').hidden = false;
          const typed = $('findName').value.trim();
          if (typed && !$('regName').value) $('regName').value = typed;
          $('regName').focus();
        }
        function showSearch() {
          container.classList.remove('auth-wide');
          $('registerForm').hidden = true;
          $('searchView').hidden = false;
          $('findName').focus();
        }
        $('goManual').addEventListener('click', showManual);
        $('goSearch').addEventListener('click', showSearch);

        // ---- Live name search over the Registrar's masterlist ----
        const input = $('findName');
        const wrap = $('nameResultsWrap');
        const list = $('nameResults');
        const foot = $('nameResultsFoot');
        let matches = [], active = -1, emailReset = true, timer = null, lastQuery = null, seq = 0;

        const initials = (n) => String(n || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('');
        const el = (tag, cls, text) => { const x = document.createElement(tag); if (cls) x.className = cls; if (text != null) x.textContent = text; return x; };
        const words = (q) => q.toLowerCase().split(/[^\p{L}]+/u).filter(Boolean);
        const stateClass = (s) => s === 'Has an account' ? 'badge-green' : s === 'No account yet' ? 'badge-blue' : s === 'Deactivated' ? 'badge-gray' : 'badge-amber';

        /** The name with the typed word-starts in <mark> (built as DOM nodes, never as HTML). */
        function highlighted(name, q) {
          const ws = words(q);
          const out = el('span', 'person-name');
          const re = /[\p{L}]+|[^\p{L}]+/gu;
          let m;
          while ((m = re.exec(name))) {
            const part = m[0];
            const w = ws.find((t) => part.toLowerCase().startsWith(t));
            if (w && /\p{L}/u.test(part)) {
              out.append(el('mark', null, part.slice(0, w.length)), part.slice(w.length));
            } else {
              out.append(part);
            }
          }
          return out;
        }

        function close() {
          wrap.hidden = true;
          input.setAttribute('aria-expanded', 'false');
          input.removeAttribute('aria-activedescendant');
          active = -1;
        }
        function open() {
          wrap.hidden = false;
          input.setAttribute('aria-expanded', 'true');
        }

        function search() {
          clearTimeout(timer);
          const q = input.value.trim();
          if (words(q).join('').length < 2) { lastQuery = null; matches = []; close(); return; }
          if (q === lastQuery) { open(); return; }
          timer = setTimeout(() => {
            const mine = ++seq;
            fetch('actions/register_lookup.php?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf } })
              .then((r) => r.json())
              .then((data) => {
                if (mine !== seq) return; // a newer search is on its way
                lastQuery = q;
                if (data && data.status === 'error') { matches = []; render(q, data.message); return; }
                matches = data && data.status === 'ok' ? data.matches : [];
                emailReset = !data || data.email_reset !== false;
                render(q);
              })
              .catch(() => { if (mine === seq) close(); });
          }, 220);
        }

        function render(q, message) {
          active = -1;
          if (!matches.length) {
            list.replaceChildren();
            foot.replaceChildren(message || 'No record found with that name. Check the spelling, or ');
            if (!message) {
              const a = el('a', null, 'register manually');
              a.href = '#';
              a.addEventListener('pointerdown', (e) => { e.preventDefault(); close(); showManual(); });
              foot.append(a, '.');
            }
            if (document.activeElement === input) open(); else close();
            return;
          }
          list.replaceChildren(...matches.map((m, i) => {
            const li = el('li', 'name-search-item');
            li.id = 'nameOpt' + i;
            li.setAttribute('role', 'option');
            li.dataset.i = i;
            const text = el('span', 'name-match-text');
            text.append(highlighted(m.display_name, q), el('span', 'person-sub', m.program || ''));
            li.append(el('span', 'avatar', initials(m.display_name)), text, el('span', 'badge-tag badge-dot ' + stateClass(m.state), m.state));
            return li;
          }));
          foot.textContent = matches.length >= 8 ? 'Showing the first 8. Type more of your name to narrow it down.' : 'Select your name to see your details.';
          if (document.activeElement === input) open();
        }

        function setActive(i) {
          const items = list.querySelectorAll('.name-search-item');
          if (!items.length) return;
          active = (i + items.length) % items.length;
          items.forEach((it, k) => it.setAttribute('aria-selected', k === active ? 'true' : 'false'));
          items[active].scrollIntoView({ block: 'nearest' });
          input.setAttribute('aria-activedescendant', items[active].id);
        }

        input.addEventListener('input', search);
        input.addEventListener('focus', () => { if (lastQuery !== null && input.value.trim() === lastQuery) open(); else search(); });
        input.addEventListener('keydown', (e) => {
          if (wrap.hidden) { if (e.key === 'ArrowDown' || e.key === 'Enter') { e.preventDefault(); search(); } return; }
          if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
          else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
          else if (e.key === 'Enter') { e.preventDefault(); choose(matches[active >= 0 ? active : (matches.length === 1 ? 0 : -1)]); }
          else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
        });
        // pointerdown, so the choice lands before the input's blur closes the menu
        list.addEventListener('pointerdown', (e) => {
          const it = e.target.closest('.name-search-item');
          if (!it) return;
          e.preventDefault();
          choose(matches[+it.dataset.i]);
        });
        document.addEventListener('pointerdown', (e) => { if (!e.target.closest('.name-search')) close(); });
        input.addEventListener('blur', () => setTimeout(() => { if (!wrap.contains(document.activeElement)) close(); }, 120));

        // ---- Selecting a name pops up the record ----
        function choose(m) {
          if (!m) return;
          close();
          const set = (key, val) => document.querySelectorAll('#accountModal [data-ac="' + key + '"]').forEach((x) => { x.textContent = val; });
          set('avatar', initials(m.display_name));
          set('name', m.display_name);
          set('no', m.student_no);
          set('email', m.email || 'None');
          set('department', m.department || 'Not specified');
          set('program', m.program || 'Not specified');
          $('acSubtitle').textContent = m.program || '';
          $('claimForm').hidden = m.has_account;
          $('existingView').hidden = !m.has_account;

          if (m.has_account) {
            $('acTitle').textContent = 'Already Has an Account';
            const st = $('acState');
            st.className = 'badge-tag badge-dot ' + stateClass(m.state);
            st.textContent = m.state;
            $('acRef').value = m.ref;
            $('acResetHelp').textContent = emailReset
              ? 'We will email a one-time link to ' + m.email + '. Open it to choose a new password. Only the owner of that email can complete the reset.'
              : 'The administrator will be notified to issue you a temporary password. Coordinate with the MIS Office or your Dean to receive it.';
          } else {
            $('acTitle').textContent = 'Is This You?';
            $('claimRef').value = m.ref;
            document.querySelector('#claimForm .claim-email-row').hidden = m.needs_email;
            document.querySelector('#claimForm .claim-email-input').hidden = !m.needs_email;
            $('claimEmail').required = m.needs_email;
            $('claimEmail').value = '';
            $('claimNo').value = '';
            $('claimPassword').value = '';
            $('claimConfirm').value = '';
            setVerified(false);
            showClaimError('');
          }
          openModal('accountModal');
          setTimeout(() => (m.has_account ? null : $('claimNo').focus()), 60);
        }

        // ---- Create My Account ----
        function showClaimError(msg, field) {
          const box = $('claimError');
          box.textContent = msg;
          box.hidden = !msg;
          document.querySelectorAll('#claimForm .form-control').forEach((f) => f.classList.remove('is-invalid'));
          const map = { student_no: 'claimNo', email: 'claimEmail', password: 'claimPassword', password_confirm: 'claimConfirm' };
          if (field && map[field] && !$(map[field]).closest('[hidden]')) { $(map[field]).classList.add('is-invalid'); $(map[field]).focus(); }
        }

        // Step A: verify the student number. Step B (only after A succeeds): create the password.
        let verified = false;
        function setVerified(on) {
          verified = on;
          $('claimPwdStep').hidden = !on;
          $('claimVerified').classList.toggle('show', on);
          $('claimNoHelp').hidden = on;
          $('claimNo').readOnly = on;
          $('claimNo').classList.toggle('is-valid', on);
          $('claimSubmit').textContent = on ? 'Create My Account' : 'Verify Student Number';
        }
        // Clicking the verified number lets the student change it, which locks the password step again
        $('claimNo').addEventListener('click', () => { if (verified) { setVerified(false); $('claimNo').focus(); } });

        function post(fields) {
          const body = new FormData();
          Object.entries(fields).forEach(([k, v]) => body.append(k, v));
          body.append('csrf_token', csrf);
          return fetch('actions/register_claim.php', { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((r) => r.json());
        }

        $('claimForm').addEventListener('submit', (e) => {
          e.preventDefault();
          const btn = $('claimSubmit');
          const ref = $('claimRef').value;
          const no = $('claimNo').value.trim();
          if (!no) return showClaimError('Enter your student number to confirm it is you.', 'student_no');

          if (!verified) {
            btn.disabled = true;
            post({ action: 'verify', claim_ref: ref, student_no: no })
              .then((data) => {
                btn.disabled = false;
                if (data && data.status === 'ok') {
                  showClaimError('');
                  setVerified(true);
                  ($('claimEmail').required ? $('claimEmail') : $('claimPassword')).focus();
                  return;
                }
                showClaimError((data && data.message) || 'Something went wrong. Please try again.', 'student_no');
              })
              .catch(() => { btn.disabled = false; showClaimError('Could not reach the server. Please try again.'); });
            return;
          }

          const pwd = $('claimPassword').value;
          if ($('claimEmail').required && (!$('claimEmail').value || !$('claimEmail').checkValidity())) return showClaimError('Please enter a valid email address.', 'email');
          if (pwd.length < 8 || !/[A-Za-z]/.test(pwd) || !/\d/.test(pwd)) return showClaimError('Password must be at least 8 characters with a letter and a number.', 'password');
          if (pwd !== $('claimConfirm').value) return showClaimError('Passwords do not match.', 'password_confirm');

          const fields = { claim_ref: ref, student_no: no, password: pwd, password_confirm: $('claimConfirm').value };
          if ($('claimEmail').required) fields.email = $('claimEmail').value.trim();
          btn.disabled = true;
          post(fields)
            .then((data) => {
              if (data && data.status === 'ok') { window.location.href = data.redirect || 'index.php'; return; }
              btn.disabled = false;
              if (data && data.field === 'student_no') setVerified(false);
              showClaimError((data && data.message) || 'Something went wrong. Please try again.', data && data.field);
            })
            .catch(() => { btn.disabled = false; showClaimError('Could not reach the server. Please try again.'); });
        });

        // ---- Manual form: client-side check mirrors the server policy (server remains authoritative) ----
        $('registerForm').addEventListener('submit', function (e) {
          const pwd = $('regPassword').value;
          let msg = '';
          if (!this.checkValidity()) msg = 'Please complete all required fields correctly.';
          else if (pwd.length < 8 || !/[A-Za-z]/.test(pwd) || !/\d/.test(pwd)) msg = 'Password must be at least 8 characters with a letter and a number.';
          else if (pwd !== $('regConfirm').value) msg = 'Passwords do not match.';
          if (msg) {
            e.preventDefault();
            showToast(msg, 'error', 'Registration');
          }
        });
      })();
    </script>
<?php require __DIR__ . '/includes/auth_layout_end.php'; ?>
