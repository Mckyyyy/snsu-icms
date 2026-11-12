<?php
// includes/concern_filter_bar.php - Server-side filter form for concern lists
// Expects: $categories, $statuses, $searchPlaceholder
$f = [
    'q'        => (string)($_GET['q'] ?? ''),
    'category' => (int)($_GET['category'] ?? 0),
    'status'   => (int)($_GET['status'] ?? 0),
    'priority' => (string)($_GET['priority'] ?? ''),
    'sla'      => (string)($_GET['sla'] ?? ''),
    'assigned' => (string)($_GET['assigned'] ?? ''),
];
$hasFilter = $f['q'] !== '' || $f['category'] || $f['status'] || $f['priority'] !== '' || $f['sla'] !== '' || $f['assigned'] !== '';
?>
<form method="GET" class="filter-bar" id="concernFilterForm">
  <div class="search-input-wrap">
    <span class="search-icon"><?= icon('search', '', 16) ?></span>
    <input type="search" name="q" class="form-control" value="<?= e($f['q']) ?>" placeholder="<?= e($searchPlaceholder ?? 'Search...') ?>">
  </div>

  <select name="category" class="form-control" style="width: auto; min-width: 150px;" onchange="this.form.submit()" aria-label="Category">
    <option value="0">All Categories</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= (int)$cat['category_id'] ?>" <?= $f['category'] === (int)$cat['category_id'] ? 'selected' : '' ?>><?= e($cat['category_name']) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="status" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit()" aria-label="Status">
    <option value="0">All Statuses</option>
    <?php foreach ($statuses as $st): ?>
      <option value="<?= (int)$st['status_id'] ?>" <?= $f['status'] === (int)$st['status_id'] ? 'selected' : '' ?>><?= e($st['status_name']) ?></option>
    <?php endforeach; ?>
  </select>

  <select name="priority" class="form-control" style="width: auto; min-width: 130px;" onchange="this.form.submit()" aria-label="Priority">
    <option value="">All Priorities</option>
    <?php if (!empty($assignOptions)): // administrator lists only ?>
      <option value="new" <?= $f['priority'] === 'new' ? 'selected' : '' ?> title="Pending concerns no administrator has responded to yet">New</option>
    <?php endif; ?>
    <?php foreach (array_keys(SLA_HOURS_BY_PRIORITY) as $p): ?>
      <option value="<?= $p ?>" <?= $f['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
    <?php endforeach; ?>
  </select>

  <select name="sla" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit()" aria-label="SLA state">
    <option value="">All SLA States</option>
    <?php foreach (['breached' => 'Breached', 'at_risk' => 'At Risk', 'on_track' => 'On Track', 'met' => 'Met', 'missed' => 'Missed'] as $k => $label): ?>
      <option value="<?= $k ?>" <?= $f['sla'] === $k ? 'selected' : '' ?>><?= $label ?></option>
    <?php endforeach; ?>
  </select>

  <?php if (!empty($assignOptions)): ?>
    <select name="assigned" class="form-control" style="width: auto; min-width: 150px;" onchange="this.form.submit()" aria-label="Assignment">
      <option value="">Anyone</option>
      <option value="me" <?= $f['assigned'] === 'me' ? 'selected' : '' ?>>Assigned to me</option>
      <option value="none" <?= $f['assigned'] === 'none' ? 'selected' : '' ?>>Unassigned</option>
      <?php foreach ($assignOptions as $opt): ?>
        <option value="<?= (int)$opt['admin_id'] ?>" <?= $f['assigned'] === (string)$opt['admin_id'] ? 'selected' : '' ?>><?= e($opt['name']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>

  <?php if (!empty($_GET['per_page'])): ?><input type="hidden" name="per_page" value="<?= (int)$_GET['per_page'] ?>"><?php endif; ?>
  <button type="submit" class="btn btn-primary btn-sm"><?= icon('search', '', 14) ?> Search</button>
</form>
<?php
// Applied filters as removable chips, with the number of matching records
$catNames = array_column($categories, 'category_name', 'category_id');
$stNames  = array_column($statuses, 'status_name', 'status_id');
$asgNames = ['me' => 'Me', 'none' => 'Unassigned'] + (!empty($assignOptions) ? array_column($assignOptions, 'name', 'admin_id') : []);
echo renderActiveFilters([
    'q'        => ['Search', fn($v) => '"' . $v . '"'],
    'category' => ['Category', $catNames],
    'status'   => ['Status', $stNames],
    'priority' => ['Priority', !empty($assignOptions) ? ['new' => 'New (not yet responded)'] : []],
    'sla'      => ['SLA', ['breached' => 'Breached', 'at_risk' => 'At Risk', 'on_track' => 'On Track', 'met' => 'Met', 'missed' => 'Missed']],
    'assigned' => ['Assigned', $asgNames],
], (int)($pager['total_rows'] ?? 0));
?>
