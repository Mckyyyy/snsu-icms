<?php
// includes/pagination.php - Server-side pagination helpers

const DEFAULT_PER_PAGE = 15;

/**
 * Compute page/offset from ?page= and the total row count.
 */
const PER_PAGE_OPTIONS = [10, 15, 20, 50, 100];

function paginate(int $totalRows, int $perPage = DEFAULT_PER_PAGE): array {
    // The viewer may pick another page size (?per_page=), limited to PER_PAGE_OPTIONS
    $requested = (int)($_GET['per_page'] ?? 0);
    if (in_array($requested, PER_PAGE_OPTIONS, true)) {
        $perPage = $requested;
    }
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = min($totalPages, max(1, (int)($_GET['page'] ?? 1)));
    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'offset'      => ($page - 1) * $perPage,
        'total_rows'  => $totalRows,
        'total_pages' => $totalPages,
        'from'        => $totalRows ? ($page - 1) * $perPage + 1 : 0,
        'to'          => min($totalRows, $page * $perPage),
    ];
}

/**
 * Query string for page $n that keeps all other current filters.
 */
function pageUrl(int $n): string {
    $params = $_GET;
    $params['page'] = $n;
    return '?' . http_build_query($params);
}

/**
 * "Showing X to Y of Z entries" + numbered page links with ellipses.
 */
function renderPagination(array $p): string {
    $html = '<span class="pager-info text-sm text-muted">';
    $html .= $p['total_rows']
        ? '<span>Showing <strong>' . $p['from'] . '–' . $p['to'] . '</strong> of <strong>' . $p['total_rows'] . '</strong></span>'
        : '<span>No records</span>';
    if ($p['total_rows'] > min(PER_PAGE_OPTIONS)) {
        $html .= '<select class="form-control per-page-select" aria-label="Rows per page" onchange="location.href = this.value">';
        foreach (PER_PAGE_OPTIONS as $n) {
            $params = $_GET;
            $params['per_page'] = $n;
            unset($params['page']);
            $html .= '<option value="?' . e(http_build_query($params)) . '"' . ($n === $p['per_page'] ? ' selected' : '') . ">{$n} per page</option>";
        }
        $html .= '</select>';
    }
    $html .= '</span>';
    if ($p['total_pages'] <= 1) {
        return $html;
    }

    $page = $p['page'];
    $last = $p['total_pages'];
    $pages = array_unique(array_filter([1, $page - 1, $page, $page + 1, $last], fn($n) => $n >= 1 && $n <= $last));
    sort($pages);

    $html .= '<nav aria-label="Pagination" style="display: flex; gap: 4px; align-items: center;">';
    $html .= $page > 1
        ? '<a class="btn btn-secondary btn-sm" href="' . e(pageUrl($page - 1)) . '" aria-label="Previous page">&lt;</a>'
        : '<span class="btn btn-secondary btn-sm" aria-disabled="true" style="opacity: .5;">&lt;</span>';

    $prev = 0;
    foreach ($pages as $n) {
        if ($n - $prev > 1) {
            $html .= '<span style="padding: 0 4px; color: var(--text-muted);">…</span>';
        }
        $html .= $n === $page
            ? '<span class="btn btn-primary btn-sm" aria-current="page">' . $n . '</span>'
            : '<a class="btn btn-secondary btn-sm" href="' . e(pageUrl($n)) . '">' . $n . '</a>';
        $prev = $n;
    }

    $html .= $page < $last
        ? '<a class="btn btn-secondary btn-sm" href="' . e(pageUrl($page + 1)) . '" aria-label="Next page">&gt;</a>'
        : '<span class="btn btn-secondary btn-sm" aria-disabled="true" style="opacity: .5;">&gt;</span>';
    return $html . '</nav>';
}

/**
 * SQL condition for an SLA state filter on alias `c`.
 */
function slaStateSql(string $state): ?string {
    $atRisk = "TIMESTAMPDIFF(SECOND, NOW(), c.sla_due_at) <= c.sla_hours * 3600 * " . SLA_AT_RISK_RATIO;
    return match ($state) {
        'breached' => "(c.status_id IN (1, 2) AND c.sla_due_at < NOW())",
        'at_risk'  => "(c.status_id IN (1, 2) AND c.sla_due_at >= NOW() AND {$atRisk})",
        'on_track' => "(c.status_id IN (1, 2) AND c.sla_due_at >= NOW() AND NOT ({$atRisk}))",
        'met'      => "(c.status_id = 3 AND c.resolved_at <= c.sla_due_at)",
        'missed'   => "(c.status_id = 3 AND c.resolved_at > c.sla_due_at)",
        default    => null,
    };
}

/**
 * Build WHERE clause + params from the shared concern list filters (?q, category, status, priority, sla).
 */
function concernFilterSql(bool $includeStudentName = true, ?int $currentAdminId = null): array {
    $where = ['1 = 1'];
    $params = [];

    // Archived tickets (administrator lists only): hidden from the queue, listed with ?archived=1
    if ($currentAdminId !== null) {
        $where[] = !empty($_GET['archived']) ? 'c.archived_at IS NOT NULL' : 'c.archived_at IS NULL';
    }

    // ?assigned=me | none | <admin_id>  (administrator lists only)
    $assigned = (string)($_GET['assigned'] ?? '');
    if ($currentAdminId !== null && $assigned !== '') {
        if ($assigned === 'me') {
            $where[] = 'c.assigned_admin_id = :asg';
            $params['asg'] = $currentAdminId;
        } elseif ($assigned === 'none') {
            $where[] = 'c.assigned_admin_id IS NULL';
        } elseif (ctype_digit($assigned)) {
            $where[] = 'c.assigned_admin_id = :asg';
            $params['asg'] = (int)$assigned;
        }
    }

    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
        $cols = ['c.title', 'c.ticket_id', 'c.description'];
        if ($includeStudentName) {
            $cols[] = 's.name';
            $cols[] = 's.student_no';
        }
        $likes = [];
        foreach ($cols as $i => $col) {
            // Anonymous concerns can never be found by the sender's name or student number
            $likes[] = str_starts_with($col, 's.') ? "(c.is_anonymous = 0 AND {$col} LIKE :q{$i})" : "{$col} LIKE :q{$i}";
            $params["q{$i}"] = '%' . $q . '%';
        }
        $where[] = '(' . implode(' OR ', $likes) . ')';
    }
    if (($cat = (int)($_GET['category'] ?? 0)) > 0) {
        $where[] = 'c.category_id = :cat';
        $params['cat'] = $cat;
    }
    if (($st = (int)($_GET['status'] ?? 0)) > 0) {
        $where[] = 'c.status_id = :st';
        $params['st'] = $st;
    }
    $prio = (string)($_GET['priority'] ?? '');
    if (array_key_exists($prio, SLA_HOURS_BY_PRIORITY)) {
        $where[] = 'c.priority = :prio';
        $params['prio'] = $prio;
    } elseif ($prio === 'new' && $currentAdminId !== null) {
        // "New" (administrator lists only): still Pending and no administrator has responded yet
        $where[] = '(c.status_id = 1 AND c.first_response_at IS NULL)';
    }
    if ($slaSql = slaStateSql((string)($_GET['sla'] ?? ''))) {
        $where[] = $slaSql;
    }
    return [implode(' AND ', $where), $params];
}

/**
 * Chips for the filters currently applied, each with a link that removes just that filter.
 * $labels maps a GET key to [label, value-to-text map or callable]; unknown values print as-is.
 */
function renderActiveFilters(array $labels, int $total): string {
    $chips = [];
    foreach ($labels as $key => [$label, $values]) {
        $raw = $_GET[$key] ?? '';
        if ($raw === '' || $raw === '0') {
            continue;
        }
        $text = is_callable($values) ? $values($raw) : ($values[$raw] ?? $raw);
        if ($text === null || $text === '') {
            continue;
        }
        $params = $_GET;
        unset($params[$key], $params['page']);
        $chips[] = '<a class="filter-chip" href="?' . e(http_build_query($params)) . '" title="Remove this filter">'
                 . e($label) . ': ' . e((string)$text) . ' <span class="filter-chip-x" aria-hidden="true">&times;</span></a>';
    }
    if (!$chips) {
        return '';
    }
    $keep = array_intersect_key($_GET, ['per_page' => 1]);
    return '<div class="active-filters"><span class="active-filters-label">Filtered by</span>' . implode('', $chips)
         . '<a class="filter-chip-clear" href="?' . e(http_build_query($keep)) . '">Clear all</a>'
         . '<span class="result-count">' . plural($total, 'result') . '</span></div>';
}

