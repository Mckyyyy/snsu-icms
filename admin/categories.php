<?php
// admin/categories.php - Manage the concern categories students choose from
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

$tab = ($_GET['tab'] ?? '') === 'archived' ? 'archived' : 'active';
$allCategories = $pdo->query("
    SELECT cat.*,
           COUNT(c.concern_id) AS total,
           COALESCE(SUM(c.status_id IN (1, 2)), 0) AS open_count
    FROM categories cat
    LEFT JOIN concerns c ON c.category_id = cat.category_id
    GROUP BY cat.category_id
    ORDER BY cat.category_name
")->fetchAll();
$activeCount   = count(array_filter($allCategories, fn($c) => (int)$c['is_active'] === 1));
$archivedCount = count($allCategories) - $activeCount;
$categories    = array_values(array_filter($allCategories, fn($c) => ((int)$c['is_active'] === 1) === ($tab === 'active')));

$pageTitle = "Categories | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Settings</div>
    <h1 class="page-title">Categories</h1>
    <p class="page-desc">The areas students choose when they submit a concern. Categories are archived, never deleted, so old concerns and reports keep them.</p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openCategoryModal()"><?= icon('plus', '', 16) ?> Add Category</button>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title"><?= $tab === 'archived' ? 'Archived Categories' : 'Active Categories' ?></h2>
    <div class="segmented" aria-label="Show">
      <a href="categories.php" class="<?= $tab === 'active' ? 'active' : '' ?>">Active (<?= $activeCount ?>)</a>
      <a href="?tab=archived" class="<?= $tab === 'archived' ? 'active' : '' ?>">Archived (<?= $archivedCount ?>)</a>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-stack">
      <thead>
        <tr><th>Category</th><th>Concerns</th><th>Open</th><th>Status</th><th class="cell-actions"><span class="sr-only">Actions</span></th></tr>
      </thead>
      <tbody>
        <?php if (!$categories): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-state-icon"><?= icon('archive', '', 22) ?></div>
              <div class="empty-state-title"><?= $tab === 'archived' ? 'No archived categories' : 'No active categories' ?></div>
            </div>
          </td></tr>
        <?php endif; ?>
        <?php foreach ($categories as $cat): $active = (int)$cat['is_active'] === 1; ?>
          <tr>
            <td class="cell-title">
              <?= e($cat['category_name']) ?>
              <span class="cell-sub"><?= e($cat['description'] ?? 'No description') ?></span>
            </td>
            <td class="text-sm"><a href="concerns.php?category=<?= (int)$cat['category_id'] ?>"><?= (int)$cat['total'] ?></a></td>
            <td class="text-sm"><?= (int)$cat['open_count'] ?></td>
            <td>
              <span class="badge-tag badge-dot <?= $active ? 'badge-green' : 'badge-gray' ?>"><?= $active ? 'Active' : 'Archived' ?></span>
              <?php if (!$active && !empty($cat['archived_at'])): ?><span class="cell-sub"><?= e(formatDate($cat['archived_at'])) ?></span><?php endif; ?>
            </td>
            <td class="cell-actions">
              <div class="row-actions">
                <button type="button" class="btn-icon" title="Edit category"
                  data-id="<?= (int)$cat['category_id'] ?>" data-name="<?= e($cat['category_name']) ?>" data-desc="<?= e($cat['description'] ?? '') ?>"
                  onclick="openCategoryModal(this)"><?= icon('edit', '', 16) ?></button>
                <form action="../actions/category_action.php" method="POST">
                  <?= csrfField() ?>
                  <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">
                  <?php if ($active): ?>
                    <input type="hidden" name="action" value="archive">
                    <button type="button" class="btn-icon" title="Archive (removed from the Submit form, kept for the record)"
                      data-confirm="Archive &lt;strong&gt;<?= e($cat['category_name']) ?>&lt;/strong&gt;? Students can no longer choose it. Existing concerns and reports keep it, and you can restore it anytime."
                      data-confirm-title="Archive Category" data-confirm-text="Archive" data-confirm-type="warning"><?= icon('archive', '', 16) ?></button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="restore">
                    <button type="button" class="btn btn-secondary btn-sm"
                      data-confirm="Restore &lt;strong&gt;<?= e($cat['category_name']) ?>&lt;/strong&gt;? Students can choose it again."
                      data-confirm-title="Restore Category" data-confirm-text="Restore" data-confirm-type="info"><?= icon('refresh', '', 14) ?> Restore</button>
                  <?php endif; ?>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    <span class="text-sm text-muted">Archived categories stay on existing concerns and in reports; students just can't pick them for new concerns. The AI engine's category weights and suggestions apply to the four original categories.</span>
  </div>
</div>

<!-- Add / edit category -->
<div class="modal-backdrop" id="categoryModal" onclick="if(event.target === this) closeModal('categoryModal')">
  <div class="modal-content" role="dialog" aria-labelledby="categoryModalTitle" style="max-width: 480px;">
    <div class="modal-header">
      <h3 class="card-title" id="categoryModalTitle">Add Category</h3>
      <button type="button" class="alert-close" onclick="closeModal('categoryModal')" aria-label="Close">&times;</button>
    </div>
    <form action="../actions/category_action.php" method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" id="catAction" value="add">
      <input type="hidden" name="category_id" id="catId" value="">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="catName">Name <span class="required">*</span></label>
          <input type="text" name="category_name" id="catName" class="form-control" required minlength="2" maxlength="100" placeholder="e.g. Transportation">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label" for="catDesc">Description <span class="optional">(shown to students)</span></label>
          <textarea name="description" id="catDesc" class="form-control" rows="3" maxlength="500" placeholder="What kinds of concerns belong here?"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('categoryModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="catSubmit"><?= icon('check', '', 16) ?> Save</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openCategoryModal(btn) {
    const editing = !!btn;
    document.getElementById('categoryModalTitle').textContent = editing ? 'Edit Category' : 'Add Category';
    document.getElementById('catAction').value = editing ? 'update' : 'add';
    document.getElementById('catId').value = editing ? btn.dataset.id : '';
    document.getElementById('catName').value = editing ? btn.dataset.name : '';
    document.getElementById('catDesc').value = editing ? btn.dataset.desc : '';
    openModal('categoryModal');
    setTimeout(() => document.getElementById('catName').focus(), 50);
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
