// assets/js/ai_preview.js - Live AI Dynamic Prioritization Preview (DPT-RRT)
// Calls api/ai_prioritize.php, which runs the same server-side engine used on
// submission, so the preview can never drift from the stored result.
//
// Two stages so it feels real-time:
//   1. quick  (~0.3 s after each keystroke): instant estimate from the rule-based engine
//              (or Gemini's answer if this exact text was already analyzed)
//   2. ai     (~1 s after the student pauses): Google Gemini's analysis replaces the estimate

document.addEventListener('DOMContentLoaded', () => {
  const titleInput     = document.getElementById('concernTitle');
  const descInput      = document.getElementById('concernDescription');
  // Category is chosen with radio cards (name="category_id")
  const categoryRadios = Array.from(document.querySelectorAll('input[name="category_id"]'));
  const categoryValue = () => (categoryRadios.find((r) => r.checked) || {}).value || '';

  const previewBox    = document.getElementById('aiPreviewBox');
  const scoreBadge    = document.getElementById('aiScoreBadge');
  const priorityBadge = document.getElementById('aiPriorityBadge');
  const slaBadge      = document.getElementById('aiSlaBadge');
  const slaValue      = document.getElementById('aiSlaValue');
  const scoreFill     = document.getElementById('aiScoreFill');
  const reasonsList   = document.getElementById('aiReasonsList');
  const rrtNotice     = document.getElementById('aiRrtNotice');
  const catHint       = document.getElementById('aiCategoryHint');
  const catHintName   = document.getElementById('aiCategoryHintName');
  const catHintBtn    = document.getElementById('aiCategoryHintBtn');
  const dupBox        = document.getElementById('duplicateBox');
  const dupList       = document.getElementById('duplicateList');
  const dupTitle      = document.getElementById('duplicateTitle');
  const editingId     = document.querySelector('input[name="concern_id"]')?.value || '';

  if (!titleInput || !descInput || !previewBox) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const endpoint = '../api/ai_prioritize.php';

  const PRIORITY_STYLE = {
    Critical: { color: '#dc2626', sla: 'Within 1 day (24 hours) · Immediate' },
    High:     { color: '#ea580c', sla: 'Within 2 days (48 hours)' },
    Medium:   { color: '#d97706', sla: 'Within 3 days (72 hours)' },
    Low:      { color: '#10b981', sla: 'Within 5 days (120 hours)' },
  };

  let quickTimer = null;
  let aiTimer = null;
  let quickCtl = null;
  let aiCtl = null;
  let aiShownFor = '';   // the text Gemini's answer on screen belongs to
  const snapshot = () => categoryValue() + '\u0001' + titleInput.value.trim() + '\u0001' + descInput.value.trim();
  let suggestedCategoryId = null;
  const SLA_PLACEHOLDER = 'Select a category to see the target';
  const MIN_TEXT = 5;   // characters of title + description before the text analysis is shown

  const textLength = () => (titleInput.value.trim() + ' ' + descInput.value.trim()).trim().length;

  /** "~4 hours", "~1 day", "~2.5 days" */
  function aboutHours(h) {
    if (h < 24) return '~' + h + ' hour' + (h === 1 ? '' : 's');
    const d = Math.round((h / 24) * 2) / 2;
    return '~' + d + ' day' + (d === 1 ? '' : 's');
  }
  /** "Wed, Nov 11 · 1:15 AM", hours from now */
  function whenFromNow(h) {
    const d = new Date(Date.now() + h * 3600 * 1000);
    return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) + ' · '
      + d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
  }

  /**
   * "Expected to be fixed" line. With Gemini: the AI's estimate of the real fix time (never past the
   * deadline), the date, and what the fix involves; the SLA deadline goes underneath. With the rules:
   * the SLA target, as before. A plain hint until a category is chosen.
   */
  function renderSla(hours, priority, data) {
    if (!slaBadge || !slaValue) return;
    const known = categoryValue() !== '' && Number(hours) > 0;
    const deadline = document.getElementById('aiSlaDeadline');
    let label = SLA_PLACEHOLDER;
    const slaLabel = () => {
      const byHours = Object.values(PRIORITY_STYLE).find((s) => s.sla.includes('(' + hours + ' hours)'));
      return byHours ? byHours.sla : (PRIORITY_STYLE[priority] || {}).sla || 'Within ' + hours + ' hours';
    };
    const fix = data && Number(data.fix_hours) > 0 ? Number(data.fix_hours) : 0;
    if (known && fix) {
      label = aboutHours(fix) + ' (by ' + whenFromNow(fix) + ')' + (data.fix_note ? ' · ' + data.fix_note : '');
      if (deadline) {
        deadline.textContent = 'Official deadline (' + priority + ' SLA): ' + slaLabel().replace(' · Immediate', '') + ', by ' + whenFromNow(Number(hours));
        deadline.hidden = false;
      }
    } else {
      if (known) label = slaLabel();
      if (deadline) deadline.hidden = true;
    }
    slaValue.textContent = label;
    slaBadge.dataset.state = known ? 'set' : 'empty';
    slaBadge.style.color = known ? 'var(--brand-blue-hover)' : 'var(--text-muted)';
    slaBadge.style.fontWeight = known ? '600' : '500';
  }

  function render(data, withText) {
    const style = PRIORITY_STYLE[data.priority] || PRIORITY_STYLE.Low;

    if (scoreBadge) scoreBadge.textContent = 'Urgency ' + data.score + '/100';
    setEngineLine(data);
    if (priorityBadge) {
      priorityBadge.textContent = data.priority;
      priorityBadge.className = 'priority-pill priority-' + data.priority.toLowerCase();
    }
    renderSla(data.sla_hours, data.priority, data);
    if (scoreFill) {
      scoreFill.style.width = data.score + '%';
      scoreFill.style.background = style.color;
    }
    if (!withText) return;   // category chosen but nothing typed yet: keep the "start typing" hint
    if (reasonsList) {
      reasonsList.replaceChildren(...(data.reasons || []).map((r) => {
        const li = document.createElement('li');
        li.textContent = r;
        return li;
      }));
    }
    if (rrtNotice) rrtNotice.style.display = data.is_rrt_alert ? 'flex' : 'none';

    // Auto-categorization suggestion
    suggestedCategoryId = data.suggested_category_id || null;
    if (catHint) {
      const differs = suggestedCategoryId && categoryRadios.length && String(categoryValue()) !== String(suggestedCategoryId);
      catHint.style.display = differs ? 'flex' : 'none';
      if (differs && catHintName) catHintName.textContent = data.suggested_category;
    }
  }

  /** "Analyzed by Google Gemini AI" / "Quick estimate, Gemini AI is analyzing..." / "Rule-based estimate". */
  function setEngineLine(data, thinking) {
    const line = document.getElementById('aiEngineLine');
    if (!line) return;
    line.classList.toggle('ai-thinking', !!thinking);
    if (thinking) {
      line.textContent = 'Quick estimate · Gemini AI is analyzing';
    } else if (data.engine === 'gemini') {
      line.textContent = 'Analyzed by Google Gemini AI';
    } else if (data.engine_note === 'quick') {
      line.textContent = 'Rule-based estimate';
    } else if (data.engine_note && /Gemini AI is (slow|busy)/.test(data.engine_note)) {
      line.textContent = 'Rule-based estimate · ' + data.engine_note + '; it will try again when you submit';
    } else {
      line.textContent = 'Rule-based estimate' + (data.engine_note && data.engine_note !== 'Not enough text yet' ? ' (' + data.engine_note + ')' : '');
    }
  }

  // Duplicate detection: similar open concerns (other students' names are never sent)
  function renderSimilar(items) {
    if (!dupBox || !dupList) return;
    if (!items || !items.length) {
      dupBox.hidden = true;
      dupList.replaceChildren();
      return;
    }
    const ownCount = items.filter((i) => i.own).length;
    dupTitle.textContent = ownCount === items.length
      ? 'You already reported something similar'
      : 'This may already be reported';
    dupList.replaceChildren(...items.map((i) => {
      const li = document.createElement('li');
      li.className = 'duplicate-item';

      const main = document.createElement('div');
      main.className = 'duplicate-main';
      const title = document.createElement(i.own ? 'a' : 'span');
      title.className = 'duplicate-item-title';
      if (i.own) {
        title.href = 'concern_details.php?id=' + encodeURIComponent(i.concern_id);
        title.textContent = i.ticket_id + ' · ' + i.title;
      } else {
        title.textContent = i.title || ('A similar ' + i.category + ' concern (details are private)');
      }
      const meta = document.createElement('span');
      meta.className = 'duplicate-meta';
      meta.textContent = (i.own ? 'Your concern' : 'Reported by another student') + ' · ' + i.category
        + ' · ' + i.status + ' · ' + i.submitted
        + (i.keywords && i.keywords.length ? ' · matches: ' + i.keywords.join(', ') : '');
      main.append(title, meta);

      const pct = document.createElement('span');
      pct.className = 'duplicate-pct';
      pct.textContent = i.similarity + '% match';
      li.append(main, pct);
      return li;
    }));
    dupBox.hidden = false;
  }

  /**
   * Ask the server. mode 'quick' = instant estimate, 'ai' = Gemini (falls back to the rules by itself).
   * Answers for text that has changed in the meantime are ignored.
   */
  async function evaluate(mode) {
    const title = titleInput.value.trim();
    const description = descInput.value.trim();
    const withText = textLength() >= MIN_TEXT;
    const hasCategory = categoryValue() !== '';
    const snap = snapshot();
    if (!hasCategory) renderSla(null);           // never show a target without a category
    if (!withText) {
      previewBox.classList.remove('active');
      renderSimilar([]);
      if (!hasCategory) return;
      // A category alone already sets a target: ask the engine (it weighs the category)
    } else {
      previewBox.classList.add('active');
    }
    if (mode === 'quick' && aiShownFor === snap) return;   // Gemini already answered for this text

    const isAi = mode === 'ai';
    if (isAi) { if (aiCtl) aiCtl.abort(); aiCtl = new AbortController(); }
    else { if (quickCtl) quickCtl.abort(); quickCtl = new AbortController(); }
    const signal = (isAi ? aiCtl : quickCtl).signal;

    const body = new FormData();
    body.append('title', title);
    body.append('description', description);
    body.append('category_id', categoryValue());
    if (!isAi) body.append('mode', 'quick');
    if (editingId) body.append('concern_id', editingId);

    try {
      const res = await fetch(endpoint, { method: 'POST', body, headers: { 'X-CSRF-Token': csrf }, credentials: 'same-origin', signal });
      const json = await res.json();
      if (json.status !== 'success' || snapshot() !== snap) return;   // text changed while it was on its way
      if (!isAi && aiShownFor === snap) return;                       // never replace Gemini with the estimate
      const data = json.data;
      render(data, withText);
      if (withText) renderSimilar(data.similar);
      if (data.engine === 'gemini') {
        aiShownFor = snap;
        setEngineLine(data, false);
      } else if (!isAi && withText && data.ai_available) {
        setEngineLine(data, true);    // estimate on screen; Gemini's answer follows
      }
    } catch (err) {
      if (err.name !== 'AbortError' && reasonsList && isAi) {
        setEngineLine({ engine: 'rules', engine_note: 'AI preview unavailable' }, false);
      }
    }
  }

  /** On every change: instant estimate now, Gemini once the student pauses. */
  function schedule() {
    clearTimeout(quickTimer);
    clearTimeout(aiTimer);
    if (aiCtl) aiCtl.abort();           // a Gemini answer for the old text is no longer wanted
    quickTimer = setTimeout(() => evaluate('quick'), 250);
    aiTimer = setTimeout(() => evaluate('ai'), 1000);
  }

  titleInput.addEventListener('input', schedule);
  descInput.addEventListener('input', schedule);
  categoryRadios.forEach((r) => r.addEventListener('change', schedule));

  if (catHintBtn && categoryRadios.length) {
    catHintBtn.addEventListener('click', () => {
      if (!suggestedCategoryId) return;
      const target = categoryRadios.find((r) => String(r.value) === String(suggestedCategoryId));
      if (target) target.checked = true;
      schedule();
    });
  }

  // Edit form (or a browser-restored form) may already have a category / text: show the real target now
  if (categoryValue() !== '' || textLength() >= MIN_TEXT) schedule();
  else renderSla(null);
  // Back/forward cache can restore the radio state without firing "change"
  window.addEventListener('pageshow', (e) => { if (e.persisted) schedule(); });
});
