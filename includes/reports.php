<?php
// includes/reports.php - Analytics queries for admin/reports.php and CSV export
// (Use case: Generate Analytical Reports; IPO Output: "Resolution rate metrics & feedback summaries")
//
// Every function takes the same date range so the page and the CSV always agree.
// Range filters on concerns.date_submitted (inclusive). Null = unbounded.

/**
 * Read & sanitize ?from=YYYY-MM-DD&to=YYYY-MM-DD from the query string.
 */
function reportRangeFromRequest(): array {
    $valid = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false ? $d : null;
    $from = $valid($_GET['from'] ?? null);
    $to   = $valid($_GET['to'] ?? null);
    if ($from && $to && $from > $to) {
        [$from, $to] = [$to, $from];
    }
    return ['from' => $from, 'to' => $to];
}

/**
 * WHERE fragment + params for the range, applied to alias `c`.
 */
function reportRangeSql(array $range): array {
    $where = [];
    $params = [];
    if ($range['from']) {
        $where[] = 'c.date_submitted >= :from';
        $params['from'] = $range['from'] . ' 00:00:00';
    }
    if ($range['to']) {
        $where[] = 'c.date_submitted <= :to';
        $params['to'] = $range['to'] . ' 23:59:59';
    }
    return [$where ? ' AND ' . implode(' AND ', $where) : '', $params];
}

function reportQuery(PDO $pdo, string $sql, array $range): array {
    [$rangeSql, $params] = reportRangeSql($range);
    $stmt = $pdo->prepare(str_replace('{RANGE}', $rangeSql, $sql));
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function reportSummary(PDO $pdo, array $range): array {
    $row = reportQuery($pdo, "
        SELECT
            COUNT(*)                                                         AS total,
            SUM(c.status_id = 1)                                             AS pending,
            SUM(c.status_id = 2)                                             AS in_progress,
            SUM(c.status_id = 3)                                             AS resolved,
            SUM(c.status_id = 4)                                             AS withdrawn,
            AVG(c.sla_hours)                                                 AS avg_sla_target,
            AVG(TIMESTAMPDIFF(MINUTE, c.date_submitted, c.first_response_at)) / 60 AS avg_first_response_hrs,
            AVG(CASE WHEN c.status_id = 3 THEN TIMESTAMPDIFF(MINUTE, c.date_submitted, c.resolved_at) END) / 60 AS avg_resolution_hrs,
            SUM(c.status_id = 3 AND c.resolved_at <= c.sla_due_at)           AS resolved_on_time,
            SUM(c.status_id IN (1, 2) AND c.sla_due_at < NOW())                   AS open_breached,
            SUM(c.status_id IN (1, 2) AND (c.priority = 'Critical' OR c.is_rrt_alert = 1)) AS active_rrt,
            SUM(c.reopen_count > 0)                                          AS reopened
        FROM concerns c
        WHERE 1 = 1 {RANGE}
    ", $range)[0];

    $fb = reportQuery($pdo, "
        SELECT AVG(f.rating) AS avg_rating, COUNT(*) AS ratings
        FROM feedback f JOIN concerns c ON c.concern_id = f.concern_id
        WHERE 1 = 1 {RANGE}
    ", $range)[0];

    $total    = (int)$row['total'];
    $resolved = (int)$row['resolved'];
    return [
        'total'                  => $total,
        'pending'                => (int)$row['pending'],
        'in_progress'            => (int)$row['in_progress'],
        'resolved'               => $resolved,
        'withdrawn'              => (int)$row['withdrawn'],
        // Withdrawn concerns never needed resolving, so they are left out of the rate
        'resolution_rate'        => ($total - (int)$row['withdrawn']) > 0 ? round($resolved / ($total - (int)$row['withdrawn']) * 100, 1) : null,
        'avg_sla_target'         => $row['avg_sla_target'] !== null ? round((float)$row['avg_sla_target'], 1) : null,
        'avg_first_response_hrs' => $row['avg_first_response_hrs'] !== null ? round((float)$row['avg_first_response_hrs'], 1) : null,
        'avg_resolution_hrs'     => $row['avg_resolution_hrs'] !== null ? round((float)$row['avg_resolution_hrs'], 1) : null,
        'sla_compliance'         => $resolved > 0 ? round((int)$row['resolved_on_time'] / $resolved * 100, 1) : null,
        'open_breached'          => (int)$row['open_breached'],
        'active_rrt'             => (int)$row['active_rrt'],
        'reopened'               => (int)$row['reopened'],
        'reopen_rate'            => $total > 0 ? round((int)$row['reopened'] / $total * 100, 1) : null,
        'avg_satisfaction'       => $fb['avg_rating'] !== null ? round((float)$fb['avg_rating'], 2) : null,
        'satisfaction_count'     => (int)$fb['ratings'],
    ];
}

function reportByCategory(PDO $pdo, array $range): array {
    [$rangeSql, $params] = reportRangeSql($range);
    // Range goes in the JOIN so categories with zero tickets still appear
    $stmt = $pdo->prepare("
        SELECT cat.category_name,
               COUNT(c.concern_id)                                     AS total,
               COALESCE(SUM(c.status_id = 3), 0)                       AS resolved,
               AVG(CASE WHEN c.status_id = 3 THEN TIMESTAMPDIFF(MINUTE, c.date_submitted, c.resolved_at) END) / 60 AS avg_resolution_hrs,
               COALESCE(SUM(c.status_id = 3 AND c.resolved_at <= c.sla_due_at), 0) AS on_time,
               COALESCE(SUM(c.status_id IN (1, 2) AND c.sla_due_at < NOW()), 0)       AS open_breached
        FROM categories cat
        LEFT JOIN concerns c ON c.category_id = cat.category_id AND c.status_id <> 4 {$rangeSql}
        GROUP BY cat.category_id, cat.category_name
        ORDER BY total DESC, cat.category_name
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function reportByPriority(PDO $pdo, array $range): array {
    $rows = reportQuery($pdo, "
        SELECT c.priority,
               COUNT(*)                         AS total,
               AVG(c.ai_urgency_score)          AS avg_score,
               SUM(c.status_id = 3)             AS resolved,
               AVG(CASE WHEN c.status_id = 3 THEN TIMESTAMPDIFF(MINUTE, c.date_submitted, c.resolved_at) END) / 60 AS avg_resolution_hrs,
               SUM(c.status_id = 3 AND c.resolved_at <= c.sla_due_at) AS on_time
        FROM concerns c
        WHERE c.status_id <> 4 {RANGE}
        GROUP BY c.priority
    ", $range);
    // Always return all four tiers in severity order
    $byPriority = array_column($rows, null, 'priority');
    $out = [];
    foreach (SLA_HOURS_BY_PRIORITY as $p => $hours) {
        $r = $byPriority[$p] ?? ['total' => 0, 'avg_score' => null, 'resolved' => 0, 'avg_resolution_hrs' => null, 'on_time' => 0];
        $out[] = ['priority' => $p, 'target_hrs' => $hours] + $r;
    }
    return $out;
}

/**
 * Concerns submitted / resolved per month. Without a range: last 12 months.
 * Submitted = concerns filed that month (date_submitted).
 * Resolved  = concerns currently Resolved, counted in the month they were resolved (resolved_at),
 *             so resolving an older concern updates the current month's count. Reopening clears
 *             resolved_at and status, so a reopened concern drops out again automatically.
 */
function reportMonthlyTrend(PDO $pdo, array $range): array {
    // When the concern was resolved (resolved_at; older rows without it fall back to their last update)
    $resolvedAt = "COALESCE(c.resolved_at, c.updated_at, c.date_submitted)";
    if (!$range['from'] && !$range['to']) {
        // Anchor on the latest activity (submission or resolution) so recent resolutions are always in view
        $latest = $pdo->query("
            SELECT GREATEST(COALESCE(MAX(date_submitted), '1970-01-01'),
                            COALESCE(MAX(CASE WHEN status_id = 3 THEN COALESCE(resolved_at, updated_at, date_submitted) END), '1970-01-01'))
            FROM concerns
        ")->fetchColumn();
        $latest = ($latest && $latest > '1970-01-01') ? $latest : date('Y-m-d');
        $range = ['from' => date('Y-m-01', strtotime('-11 months', strtotime($latest))), 'to' => date('Y-m-t', strtotime($latest))];
    }
    $submitted = reportQuery($pdo, "
        SELECT DATE_FORMAT(c.date_submitted, '%Y-%m') AS ym, COUNT(*) AS n
        FROM concerns c
        WHERE 1 = 1 {RANGE}
        GROUP BY ym
    ", $range);

    // Same range, applied to the resolution date instead of the submission date
    $where = ['c.status_id = 3'];
    $params = [];
    if ($range['from']) { $where[] = "{$resolvedAt} >= :rfrom"; $params['rfrom'] = $range['from'] . ' 00:00:00'; }
    if ($range['to'])   { $where[] = "{$resolvedAt} <= :rto";   $params['rto']   = $range['to'] . ' 23:59:59'; }
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT({$resolvedAt}, '%Y-%m') AS ym, COUNT(*) AS n
        FROM concerns c
        WHERE " . implode(' AND ', $where) . "
        GROUP BY ym
    ");
    $stmt->execute($params);
    $resolved = $stmt->fetchAll();

    $byMonth = [];
    foreach ($submitted as $r) { $byMonth[$r['ym']]['submitted'] = (int)$r['n']; }
    foreach ($resolved as $r)  { $byMonth[$r['ym']]['resolved']  = (int)$r['n']; }

    // Fill empty months so gaps are visible (cap at 24 bars)
    $out = [];
    $cursor = strtotime(date('Y-m-01', strtotime($range['from'] ?? $range['to'])));
    $end    = strtotime(date('Y-m-01', strtotime($range['to'] ?? $range['from'])));
    while ($cursor <= $end && count($out) < 24) {
        $ym = date('Y-m', $cursor);
        $out[] = [
            'ym'        => $ym,
            'label'     => date('M Y', $cursor),
            'submitted' => (int)($byMonth[$ym]['submitted'] ?? 0),
            'resolved'  => (int)($byMonth[$ym]['resolved'] ?? 0),
        ];
        $cursor = strtotime('+1 month', $cursor);
    }
    return $out;
}

function reportByDepartment(PDO $pdo, array $range): array {
    return reportQuery($pdo, "
        SELECT IF(c.is_anonymous = 1, 'Not disclosed (anonymous)', s.department) AS department,
               COUNT(*) AS total, SUM(c.status_id = 3) AS resolved
        FROM concerns c JOIN students s ON s.student_id = c.student_id
        WHERE c.status_id <> 4 {RANGE}
        GROUP BY IF(c.is_anonymous = 1, 'Not disclosed (anonymous)', s.department)
        ORDER BY total DESC
    ", $range);
}

/**
 * Feedback summary. General feedback is filtered by its own submitted_at;
 * resolution ratings follow the concern range.
 */
function reportFeedback(PDO $pdo, array $range): array {
    $where = [];
    $params = [];
    if ($range['from']) { $where[] = 'f.submitted_at >= :from'; $params['from'] = $range['from'] . ' 00:00:00'; }
    if ($range['to'])   { $where[] = 'f.submitted_at <= :to';   $params['to']   = $range['to'] . ' 23:59:59'; }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $dist = array_fill(1, 5, 0);
    $stmt = $pdo->prepare("SELECT f.rating, COUNT(*) AS n FROM feedback f {$w} GROUP BY f.rating");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $dist[max(1, min(5, (int)$r['rating']))] += (int)$r['n'];
    }

    $stmt = $pdo->prepare("
        SELECT f.feedback_type, COUNT(*) AS total, AVG(f.rating) AS avg_rating
        FROM feedback f {$w}
        GROUP BY f.feedback_type
        ORDER BY total DESC
    ");
    $stmt->execute($params);
    $byType = $stmt->fetchAll();

    $total = array_sum($dist);
    $sum = 0;
    foreach ($dist as $stars => $n) {
        $sum += $stars * $n;
    }
    return [
        'total'        => $total,
        'avg_rating'   => $total > 0 ? round($sum / $total, 2) : null,
        'distribution' => $dist,
        'by_type'      => $byType,
    ];
}

/**
 * Hours → "36.5 h" / "2.1 d" display.
 */
function formatHours(?float $hours): string {
    if ($hours === null) {
        return '—';
    }
    return $hours >= 48 ? round($hours / 24, 1) . ' d' : round($hours, 1) . ' h';
}

/** ISO/IEC 25010 characteristics evaluated by the survey (keys stored in iso_evaluation_results). */
const ISO_CHARACTERISTICS = [
    'usability'   => ['label' => 'Usability', 'items' => 'U1–U8', 'desc' => 'Ease of reporting, clear dashboards and status tracking.'],
    'reliability' => ['label' => 'Reliability', 'items' => 'R1–R6', 'desc' => 'No lost concerns, consistent records, stable status changes.'],
    'performance' => ['label' => 'Performance Efficiency', 'items' => 'P1–P6', 'desc' => 'Fast pages, instant urgency scoring, quick search.'],
    'security'    => ['label' => 'Security', 'items' => 'S1–S7', 'desc' => 'Protected accounts, role access, lockout and audit trail.'],
];

/** Verbal interpretation of a 5-point Likert mean (equal intervals of 0.80). */
function isoInterpretation(float $mean): array {
    return match (true) {
        $mean >= 4.21 => ['Strongly Agree', 'badge-green'],
        $mean >= 3.41 => ['Agree', 'badge-blue'],
        $mean >= 2.61 => ['Neutral', 'badge-gray'],
        $mean >= 1.81 => ['Disagree', 'badge-amber'],
        default       => ['Strongly Disagree', 'badge-red'],
    };
}

/** Saved survey results keyed by characteristic. */
function isoResults(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query("SELECT r.*, a.name AS updated_by_name FROM iso_evaluation_results r LEFT JOIN administrators a ON a.admin_id = r.updated_by") as $r) {
        $out[$r['characteristic']] = $r;
    }
    return $out;
}

