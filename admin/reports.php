<?php
// admin/reports.php - Analytical Reports & Resolution Monitoring
// (Use case: Generate Analytical Reports; IPO Output: Analytics & Reports)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/reports.php';

requireAdmin();

$range       = reportRangeFromRequest();
$summary     = reportSummary($pdo, $range);
$catStats    = reportByCategory($pdo, $range);
$prioStats   = reportByPriority($pdo, $range);
$trend       = reportMonthlyTrend($pdo, $range);
$deptStats   = reportByDepartment($pdo, $range);
$fbStats     = reportFeedback($pdo, $range);

$rangeQuery  = http_build_query(array_filter($range));
$rangeLabel  = ($range['from'] || $range['to'])
    ? ($range['from'] ? formatDate($range['from']) : 'Beginning') . ' – ' . ($range['to'] ? formatDate($range['to']) : 'Today')
    : 'All time';
$trendMax    = max(1, ...array_column($trend, 'submitted'));
$fbMax       = max(1, ...array_values($fbStats['distribution']));

$pct = fn($v) => $v === null ? '—' : $v . '%';

$pageTitle = "Reports & Monitoring | ICMS-DPT-RRT";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="page-header">
  <div class="page-title-wrap">
    <div class="page-eyebrow">Analytics</div>
    <h1 class="page-title">Reports & Resolution Monitoring</h1>
    <p class="page-desc">Resolution metrics, SLA compliance and feedback summaries · <strong><?= e($rangeLabel) ?></strong></p>
  </div>
  <div class="no-print" style="display: flex; gap: 8px; flex-wrap: wrap;">
    <a class="btn btn-secondary" href="../actions/export_csv.php?type=concerns<?= $rangeQuery ? '&' . e($rangeQuery) : '' ?>">
      <span style="display: inline-flex; align-items: center; gap: 6px;"><?= icon('file-text') ?> Export Concerns (CSV)</span>
    </a>
    <a class="btn btn-secondary" href="../actions/export_csv.php?type=summary<?= $rangeQuery ? '&' . e($rangeQuery) : '' ?>">
      <span style="display: inline-flex; align-items: center; gap: 6px;"><?= icon('bar-chart') ?> Export Summary (CSV)</span>
    </a>
    <button type="button" class="btn btn-secondary" onclick="window.print()">
      <span style="display: inline-flex; align-items: center; gap: 6px;"><?= icon('printer') ?> Print</span>
    </button>
  </div>
</div>

<!-- Date range filter -->
<form method="GET" class="report-filter-bar">
  <div class="form-group">
    <label class="form-label" for="repFrom">From</label>
    <input type="date" name="from" id="repFrom" class="form-control" value="<?= e($range['from'] ?? '') ?>">
  </div>
  <div class="form-group">
    <label class="form-label" for="repTo">To</label>
    <input type="date" name="to" id="repTo" class="form-control" value="<?= e($range['to'] ?? '') ?>">
  </div>
  <button type="submit" class="btn btn-primary">Apply</button>
  <?php if ($range['from'] || $range['to']): ?>
    <a href="reports.php" class="btn btn-secondary">Clear</a>
  <?php endif; ?>
  <div style="display: flex; gap: 6px; flex-wrap: wrap;">
    <?php
      $presets = [
          'Last 30 days' => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
          'This month'   => [date('Y-m-01'), date('Y-m-t')],
          'This year'    => [date('Y-01-01'), date('Y-12-31')],
      ];
      foreach ($presets as $label => [$f, $t]):
    ?>
      <a href="?from=<?= $f ?>&to=<?= $t ?>" class="btn btn-secondary btn-sm"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</form>

<!-- Headline metrics -->
<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-icon-wrap metric-icon-total"><?= icon('folder', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Concerns Received</span>
      <span class="metric-number"><?= $summary['total'] ?></span>
      <span class="text-xs text-muted"><?= $summary['pending'] ?> pending · <?= $summary['in_progress'] ?> in progress</span>
    </div>
  </div>
  <div class="metric-card">
    <div class="metric-icon-wrap metric-icon-resolved"><?= icon('check-circle', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Resolution Rate</span>
      <span class="metric-number"><?= $pct($summary['resolution_rate']) ?></span>
      <span class="text-xs text-muted"><?= $summary['resolved'] ?> resolved · <?= $summary['reopened'] ?> reopened<?= $summary['withdrawn'] ? ' · ' . $summary['withdrawn'] . ' withdrawn' : '' ?></span>
    </div>
  </div>
  <div class="metric-card">
    <div class="metric-icon-wrap" style="background:#d1fae5;color:#059669;"><?= icon('target', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">SLA Compliance</span>
      <span class="metric-number"><?= $pct($summary['sla_compliance']) ?></span>
      <span class="text-xs text-muted">resolved within target</span>
    </div>
  </div>
  <div class="metric-card">
    <div class="metric-icon-wrap" style="background:#fee2e2;color:#dc2626;"><?= icon('alert-triangle', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Open & SLA Breached</span>
      <span class="metric-number"><?= $summary['open_breached'] ?></span>
      <span class="text-xs text-muted"><?= $summary['active_rrt'] ?> active RRT / critical</span>
    </div>
  </div>
</div>

<div class="metrics-grid">
  <div class="metric-card">
    <div class="metric-icon-wrap metric-icon-pending"><?= icon('clock', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Avg. First Response</span>
      <span class="metric-number" style="font-size: 1.5rem;"><?= formatHours($summary['avg_first_response_hrs']) ?></span>
    </div>
  </div>
  <div class="metric-card">
    <div class="metric-icon-wrap metric-icon-progress"><?= icon('refresh', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Avg. Resolution Time</span>
      <span class="metric-number" style="font-size: 1.5rem;"><?= formatHours($summary['avg_resolution_hrs']) ?></span>
    </div>
  </div>
  <div class="metric-card">
    <div class="metric-icon-wrap metric-icon-total"><?= icon('zap', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Avg. SLA Target</span>
      <span class="metric-number" style="font-size: 1.5rem;"><?= formatHours($summary['avg_sla_target']) ?></span>
    </div>
  </div>
  <div class="metric-card">
    <div class="metric-icon-wrap" style="background:#fef3c7;color:#d97706;"><?= icon('star', '', 22) ?></div>
    <div class="metric-content">
      <span class="metric-label">Resolution Satisfaction</span>
      <span class="metric-number" style="font-size: 1.5rem;"><?= $summary['avg_satisfaction'] === null ? '—' : $summary['avg_satisfaction'] . ' / 5' ?></span>
      <span class="text-xs text-muted"><?= plural((int)$summary['satisfaction_count'], 'rating') ?></span>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Monthly trend -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Concerns Submitted per Month</h2>
    </div>
    <div class="card-body">
      <?php if (array_sum(array_column($trend, 'submitted')) === 0): ?>
        <p class="text-sm text-muted">No concerns in this period.</p>
      <?php else: ?>
        <div class="bar-chart" role="img" aria-label="Concerns submitted per month">
          <?php foreach ($trend as $m): ?>
            <div class="bar-chart-col" data-tip="<?= e($m['label']) ?>: <?= $m['submitted'] ?> submitted, <?= $m['resolved'] ?> resolved">
              <?php if ($m['submitted'] > 0): ?><span class="bar-chart-value"><?= $m['submitted'] ?></span><?php endif; ?>
              <div class="bar-chart-bar" style="height: <?= round($m['submitted'] / $trendMax * 100, 1) ?>%;"></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="bar-chart-labels">
          <?php foreach ($trend as $m): ?><span><?= e(date('M', strtotime($m['ym'] . '-01'))) ?></span><?php endforeach; ?>
        </div>
        <details style="margin-top: 12px;">
          <summary class="text-xs text-muted" style="cursor: pointer;">Show as table</summary>
          <table class="table" style="font-size: 0.8rem; margin-top: 6px;">
            <thead><tr><th>Month</th><th>Submitted</th><th>Resolved</th></tr></thead>
            <tbody>
              <?php foreach ($trend as $m): ?>
                <tr><td><?= e($m['label']) ?></td><td><?= $m['submitted'] ?></td><td><?= $m['resolved'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </details>
      <?php endif; ?>
    </div>
  </div>

  <!-- Feedback rating distribution -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Feedback Rating Distribution</h2>
      <span class="text-xs text-muted"><?= plural((int)$fbStats['total'], 'response') ?> · avg <?= $fbStats['avg_rating'] ?? '—' ?>/5</span>
    </div>
    <div class="card-body">
      <?php if ($fbStats['total'] === 0): ?>
        <p class="text-sm text-muted">No feedback in this period.</p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <?php for ($s = 5; $s >= 1; $s--): $n = $fbStats['distribution'][$s]; ?>
            <div style="display: grid; grid-template-columns: 56px 1fr 40px; align-items: center; gap: 10px;" title="<?= $s ?> star: <?= $n ?> response(s)">
              <span class="text-sm" style="font-weight: 600; display: inline-flex; align-items: center; gap: 3px;"><?= $s ?> <?= icon('star-filled', '', 12) ?></span>
              <div class="hbar-track"><div class="hbar-fill" style="width: <?= round($n / $fbMax * 100, 1) ?>%;"></div></div>
              <span class="text-sm" style="text-align: right;"><?= $n ?></span>
            </div>
          <?php endfor; ?>
        </div>
        <table class="table" style="margin-top: 16px; font-size: 0.85rem;">
          <thead><tr><th>Feedback Type</th><th>Count</th><th>Avg Rating</th></tr></thead>
          <tbody>
            <?php foreach ($fbStats['by_type'] as $t): ?>
              <tr>
                <td><?= e($t['feedback_type']) ?></td>
                <td><?= (int)$t['total'] ?></td>
                <td><?= round((float)$t['avg_rating'], 2) ?> / 5</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Category performance -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Category Performance</h2>
  </div>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>Category</th>
          <th>Received</th>
          <th>Resolved</th>
          <th style="width: 180px;">Resolution Rate</th>
          <th>Avg. Resolution</th>
          <th>SLA Compliance</th>
          <th>Open Breached</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($catStats as $cs):
          $rate = $cs['total'] > 0 ? round($cs['resolved'] / $cs['total'] * 100) : 0;
          $comp = $cs['resolved'] > 0 ? round($cs['on_time'] / $cs['resolved'] * 100, 1) . '%' : '—';
        ?>
          <tr>
            <td style="font-weight: 600;"><?= e($cs['category_name']) ?></td>
            <td><?= (int)$cs['total'] ?></td>
            <td><?= (int)$cs['resolved'] ?></td>
            <td>
              <div style="display: flex; align-items: center; gap: 8px;">
                <div class="hbar-track" style="flex: 1;"><div class="hbar-fill" style="width: <?= $rate ?>%;"></div></div>
                <span class="text-xs" style="width: 34px; text-align: right;"><?= $rate ?>%</span>
              </div>
            </td>
            <td><?= formatHours($cs['avg_resolution_hrs'] !== null ? (float)$cs['avg_resolution_hrs'] : null) ?></td>
            <td><?= $comp ?></td>
            <td><?= (int)$cs['open_breached'] > 0 ? '<span class="sla-pill sla-breached">' . (int)$cs['open_breached'] . '</span>' : '0' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid-main-side grid-wide-narrow">
  <!-- Priority / AI engine effectiveness -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Dynamic Prioritization vs. SLA Targets</h2>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>Priority</th><th>Tickets</th><th>Avg score</th><th>Target</th><th>Avg resolution</th><th>On time</th></tr></thead>
        <tbody>
          <?php foreach ($prioStats as $ps): ?>
            <tr>
              <td><?= renderPriorityBadge($ps['priority']) ?></td>
              <td style="font-weight: 700;"><?= (int)$ps['total'] ?></td>
              <td><?= $ps['avg_score'] !== null ? round((float)$ps['avg_score'], 1) : '—' ?></td>
              <td><?= (int)$ps['target_hrs'] ?> h</td>
              <td><?= formatHours($ps['avg_resolution_hrs'] !== null ? (float)$ps['avg_resolution_hrs'] : null) ?></td>
              <td><?= (int)$ps['resolved'] > 0 ? round($ps['on_time'] / $ps['resolved'] * 100) . '%' : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- By college -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Concerns by College / Department</h2>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>College</th><th>Concerns</th><th>Resolved</th></tr></thead>
        <tbody>
          <?php if (!$deptStats): ?>
            <tr><td colspan="3" class="text-muted">No data in this period.</td></tr>
          <?php endif; ?>
          <?php foreach ($deptStats as $d): ?>
            <tr><td><?= e($d['department']) ?></td><td><?= (int)$d['total'] ?></td><td><?= (int)$d['resolved'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ISO/IEC 25010 survey results: shown only after an administrator enters the real survey means -->
<?php $iso = isoResults($pdo); $isoMeans = array_map(fn($r) => (float)$r['mean_score'], $iso); ?>
<div class="card" id="iso" style="scroll-margin-top: 80px;">
  <div class="card-header">
    <div>
      <h2 class="card-title"><?= icon('award', '', 18) ?> ISO/IEC 25010 Software Quality Evaluation</h2>
      <div class="card-subtitle">Results of the user survey (paper Section 5.3). These are entered from the collected questionnaires, not computed from system data.</div>
    </div>
    <button type="button" class="btn btn-secondary btn-sm no-print" onclick="openModal('isoModal')"><?= icon('edit', '', 14) ?> <?= $iso ? 'Update results' : 'Enter survey results' ?></button>
  </div>
  <div class="card-body">
    <div class="iso-grid">
      <?php foreach (ISO_CHARACTERISTICS as $key => $info): $r = $iso[$key] ?? null; ?>
        <div class="iso-card <?= $r ? '' : 'iso-card-empty' ?>">
          <div class="iso-card-head">
            <span class="iso-card-name"><?= e($info['label']) ?></span>
            <span class="text-xs text-muted"><?= e($info['items']) ?></span>
          </div>
          <?php if ($r): [$verbal, $badge] = isoInterpretation((float)$r['mean_score']); ?>
            <div class="iso-score"><?= number_format((float)$r['mean_score'], 2) ?><span> / 5.00</span></div>
            <div class="iso-bar"><span style="width: <?= round(((float)$r['mean_score'] - 1) / 4 * 100) ?>%;"></span></div>
            <div class="iso-meta"><span class="badge-tag <?= $badge ?>"><?= e($verbal) ?></span><span class="text-xs text-muted"><?= plural((int)$r['respondents'], 'respondent') ?></span></div>
          <?php else: ?>
            <div class="iso-score iso-score-empty">Not yet evaluated</div>
            <p class="text-xs text-muted" style="margin: 6px 0 0;"><?= e($info['desc']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($iso): $overall = array_sum($isoMeans) / count($isoMeans); [$ov, $ob] = isoInterpretation($overall); $first = reset($iso); ?>
      <div class="iso-overall">
        <span>Overall mean of <?= plural(count($iso), 'characteristic') ?>: <strong><?= number_format($overall, 2) ?></strong></span>
        <span class="badge-tag <?= $ob ?>"><?= e($ov) ?></span>
        <span class="text-xs text-muted">
          <?= $first['survey_date'] ? 'Survey of ' . formatDate($first['survey_date']) . ' · ' : '' ?>Entered by <?= e($first['updated_by_name'] ?? 'an administrator') ?>, <?= e(timeAgo($first['updated_at'])) ?>
        </span>
      </div>
    <?php else: ?>
      <p class="text-sm text-muted" style="margin: 14px 0 0;">No results yet. Run the ISO 25010 questionnaire with the respondents, compute each characteristic's weighted mean, then click <strong>Enter survey results</strong>.</p>
    <?php endif; ?>
  </div>
</div>

<div class="modal-backdrop" id="isoModal" onclick="if(event.target === this) closeModal('isoModal')">
  <div class="modal-content" role="dialog" aria-labelledby="isoModalTitle" style="max-width: 560px;">
    <div class="modal-header">
      <h3 class="card-title" id="isoModalTitle">ISO 25010 Survey Results</h3>
      <button type="button" class="alert-close" onclick="closeModal('isoModal')" aria-label="Close">&times;</button>
    </div>
    <form action="../actions/iso_results_action.php" method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
        <p class="text-sm text-secondary" style="margin-top: 0;">Enter the weighted mean (1.00–5.00) and number of respondents for each characteristic, from the tally sheet of the questionnaire. Leave a row empty if it was not evaluated.</p>
        <div class="iso-form-grid">
          <span class="form-label">Characteristic</span><span class="form-label">Mean</span><span class="form-label">Respondents</span>
          <?php foreach (ISO_CHARACTERISTICS as $key => $info): $r = $iso[$key] ?? null; ?>
            <label for="isoMean_<?= $key ?>" class="text-sm font-semibold" style="align-self: center;"><?= e($info['label']) ?></label>
            <input type="number" name="mean[<?= $key ?>]" id="isoMean_<?= $key ?>" class="form-control" min="1" max="5" step="0.01" placeholder="e.g. 4.25" value="<?= $r ? e($r['mean_score']) : '' ?>">
            <input type="number" name="respondents[<?= $key ?>]" class="form-control" min="1" step="1" placeholder="e.g. 30" value="<?= $r ? (int)$r['respondents'] : '' ?>" aria-label="<?= e($info['label']) ?> respondents">
          <?php endforeach; ?>
        </div>
        <div class="form-group" style="margin: 14px 0 0;">
          <label class="form-label" for="isoDate">Survey date <span class="optional">(optional)</span></label>
          <input type="date" name="survey_date" id="isoDate" class="form-control" value="<?= e(reset($iso)['survey_date'] ?? '') ?>" style="max-width: 220px;">
        </div>
      </div>
      <div class="modal-footer">
        <?php if ($iso): ?>
          <button type="submit" name="action" value="clear" class="btn btn-ghost" style="margin-right: auto; color: var(--danger);" formnovalidate>Clear results</button>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" onclick="closeModal('isoModal')">Cancel</button>
        <button type="submit" name="action" value="save" class="btn btn-primary"><?= icon('check', '', 16) ?> Save results</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
