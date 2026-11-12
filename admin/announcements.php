<?php
// admin/announcements.php - Manage Announcements (Figure 12 of Prototype)
// Announcements are archived, never deleted: archived ones disappear for students and can be restored.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

$tab = ($_GET['tab'] ?? '') === 'archived' ? 'archived' : 'published';

$counts = $pdo->query("
    SELECT COALESCE(SUM(archived_at IS NULL), 0) AS published,
           COALESCE(SUM(archived_at IS NULL AND is_urgent = 1), 0) AS urgent,
           COALESCE(SUM(archived_at IS NOT NULL), 0) AS archived
    FROM announcements
")->fetch();

$announcements = $pdo->query("
    SELECT a.*, adm.name AS author_name, arc.name AS archived_by_name
    FROM announcements a
    LEFT JOIN administrators adm ON a.admin_id = adm.admin_id
    LEFT JOIN administrators arc ON a.archived_by = arc.admin_id
    WHERE a.archived_at IS " . ($tab === 'archived' ? 'NOT NULL' : 'NULL') . "
    ORDER BY " . ($tab === 'archived' ? 'a.archived_at DESC' : 'a.is_urgent DESC, a.published_at DESC') . "
")->fetchAll();

$categoryLabels = [
    'General'     => 'General notice',
    'Maintenance' => 'Maintenance & facilities',
    'Academic'    => 'Academic & schedules',
    'Services'    => 'Campus services',
];

$pageTitle = "Announcements | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
  .ann-list { display: flex; flex-direction: column; }
  .ann-item { display: grid; grid-template-columns: 40px 1fr auto; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--border-subtle); }
  .ann-item:last-child { border-bottom: 0; }
  .ann-item.is-urgent { background: #fffafa; box-shadow: inset 3px 0 0 var(--danger); }
  .ann-title { font-weight: 700; color: var(--text-primary); font-size: 0.98rem; line-height: 1.35; }
  .ann-badges { display: flex; flex-wrap: wrap; gap: 6px; margin: 6px 0 8px; }
  .ann-text { font-size: 0.88rem; color: var(--text-secondary); line-height: 1.6; white-space: pre-line; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
  .ann-text.expanded { display: block; }
  .ann-more { background: none; border: 0; padding: 0; margin-top: 4px; font-size: 0.8rem; font-weight: 600; color: var(--brand-blue); cursor: pointer; }
  .ann-meta { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 10px; font-size: 0.78rem; color: var(--text-muted); }
  .ann-meta span { display: inline-flex; align-items: center; gap: 4px; }
  .ann-actions { display: flex; gap: 4px; align-items: flex-start; }
  @media (max-width: 640px) {
    .ann-item { grid-template-columns: 1fr; padding: 16px; }
    .ann-item > .list-item-icon { display: none; }
  }
</style>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Communication</div>
    <h1 class="page-title">Announcements</h1>
    <p class="page-desc">Publish bulletins to every student. Urgent notices are pinned on top and also go to their notification bell.</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openAnnouncementModal()"><?= icon('plus', '', 16) ?> New Announcement</button>
  </div>
</div>

<div class="metrics-grid">
  <a href="announcements.php" class="metric-card">
    <div class="metric-icon-wrap metric-icon-total"><?= icon('megaphone', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Published</span><span class="metric-number"><?= (int)$counts['published'] ?></span><span class="metric-sub">Visible to students</span></div>
  </a>
  <a href="announcements.php" class="metric-card">
    <div class="metric-icon-wrap metric-icon-danger"><?= icon('alert-triangle', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Urgent</span><span class="metric-number"><?= (int)$counts['urgent'] ?></span><span class="metric-sub">Pinned on top</span></div>
  </a>
  <a href="?tab=archived" class="metric-card">
    <div class="metric-icon-wrap metric-icon-warning"><?= icon('archive', '', 22) ?></div>
    <div class="metric-content"><span class="metric-label">Archived</span><span class="metric-number"><?= (int)$counts['archived'] ?></span><span class="metric-sub">Kept for the record</span></div>
  </a>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title"><?= $tab === 'archived' ? 'Archived Announcements' : 'Published Bulletins' ?></h2>
    <div class="segmented" aria-label="Show">
      <a href="announcements.php" class="<?= $tab === 'published' ? 'active' : '' ?>">Published (<?= (int)$counts['published'] ?>)</a>
      <a href="?tab=archived" class="<?= $tab === 'archived' ? 'active' : '' ?>">Archived (<?= (int)$counts['archived'] ?>)</a>
    </div>
  </div>

  <?php if (!$announcements): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><?= icon($tab === 'archived' ? 'archive' : 'megaphone', '', 22) ?></div>
      <div class="empty-state-title"><?= $tab === 'archived' ? 'Nothing archived' : 'Nothing published yet' ?></div>
      <p><?= $tab === 'archived' ? 'Archived announcements are kept here and can be restored.' : 'Click "New Announcement" to publish your first bulletin.' ?></p>
    </div>
  <?php endif; ?>

  <div class="ann-list">
    <?php foreach ($announcements as $anc): $urgent = (int)$anc['is_urgent'] === 1; ?>
      <div class="ann-item <?= $urgent && $tab === 'published' ? 'is-urgent' : '' ?>">
        <div class="list-item-icon <?= $urgent ? 'urgent' : '' ?>"><?= icon($urgent ? 'alert-triangle' : 'megaphone', '', 18) ?></div>
        <div style="min-width: 0;">
          <div class="ann-title"><?= e($anc['title']) ?></div>
          <div class="ann-badges">
            <?php if ($urgent): ?><span class="badge-tag badge-red badge-dot">Urgent</span><?php endif; ?>
            <span class="badge-tag"><?= e($categoryLabels[$anc['category']] ?? $anc['category']) ?></span>
            <?php if ($anc['archived_at']): ?><span class="badge-tag badge-gray"><?= icon('archive', '', 12) ?> Archived</span><?php endif; ?>
          </div>
          <div class="ann-text"><?= e($anc['content']) ?></div>
          <?php if (mb_strlen($anc['content']) > 320 || substr_count($anc['content'], "\n") > 3): ?>
            <button type="button" class="ann-more" onclick="const t = this.previousElementSibling; t.classList.toggle('expanded'); this.textContent = t.classList.contains('expanded') ? 'Show less' : 'Read more';">Read more</button>
          <?php endif; ?>
          <div class="ann-meta">
            <span><?= icon('calendar', '', 13) ?> <?= formatDateTime($anc['published_at']) ?></span>
            <?php if (!empty($anc['author_name'])): ?><span><?= icon('user', '', 13) ?> <?= e($anc['author_name']) ?></span><?php endif; ?>
            <?php if ($anc['archived_at']): ?><span><?= icon('archive', '', 13) ?> Archived <?= formatDateTime($anc['archived_at']) ?><?= $anc['archived_by_name'] ? ' by ' . e($anc['archived_by_name']) : '' ?></span><?php endif; ?>
          </div>
        </div>
        <div class="ann-actions">
          <?php if ($tab === 'published'): ?>
            <button type="button" class="btn-icon" title="Edit announcement"
              data-announcement="<?= e(json_encode(['id' => (int)$anc['announcement_id'], 'title' => $anc['title'], 'content' => $anc['content'], 'category' => $anc['category'], 'urgent' => $urgent])) ?>"
              onclick="openAnnouncementModal(this)"><?= icon('edit', '', 16) ?></button>
          <?php endif; ?>
          <form action="../actions/announcement_action.php" method="POST" style="margin: 0;">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int)$anc['announcement_id'] ?>">
            <?php if ($tab === 'archived'): ?>
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="return_tab" value="archived">
              <button type="button" class="btn btn-secondary btn-sm"
                data-confirm="Restore &lt;strong&gt;<?= e($anc['title']) ?>&lt;/strong&gt;? Students will see it again."
                data-confirm-title="Restore Announcement" data-confirm-text="Restore" data-confirm-type="info"><?= icon('refresh', '', 14) ?> Restore</button>
            <?php else: ?>
              <input type="hidden" name="action" value="archive">
              <button type="button" class="btn-icon" title="Archive (kept for the record)"
                data-confirm="Archive &lt;strong&gt;<?= e($anc['title']) ?>&lt;/strong&gt;? Students will no longer see it. It is kept for the record and can be restored."
                data-confirm-title="Archive Announcement" data-confirm-text="Archive" data-confirm-type="warning"><?= icon('archive', '', 16) ?></button>
            <?php endif; ?>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- New / edit announcement -->
<div class="modal-backdrop" id="announcementModal" onclick="if(event.target === this) closeModal('announcementModal')">
  <div class="modal-content" role="dialog" aria-labelledby="ancModalTitle" style="max-width: 620px;">
    <div class="modal-header">
      <div>
        <h3 class="card-title" id="ancModalTitle">New Announcement</h3>
        <div class="card-subtitle" id="ancModalSub">Visible to all students as soon as it is published.</div>
      </div>
      <button type="button" class="alert-close" onclick="closeModal('announcementModal')" aria-label="Close">&times;</button>
    </div>
    <form action="../actions/announcement_action.php" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" id="ancAction" value="create">
      <input type="hidden" name="id" id="ancId" value="">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="ancTitle">Title <span class="required">*</span></label>
          <input type="text" name="title" id="ancTitle" class="form-control" placeholder="e.g. Scheduled water interruption on Friday" required maxlength="255">
        </div>
        <div class="form-group">
          <label class="form-label" for="ancCategory">Category</label>
          <select name="category" id="ancCategory" class="form-control">
            <?php foreach ($categoryLabels as $val => $label): ?>
              <option value="<?= e($val) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="ancContent">Message <span class="required">*</span></label>
          <textarea name="content" id="ancContent" class="form-control" rows="6" placeholder="What, when, where and what students should do..." required></textarea>
        </div>
        <label class="choice-card" style="display: block; margin: 0;">
          <input type="checkbox" name="is_urgent" id="ancUrgent" value="1">
          <span class="choice-card-body">
            <span class="choice-card-title" style="color: var(--danger);"><?= icon('alert-triangle', '', 16) ?> Mark as urgent</span>
            <span class="choice-card-desc" id="ancUrgentDesc">Pins it to the top and notifies every active student.</span>
          </span>
        </label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('announcementModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="ancSubmit"><?= icon('megaphone', '', 16) ?> Publish</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openAnnouncementModal(btn) {
    const a = btn ? JSON.parse(btn.dataset.announcement) : null;
    const $ = (id) => document.getElementById(id);
    $('ancModalTitle').textContent = a ? 'Edit Announcement' : 'New Announcement';
    $('ancModalSub').textContent = a ? 'Changes show to students right away.' : 'Visible to all students as soon as it is published.';
    $('ancAction').value = a ? 'update' : 'create';
    $('ancId').value = a ? a.id : '';
    $('ancTitle').value = a ? a.title : '';
    $('ancContent').value = a ? a.content : '';
    $('ancCategory').value = a ? a.category : 'General';
    $('ancUrgent').checked = a ? a.urgent : false;
    $('ancUrgentDesc').textContent = a ? 'Pins it to the top. Editing does not send the notification again.' : 'Pins it to the top and notifies every active student.';
    $('ancSubmit').lastChild.textContent = a ? ' Save Changes' : ' Publish';
    openModal('announcementModal');
    setTimeout(() => $('ancTitle').focus(), 50);
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
