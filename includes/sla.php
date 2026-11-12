<?php
// includes/sla.php - SLA (Service Level Agreement) tracking helpers
//
// States:
//   on_track  - open, more than 25% of the SLA window left
//   at_risk   - open, 25% or less of the SLA window left
//   breached  - open, past the deadline
//   met       - resolved on or before the deadline
//   missed    - resolved after the deadline

const SLA_AT_RISK_RATIO = 0.25;

function computeSlaDueAt(string $submittedAt, int $slaHours): string {
    return date('Y-m-d H:i:s', strtotime($submittedAt) + $slaHours * 3600);
}

/** "within 3 days" style target for students (24h -> "within 1 day"). */
function slaTargetText(int $hours): string {
    if ($hours % 24 === 0) {
        $d = intdiv($hours, 24);
        return 'within ' . $d . ' day' . ($d === 1 ? '' : 's');
    }
    return "within {$hours} hours";
}

/** Long, plain duration: "2 days 4 hours", "5 hours 10 minutes", "15 minutes". */
function formatDurationLong(int $seconds): string {
    $seconds = abs($seconds);
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);
    $plural = fn(int $n, string $unit) => $n . ' ' . $unit . ($n === 1 ? '' : 's');
    if ($d > 0) return $plural($d, 'day') . ($h > 0 ? ' ' . $plural($h, 'hour') : '');
    if ($h > 0) return $plural($h, 'hour') . ($m > 0 ? ' ' . $plural($m, 'minute') : '');
    return $plural(max(1, $m), 'minute');
}

/**
 * How long similar concerns actually took to resolve (same priority, and same category when there is
 * enough history). Returns null with fewer than 3 resolved examples.
 * @return array{hours: float, count: int, basis: string}|null
 */
function typicalResolutionTime(PDO $pdo, string $priority, int $categoryId, int $exceptConcernId = 0): ?array {
    $q = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, date_submitted, resolved_at)) / 60 AS hrs, COUNT(*) AS n
        FROM concerns
        WHERE status_id = 3 AND resolved_at IS NOT NULL AND priority = :p AND concern_id <> :x
          AND (:cat = 0 OR category_id = :cat2)
    ");
    foreach ([[$categoryId, 'category'], [0, 'priority']] as [$cat, $basis]) {
        $q->execute(['p' => $priority, 'x' => $exceptConcernId, 'cat' => $cat, 'cat2' => $cat]);
        $row = $q->fetch();
        if ((int)$row['n'] >= 3) {
            return ['hours' => (float)$row['hrs'], 'count' => (int)$row['n'], 'basis' => $basis];
        }
    }
    return null;
}

function formatDuration(int $seconds): string {
    $seconds = abs($seconds);
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($d > 0) return "{$d}d {$h}h";
    if ($h > 0) return "{$h}h {$m}m";
    return max(1, $m) . "m";
}

/**
 * Derive SLA state for a concern row (needs sla_due_at, sla_hours, resolved_at, status_name/status_id).
 */
function getSlaState(array $c, ?int $now = null): array {
    $now = $now ?? time();
    $due = !empty($c['sla_due_at'])
        ? strtotime($c['sla_due_at'])
        : strtotime($c['date_submitted'] ?? 'now') + (int)($c['sla_hours'] ?? 72) * 3600;

    $isWithdrawn = (isset($c['status_name']) && $c['status_name'] === 'Withdrawn')
        || (isset($c['status_id']) && (int)$c['status_id'] === 4);
    if ($isWithdrawn) {
        return [
            'state'     => 'withdrawn',
            'label'     => 'Withdrawn',
            'detail'    => 'Withdrawn by the student',
            'due_at'    => date('Y-m-d H:i:s', $due),
            'remaining' => 0,
        ];
    }

    $isResolved = (isset($c['status_name']) && $c['status_name'] === 'Resolved')
        || (isset($c['status_id']) && (int)$c['status_id'] === 3);

    if ($isResolved) {
        $resolvedAt = !empty($c['resolved_at']) ? strtotime($c['resolved_at']) : $now;
        $met = $resolvedAt <= $due;
        return [
            'state'     => $met ? 'met' : 'missed',
            'label'     => $met ? 'SLA Met' : 'SLA Missed',
            'detail'    => $met ? 'Resolved within target' : 'Resolved ' . formatDuration($resolvedAt - $due) . ' late',
            'due_at'    => date('Y-m-d H:i:s', $due),
            'remaining' => 0,
        ];
    }

    $remaining = $due - $now;
    $window    = max(1, (int)($c['sla_hours'] ?? 72) * 3600);

    if ($remaining < 0) {
        $state = 'breached';
        $label = 'SLA Breached';
        $detail = 'Overdue by ' . formatDuration($remaining);
    } elseif ($remaining <= $window * SLA_AT_RISK_RATIO) {
        $state = 'at_risk';
        $label = 'At Risk';
        $detail = formatDuration($remaining) . ' left';
    } else {
        $state = 'on_track';
        $label = 'On Track';
        $detail = formatDuration($remaining) . ' left';
    }

    return [
        'state'     => $state,
        'label'     => $label,
        'detail'    => $detail,
        'due_at'    => date('Y-m-d H:i:s', $due),
        'remaining' => $remaining,
    ];
}

function renderSlaBadge(array $concern, bool $withDetail = true): string {
    $sla = getSlaState($concern);
    $html = '<span class="sla-pill sla-' . $sla['state'] . '" title="Due: ' . htmlspecialchars(date('M j, Y · g:i A', strtotime($sla['due_at'])), ENT_QUOTES) . '">'
          . htmlspecialchars($sla['label']) . '</span>';
    if ($withDetail) {
        $html .= '<span class="sla-detail">' . htmlspecialchars($sla['detail']) . '</span>';
    }
    return $html;
}


// ---- Reopening resolved tickets -------------------------------------------
const REOPEN_WINDOW_DAYS = 7;   // student may reopen within this many days of resolution
const REOPEN_MAX         = 3;   // maximum reopens per ticket

/**
 * Whether the owning student may reopen this concern now.
 */
function canReopenConcern(array $c): bool {
    $resolved = (isset($c['status_id']) && (int)$c['status_id'] === 3)
        || (isset($c['status_name']) && $c['status_name'] === 'Resolved');
    return $resolved
        && !empty($c['resolved_at'])
        && (int)($c['reopen_count'] ?? 0) < REOPEN_MAX
        && strtotime($c['resolved_at']) >= time() - REOPEN_WINDOW_DAYS * 86400;
}

// ---- Editing a concern -----------------------------------------------------
/**
 * A student may edit their concern only while it is Pending and no administrator
 * has responded yet (so staff never answer a description that later changes).
 */
function canEditConcern(array $c): bool {
    return (int)($c['status_id'] ?? 0) === 1 && empty($c['first_response_at']);
}
