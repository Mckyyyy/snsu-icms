<?php
// student/submit_concern.php - Concern Submission Form (Figure 4 of Prototype)
// Compact two-column layout: the form on the left, the live AI preview on the right.
// With ?id=N it becomes the edit form for that concern (owner only, Pending, no admin response yet).
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();
$user = getCurrentUser();



// ---- Edit mode ----
$editing = null;
if (isset($_GET['id'])) {
    $st = $pdo->prepare("SELECT * FROM concerns WHERE concern_id = :id AND student_id = :sid");
    $st->execute(['id' => (int)$_GET['id'], 'sid' => $user['id']]);
    $editing = $st->fetch() ?: null;
    if (!$editing) {
        setFlash('error', 'Concern not found.');
        header('Location: my_concerns.php');
        exit;
    }
    if (!canEditConcern($editing)) {
        setFlash('warning', 'This concern can no longer be edited because an administrator has already started on it.');
        header('Location: concern_details.php?id=' . (int)$editing['concern_id']);
        exit;
    }
}
$selectedCategory = $editing ? (int)$editing['category_id'] : 0;

// Only active categories can be chosen; an edited concern keeps its current one even if it was deactivated
$catStmt = $pdo->prepare("SELECT * FROM categories WHERE is_active = 1 OR category_id = :cur ORDER BY category_id ASC");
$catStmt->execute(['cur' => $selectedCategory]);
$categories = $catStmt->fetchAll();
$selectedDesc = '';
foreach ($categories as $cat) {
    if ((int)$cat['category_id'] === $selectedCategory) {
        $selectedDesc = $cat['description'];
    }
}

$categoryIcons = ['Facilities' => 'wrench', 'Academic' => 'book', 'Services' => 'settings', 'Student Welfare' => 'users'];

$pageTitle = $editing ? "Edit {$editing['ticket_id']} | ICMS-DPT-RRT" : "Submit Concern | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<?php if ($editing): ?>
  <a href="concern_details.php?id=<?= (int)$editing['concern_id'] ?>" class="back-link"><?= icon('arrow-left', '', 16) ?> Back to <?= e($editing['ticket_id']) ?></a>
<?php endif; ?>
<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Concerns</div>
    <h1 class="page-title"><?= $editing ? 'Edit Concern' : 'Submit a Concern' ?></h1>
    <p class="page-desc">
      <?= $editing
          ? 'You can edit until an administrator responds. Priority is recalculated, but the deadline still counts from when you first submitted.'
          : 'Describe the issue. The AI engine sets its priority and resolution target while you type.' ?>
    </p>
  </div>
  <?php if ($editing): ?><span class="ticket-badge" style="font-size: 0.85rem;"><?= e($editing['ticket_id']) ?></span><?php endif; ?>
</div>

<div class="grid-main-side submit-layout">
  <!-- ===================== Form ===================== -->
  <form action="../actions/<?= $editing ? 'edit_concern_action.php' : 'concern_action.php' ?>" method="POST" enctype="multipart/form-data" id="submitConcernForm" class="card">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="concern_id" value="<?= (int)$editing['concern_id'] ?>"><?php endif; ?>

    <div class="card-body submit-body">
      <!-- Category -->
      <div class="form-group">
        <span class="form-label" id="categoryLabel">Category <span class="required">*</span></span>
        <div class="cat-pills" role="radiogroup" aria-labelledby="categoryLabel">
          <?php foreach ($categories as $cat): ?>
            <label class="cat-pill" title="<?= e($cat['description']) ?>">
              <input type="radio" name="category_id" value="<?= (int)$cat['category_id'] ?>"
                     data-name="<?= e($cat['category_name']) ?>" data-desc="<?= e($cat['description']) ?>" required
                     <?= (int)$cat['category_id'] === $selectedCategory ? 'checked' : '' ?>>
              <span class="cat-pill-body"><?= icon($categoryIcons[$cat['category_name']] ?? 'folder', '', 16) ?> <?= e($cat['category_name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <span class="form-help" id="categoryHelp"><?= $selectedDesc !== '' ? e($selectedDesc) : 'Pick the area the problem belongs to.' ?></span>
      </div>

      <!-- Title -->
      <div class="form-group">
        <div class="label-row">
          <label class="form-label" for="concernTitle">Title <span class="required">*</span></label>
          <span class="char-counter" id="titleCounter">0 / 255</span>
        </div>
        <input type="text" name="title" id="concernTitle" class="form-control" maxlength="255" required
               placeholder="e.g. Broken chairs in classroom Lab 2" data-counter="titleCounter"
               value="<?= e($editing['title'] ?? '') ?>">
      </div>

      <!-- Description -->
      <div class="form-group">
        <div class="label-row">
          <label class="form-label" for="concernDescription">Description <span class="required">*</span></label>
          <span class="char-counter" id="descCounter">0 / 5000</span>
        </div>
        <textarea name="description" id="concernDescription" class="form-control" rows="5" maxlength="5000" required
                  placeholder="Where is it, when did it start, and who is affected?" data-counter="descCounter"><?= e($editing['description'] ?? '') ?></textarea>
      </div>

      <!-- Duplicate detection: filled by ai_preview.js from api/ai_prioritize.php -->
      <div class="duplicate-box" id="duplicateBox" hidden aria-live="polite">
        <div class="duplicate-head">
          <span class="duplicate-icon"><?= icon('copy', '', 16) ?></span>
          <div>
            <strong id="duplicateTitle">This may already be reported</strong>
            <p class="text-xs" id="duplicateHint">Check the list below. If it is the same problem, you don't need to submit it again; you will see the fix announced or can follow your own ticket.</p>
          </div>
        </div>
        <ul class="duplicate-list" id="duplicateList"></ul>
        <p class="text-xs duplicate-foot">Different problem, or new details? You can still submit.</p>
      </div>

      <!-- Attachment (one line) -->
      <div class="form-group" style="margin-bottom: 0;">
        <span class="form-label">Evidence <span class="optional">(optional)</span></span>
        <?php $currentAttachment = $editing ? getConcernAttachmentMeta($pdo, (int)$editing['concern_id']) : null; ?>
        <?php if ($currentAttachment): ?>
          <div class="file-chosen" id="currentAttachment" style="margin-bottom: 8px;">
            <span class="flex items-center gap-2 text-sm" style="min-width: 0; flex: 1;">
              <?= icon('paperclip', '', 14) ?>
              <span class="truncate">Current file: <a href="<?= e(concernAttachmentUrl($currentAttachment)) ?>" target="_blank" rel="noopener" class="font-semibold"><?= e($currentAttachment['original_name']) ?></a></span>
            </span>
            <label class="flex items-center gap-2 text-sm" style="cursor: pointer; white-space: nowrap;">
              <input type="checkbox" name="remove_attachment" value="1" id="removeCurrent"> Remove
            </label>
          </div>
          <span class="form-help" style="margin: -2px 0 8px;">Choosing a new file below replaces the current one.</span>
        <?php endif; ?>
        <label class="file-inline" id="dropZone" for="attachment">
          <input type="file" name="attachment" id="attachment" accept=".jpg,.jpeg,.png,.pdf,.docx">
          <span class="file-inline-icon"><?= icon('paperclip', '', 16) ?></span>
          <span class="file-inline-text"><strong>Attach a photo or document</strong> <span>JPG, PNG, PDF or DOCX · up to <?= ATTACHMENT_MAX_BYTES / 1024 / 1024 ?> MB</span></span>
          <span class="btn btn-secondary btn-sm file-inline-btn">Browse</span>
        </label>
        <div id="fileActions" class="file-chosen" style="display: none;">
          <div id="imagePreviewWrap" style="display: none;">
            <img id="imagePreviewImg" src="" alt="Selected image preview">
          </div>
          <span class="flex items-center gap-2 text-sm" style="min-width: 0; flex: 1;"><?= icon('paperclip', '', 14) ?> <span id="fileName" class="truncate font-semibold"></span></span>
          <button type="button" id="removeImageBtn" class="btn btn-ghost btn-sm" aria-label="Remove file"><?= icon('x', '', 14) ?> Remove</button>
        </div>
      </div>

      <!-- Anonymous submission -->
      <label class="choice-card anon-choice" style="display: block; margin-top: 16px;">
        <input type="checkbox" name="is_anonymous" id="isAnonymous" value="1" <?= !empty($editing['is_anonymous']) ? 'checked' : '' ?>>
        <span class="choice-card-body">
          <span class="choice-card-title"><?= icon('eye-off', '', 16) ?> Submit anonymously</span>
          <span class="choice-card-desc">Administrators will not see your name, student number, email or college. You can still track this concern and receive replies here. Don't put your name in the description or photo if you want to stay anonymous.</span>
        </span>
      </label>
    </div>

    <div class="form-actions">
      <span class="form-actions-note" id="privacyNote"><?= icon('lock', '', 14) ?> <span id="privacyNoteText"><?= !empty($editing['is_anonymous']) ? 'Administrators will see this without your name.' : 'Only you and the administrators can see this.' ?></span></span>
      <a href="<?= $editing ? 'concern_details.php?id=' . (int)$editing['concern_id'] : 'dashboard.php' ?>" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary" id="submitConcernBtn"><?= icon('check', '', 16) ?> <?= $editing ? 'Save Changes' : 'Submit Concern' ?></button>
    </div>
  </form>

  <!-- ===================== Live AI preview ===================== -->
  <aside class="submit-aside">
    <div class="card" style="margin-bottom: 16px;">
      <div class="card-header">
        <h2 class="card-title"><?= icon('zap', '', 16) ?> AI Prioritization</h2>
        <span class="text-xs text-muted">Live preview</span>
      </div>
      <div class="card-body">
        <div class="ai-preview-box" id="aiPreviewBox" aria-live="polite" style="border: 0; padding: 0; background: none; box-shadow: none;">
          <div class="ai-preview-header">
            <span id="aiPriorityBadge" class="priority-pill priority-low">Low</span>
            <span id="aiScoreBadge" class="mono" style="font-size: 0.85rem;" title="How urgent the AI judges your concern, from 1 to 100 (not a count of reports)">Urgency 25/100</span>
          </div>
          <div class="ai-score-bar-track"><div class="ai-score-bar-fill" id="aiScoreFill" style="width: 25%;"></div></div>
          <div id="aiEngineLine" class="text-xs text-muted" style="margin-top: -2px;"><?= aiLlmAvailable() ? 'Analyzed by Google Gemini AI' : 'Rule-based estimate' ?></div>
          <!-- Filled by ai_preview.js once a category is selected; a plain hint until then -->
          <div id="aiSlaBadge" class="text-sm" data-state="empty" style="color: var(--text-muted); font-weight: 500;">Expected to be fixed: <span id="aiSlaValue">Select a category to see the target</span></div>
          <div id="aiSlaDeadline" class="text-xs text-muted" hidden></div>
          <ul id="aiReasonsList" class="text-xs text-secondary" style="margin-left: 16px; display: flex; flex-direction: column; gap: 2px;">
            <li>Start typing a title and description to see the urgency analysis.</li>
          </ul>
          <div id="aiCategoryHint" class="alert alert-info" style="display: none; margin: 0; padding: 8px 10px; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
            <span class="text-xs">Suggested category: <strong id="aiCategoryHintName"></strong></span>
            <button type="button" id="aiCategoryHintBtn" class="btn btn-secondary btn-sm">Use it</button>
          </div>
          <div id="aiRrtNotice" class="alert alert-error" style="display: none; margin: 0; padding: 8px 10px;">
            <span class="alert-icon"><?= icon('alert-triangle', '', 16) ?></span>
            <span class="alert-text text-xs"><strong>Rapid Response alert:</strong> this will be escalated immediately.</span>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h2 class="card-title"><?= icon('info', '', 16) ?> Tips for a faster fix</h2></div>
      <div class="card-body">
        <ul class="tips-list">
          <li>Name the exact place: building, floor and room.</li>
          <li>Say when it started and how many people are affected.</li>
          <li>Mention safety risks (sparks, leaks, injuries) so they are flagged as urgent.</li>
          <li>Add a photo if you can.</li>
        </ul>
      </div>
    </div>
  </aside>
</div>

<script src="../assets/js/ai_preview.js<?= assetVersion('js/ai_preview.js') ?>"></script>
<script>
  (function () {
    // Anonymous toggle updates the privacy note
    const anon = document.getElementById('isAnonymous');
    const note = document.getElementById('privacyNoteText');
    if (anon && note) {
      anon.addEventListener('change', () => {
        note.textContent = anon.checked ? 'Administrators will see this without your name.' : 'Only you and the administrators can see this.';
      });
    }

    // Character counters
    document.querySelectorAll('[data-counter]').forEach((el) => {
      const out = document.getElementById(el.dataset.counter);
      const max = el.getAttribute('maxlength');
      const update = () => { out.textContent = el.value.length + ' / ' + max; };
      el.addEventListener('input', update);
      update();
    });

    // Show the chosen category's description under the pills
    const help = document.getElementById('categoryHelp');
    document.querySelectorAll('input[name="category_id"]').forEach((r) => {
      r.addEventListener('change', () => { help.textContent = r.dataset.desc; });
    });

    // File picker: name, thumbnail, drag & drop styling, remove
    const fileInput = document.getElementById('attachment');
    const dropZone = document.getElementById('dropZone');
    const fileActions = document.getElementById('fileActions');
    const fileName = document.getElementById('fileName');
    const previewWrap = document.getElementById('imagePreviewWrap');
    const previewImg = document.getElementById('imagePreviewImg');

    function refresh() {
      const file = fileInput.files[0];
      fileActions.style.display = file ? 'flex' : 'none';
      dropZone.style.display = file ? 'none' : '';
      fileName.textContent = file ? file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)' : '';
      if (file && file.size > <?= ATTACHMENT_MAX_BYTES ?>) {
        showToast('That file is larger than <?= ATTACHMENT_MAX_BYTES / 1024 / 1024 ?> MB. Please choose a smaller file.', 'error', 'Attachment too large');
      }
      previewWrap.style.display = 'none';
      previewImg.src = '';
      if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => { previewImg.src = e.target.result; previewWrap.style.display = 'block'; };
        reader.readAsDataURL(file);
      }
    }
    fileInput.addEventListener('change', refresh);
    ['dragenter', 'dragover'].forEach((ev) => dropZone.addEventListener(ev, () => dropZone.classList.add('dragover')));
    ['dragleave', 'drop'].forEach((ev) => dropZone.addEventListener(ev, () => dropZone.classList.remove('dragover')));
    document.getElementById('removeImageBtn').addEventListener('click', () => { fileInput.value = ''; refresh(); });

    // Prevent double submission
    document.getElementById('submitConcernForm').addEventListener('submit', () => {
      const btn = document.getElementById('submitConcernBtn');
      btn.disabled = true;
      btn.textContent = <?= json_encode($editing ? 'Saving...' : 'Submitting...') ?>;
    });

    // Edit mode: show the AI analysis for the current text right away
    <?php if ($editing): ?>
    window.addEventListener('load', () => {
      document.getElementById('concernTitle').dispatchEvent(new Event('input'));
    });
    <?php endif; ?>
  })();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
