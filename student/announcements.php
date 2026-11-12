<?php
// student/announcements.php - Student Announcements (campus bulletin board)
// Featured notice (latest urgent, else the latest), category filters + search, month groups.
// Page-only styles: assets/css/announcements.css
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireStudent();

// Fetch announcements (urgent first, then newest)
$announcements = $pdo->query("
    SELECT a.*, adm.name AS author_name
    FROM announcements a
    LEFT JOIN administrators adm ON a.admin_id = adm.admin_id
    WHERE a.archived_at IS NULL
    ORDER BY a.is_urgent DESC, a.published_at DESC
")->fetchAll();

/** Visual identity per category (colour class, icon, friendly label); unknown categories get a neutral style. */
function announcementStyle(?string $category): array {
    $styles = [
        'academic'    => ['ann-cat-academic', 'book', 'Academic & schedules'],
        'maintenance' => ['ann-cat-maintenance', 'wrench', 'Maintenance & facilities'],
        'services'    => ['ann-cat-services', 'settings', 'Campus services'],
        'general'     => ['ann-cat-general', 'megaphone', 'General notice'],
    ];
    $key = strtolower(trim((string)$category)) ?: 'general';
    return $styles[$key] ?? ['ann-cat-other', 'megaphone', (string)$category];
}
/** Lower-case key used by the category filter. */
function announcementKey(?string $category): string {
    return strtolower(trim((string)$category)) ?: 'general';
}

// Featured = the most recent urgent notice; if none is urgent, the most recent one
$featured = $announcements[0] ?? null;
$others = array_slice($announcements, 1);

// The rest, grouped by month (urgent first within a month, then newest)
usort($others, fn($a, $b) => [substr($b['published_at'], 0, 7), $b['is_urgent'], $b['published_at']] <=> [substr($a['published_at'], 0, 7), $a['is_urgent'], $a['published_at']]);
$byMonth = [];
foreach ($others as $a) {
    $byMonth[date('F Y', strtotime($a['published_at']))][] = $a;
}

// Category chips with counts (in order of first appearance)
$catCounts = [];
foreach ($announcements as $a) {
    $k = announcementKey($a['category']);
    $catCounts[$k] ??= ['label' => $a['category'] ?: 'General', 'n' => 0, 'class' => announcementStyle($a['category'])[0]];
    $catCounts[$k]['n']++;
}
$urgentCount = count(array_filter($announcements, fn($a) => (int)$a['is_urgent'] === 1));
$isNew = fn(array $a): bool => ($t = strtotime($a['published_at'])) <= time() && time() - $t < 3 * 86400;
$searchText = fn(array $a): string => mb_strtolower($a['title'] . ' ' . $a['content'] . ' ' . $a['category'] . ' ' . ($a['author_name'] ?? ''));

$pageTitle = "Announcements | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/announcements.css<?= assetVersion('css/announcements.css') ?>">

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Campus bulletin</div>
    <h1 class="page-title">Announcements</h1>
    <p class="page-desc">Official notices from the university administration.</p>
  </div>
  <?php if ($announcements): ?>
    <div class="ann-stats" aria-label="Summary">
      <span class="ann-stat"><?= icon('megaphone', '', 14) ?> <strong><?= count($announcements) ?></strong> <?= count($announcements) === 1 ? 'notice' : 'notices' ?></span>
      <?php if ($urgentCount): ?>
        <span class="ann-stat is-urgent"><?= icon('alert-triangle', '', 14) ?> <strong><?= $urgentCount ?></strong> urgent</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!$announcements): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-state-icon"><?= icon('megaphone', '', 22) ?></div>
      <div class="empty-state-title">No announcements</div>
      <p>New bulletins from the administration will appear here.</p>
    </div>
  </div>
<?php else: ?>

  <?php
    // ---------------- Featured ----------------
    [$fClass, $fIcon, $fLabel] = announcementStyle($featured['category']);
    $fUrgent = (int)$featured['is_urgent'] === 1;
    $fTime = strtotime($featured['published_at']);
  ?>
  <article class="ann-featured <?= $fClass ?> <?= $fUrgent ? 'is-urgent' : '' ?>" id="announcement-<?= (int)$featured['announcement_id'] ?>"
           data-category="<?= e(announcementKey($featured['category'])) ?>" data-search="<?= e($searchText($featured)) ?>"
           aria-labelledby="ann-title-<?= (int)$featured['announcement_id'] ?>">
    <div class="ann-featured-media" aria-hidden="true">
      <span class="ann-ribbon <?= $fUrgent ? 'is-urgent' : '' ?>">
        <?php if ($fUrgent): ?><span class="pulse"></span> Urgent notice<?php else: ?><?= icon('star', '', 12) ?> Latest notice<?php endif; ?>
      </span>
      <div class="ann-featured-icon"><?= icon($fIcon, '', 30) ?></div>
      <div class="ann-featured-cat">Category<strong><?= e($featured['category'] ?: 'General') ?></strong></div>
    </div>
    <div class="ann-featured-body">
      <div class="ann-featured-head">
        <time class="ann-date" datetime="<?= date('c', $fTime) ?>" aria-hidden="true">
          <span class="ann-date-m"><?= date('M', $fTime) ?></span><span class="ann-date-d"><?= date('j', $fTime) ?></span>
        </time>
        <div style="min-width: 0;">
          <div class="ann-card-tags" style="padding-right: 0; margin-bottom: 6px;">
            <?php if ($fUrgent): ?><span class="ann-tag is-urgent"><?= icon('alert-triangle', '', 11) ?> Urgent</span><?php endif; ?>
            <span class="ann-tag"><?= e($fLabel ?: 'General') ?></span>
            <?php if ($isNew($featured)): ?><span class="ann-tag is-new">New</span><?php endif; ?>
          </div>
          <h2 class="ann-featured-title" id="ann-title-<?= (int)$featured['announcement_id'] ?>"><?= e($featured['title']) ?></h2>
        </div>
      </div>
      <div class="ann-featured-text ann-text" data-clamp><?= nl2br(e($featured['content'])) ?></div>
      <button type="button" class="ann-more" hidden>Read more</button>
      <div class="ann-meta">
        <span class="ann-author">
          <span class="avatar avatar-admin"><?= e(userInitials($featured['author_name'] ?: 'Administration')) ?></span>
          <span class="ann-author-name"><?= e($featured['author_name'] ?: 'University Administration') ?></span>
        </span>
        <span class="ann-time" title="<?= e(formatDateTime($featured['published_at'])) ?>">
          <?= icon('calendar', '', 13) ?> <?= date('l, F j, Y · g:i A', $fTime) ?> · <?= e(timeAgo($featured['published_at'])) ?>
        </span>
      </div>
    </div>
  </article>

  <?php if (count($catCounts) > 1 || count($announcements) > 3): ?>
  <!-- ---------------- Filters ---------------- -->
  <div class="ann-toolbar" role="search">
    <div class="ann-chips" role="group" aria-label="Filter by category">
      <button type="button" class="ann-chip" data-filter="" aria-pressed="true">All <span class="count"><?= count($announcements) ?></span></button>
      <?php foreach ($catCounts as $key => $c): ?>
        <button type="button" class="ann-chip <?= $c['class'] ?>" data-filter="<?= e($key) ?>" aria-pressed="false">
          <span class="dot" aria-hidden="true"></span><?= e($c['label']) ?> <span class="count"><?= $c['n'] ?></span>
        </button>
      <?php endforeach; ?>
    </div>
    <div class="search-input-wrap ann-search">
      <span class="search-icon"><?= icon('search', '', 16) ?></span>
      <input type="search" class="form-control" id="annSearch" placeholder="Search announcements" aria-label="Search announcements" autocomplete="off">
    </div>
  </div>
  <?php endif; ?>

  <!-- ---------------- All other notices, by month ---------------- -->
  <?php foreach ($byMonth as $month => $items): ?>
    <section class="ann-section" aria-label="<?= e($month) ?>">
      <div class="ann-month"><h2><?= e($month) ?></h2></div>
      <div class="ann-grid">
        <?php foreach ($items as $a):
          [$cls, $ic, $label] = announcementStyle($a['category']);
          $urgent = (int)$a['is_urgent'] === 1;
          $t = strtotime($a['published_at']);
        ?>
          <article class="ann-card <?= $cls ?> <?= $urgent ? 'is-urgent' : '' ?>" id="announcement-<?= (int)$a['announcement_id'] ?>"
                   data-category="<?= e(announcementKey($a['category'])) ?>" data-search="<?= e($searchText($a)) ?>"
                   aria-labelledby="ann-title-<?= (int)$a['announcement_id'] ?>">
            <div class="ann-card-media" aria-hidden="true">
              <span class="ann-card-icon"><?= icon($ic, '', 20) ?></span>
              <span class="ann-card-cat" style="margin-right: 70px;"><?= e($a['category'] ?: 'General') ?></span>
              <time class="ann-date" datetime="<?= date('c', $t) ?>">
                <span class="ann-date-m"><?= date('M', $t) ?></span><span class="ann-date-d"><?= date('j', $t) ?></span>
              </time>
            </div>
            <div class="ann-card-body">
              <div class="ann-card-tags">
                <?php if ($urgent): ?><span class="ann-tag is-urgent"><?= icon('alert-triangle', '', 11) ?> Urgent</span><?php endif; ?>
                <span class="ann-tag"><?= e($label ?: 'General') ?></span>
                <?php if ($isNew($a)): ?><span class="ann-tag is-new">New</span><?php endif; ?>
              </div>
              <h3 class="ann-card-title" id="ann-title-<?= (int)$a['announcement_id'] ?>"><?= e($a['title']) ?></h3>
              <div class="ann-text is-clamped" data-clamp><?= nl2br(e($a['content'])) ?></div>
              <button type="button" class="ann-more" hidden>Read more</button>
              <div class="ann-meta">
                <span class="ann-author">
                  <span class="avatar avatar-admin"><?= e(userInitials($a['author_name'] ?: 'Administration')) ?></span>
                  <span class="ann-author-name"><?= e($a['author_name'] ?: 'University Administration') ?></span>
                </span>
                <span class="ann-time" title="<?= e(formatDateTime($a['published_at'])) ?>"><?= icon('clock', '', 13) ?> <?= e(timeAgo($a['published_at'])) ?></span>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <div class="empty-state ann-empty" id="annNoResults" hidden>
    <div class="empty-state-icon"><?= icon('search', '', 22) ?></div>
    <div class="empty-state-title">No matching announcements</div>
    <p>Try another category or search term.</p>
    <button type="button" class="btn btn-secondary btn-sm" id="annReset">Show all announcements</button>
  </div>
<?php endif; ?>

<script>
(function () {
  // "Read more" only where the text is actually cut off
  document.querySelectorAll('[data-clamp]').forEach((text) => {
    const btn = text.nextElementSibling;
    if (!btn || !btn.classList.contains('ann-more')) return;
    const isFeatured = !!text.closest('.ann-featured');
    if (isFeatured) text.classList.add('is-clamped');
    requestAnimationFrame(() => {
      if (text.scrollHeight - text.clientHeight > 2) {
        btn.hidden = false;
        btn.addEventListener('click', () => {
          const open = text.classList.toggle('is-clamped') === false;
          btn.textContent = open ? 'Show less' : 'Read more';
          btn.setAttribute('aria-expanded', String(open));
        });
        btn.setAttribute('aria-expanded', 'false');
      } else {
        text.classList.remove('is-clamped');
      }
    });
  });

  // Category chips + search (client-side; every announcement is already on the page)
  const chips = Array.from(document.querySelectorAll('.ann-chip'));
  const search = document.getElementById('annSearch');
  const items = Array.from(document.querySelectorAll('.ann-featured, .ann-card'));
  const sections = Array.from(document.querySelectorAll('.ann-section'));
  const none = document.getElementById('annNoResults');
  let category = '';

  function apply() {
    const q = (search ? search.value : '').trim().toLowerCase();
    let shown = 0;
    items.forEach((el) => {
      const ok = (!category || el.dataset.category === category) && (!q || el.dataset.search.includes(q));
      el.hidden = !ok;
      if (ok) shown++;
    });
    sections.forEach((s) => { s.hidden = !s.querySelector('.ann-card:not([hidden])'); });
    if (none) none.hidden = shown > 0;
  }
  chips.forEach((chip) => chip.addEventListener('click', () => {
    category = chip.dataset.filter;
    chips.forEach((c) => c.setAttribute('aria-pressed', String(c === chip)));
    apply();
  }));
  if (search) search.addEventListener('input', apply);
  const reset = document.getElementById('annReset');
  if (reset) reset.addEventListener('click', () => {
    category = '';
    if (search) search.value = '';
    chips.forEach((c) => c.setAttribute('aria-pressed', String(c.dataset.filter === '')));
    apply();
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
