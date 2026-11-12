<?php
// actions/export_csv.php - CSV export of report data (admin only, read-only GET)
//   ?type=concerns  : one row per concern with SLA/lifecycle fields
//   ?type=summary   : headline metrics + category + priority + feedback tables
//   optional &from=YYYY-MM-DD&to=YYYY-MM-DD
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/reports.php';

requireAdmin();

$type  = $_GET['type'] ?? 'concerns';
$range = reportRangeFromRequest();

/**
 * Neutralize spreadsheet formula injection (cells starting with = + - @ tab CR).
 */
function csvSafe($value): string {
    $v = (string)($value ?? '');
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

function csvRow($out, array $cells): void {
    fputcsv($out, array_map('csvSafe', $cells));
}

$suffix = ($range['from'] ?? 'start') . '_to_' . ($range['to'] ?? date('Y-m-d'));
$filename = "icms_{$type}_{$suffix}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ñ, –, etc. correctly

if ($type === 'summary') {
    $s = reportSummary($pdo, $range);
    csvRow($out, ['ICMS-DPT-RRT Report Summary']);
    csvRow($out, ['Period', ($range['from'] ?? 'All') . ' to ' . ($range['to'] ?? 'Present')]);
    csvRow($out, ['Generated', date('Y-m-d H:i:s')]);
    csvRow($out, []);
    csvRow($out, ['Metric', 'Value']);
    foreach ([
        'Concerns received'            => $s['total'],
        'Pending'                      => $s['pending'],
        'In progress'                  => $s['in_progress'],
        'Resolved'                     => $s['resolved'],
        'Withdrawn by student'         => $s['withdrawn'],
        'Resolution rate (%)'          => $s['resolution_rate'],
        'SLA compliance (%)'           => $s['sla_compliance'],
        'Open & SLA breached'          => $s['open_breached'],
        'Active RRT / critical'        => $s['active_rrt'],
        'Reopened by student'          => $s['reopened'],
        'Reopen rate (%)'              => $s['reopen_rate'],
        'Avg first response (hours)'   => $s['avg_first_response_hrs'],
        'Avg resolution time (hours)'  => $s['avg_resolution_hrs'],
        'Avg SLA target (hours)'       => $s['avg_sla_target'],
        'Resolution satisfaction (/5)' => $s['avg_satisfaction'],
        'Resolution ratings received'  => $s['satisfaction_count'],
    ] as $k => $v) {
        csvRow($out, [$k, $v ?? '']);
    }

    csvRow($out, []);
    csvRow($out, ['Category', 'Received', 'Resolved', 'Avg resolution (hours)', 'Resolved on time', 'Open breached']);
    foreach (reportByCategory($pdo, $range) as $c) {
        csvRow($out, [$c['category_name'], $c['total'], $c['resolved'],
            $c['avg_resolution_hrs'] !== null ? round((float)$c['avg_resolution_hrs'], 1) : '', $c['on_time'], $c['open_breached']]);
    }

    csvRow($out, []);
    csvRow($out, ['Priority', 'Tickets', 'Avg urgency score', 'SLA target (hours)', 'Avg resolution (hours)', 'Resolved on time']);
    foreach (reportByPriority($pdo, $range) as $p) {
        csvRow($out, [$p['priority'], $p['total'], $p['avg_score'] !== null ? round((float)$p['avg_score'], 1) : '',
            $p['target_hrs'], $p['avg_resolution_hrs'] !== null ? round((float)$p['avg_resolution_hrs'], 1) : '', $p['on_time']]);
    }

    $fb = reportFeedback($pdo, $range);
    csvRow($out, []);
    csvRow($out, ['Feedback rating', 'Responses']);
    for ($i = 5; $i >= 1; $i--) {
        csvRow($out, ["{$i} star", $fb['distribution'][$i]]);
    }
    csvRow($out, ['Average', $fb['avg_rating'] ?? '']);
} else {
    [$rangeSql, $params] = reportRangeSql($range);
    $stmt = $pdo->prepare("
        SELECT c.*, cat.category_name, st.status_name, " . anonStudentColumns() . ",
               f.rating AS satisfaction_rating
        FROM concerns c
        JOIN categories cat ON cat.category_id = c.category_id
        JOIN statuses st ON st.status_id = c.status_id
        JOIN students s ON s.student_id = c.student_id
        LEFT JOIN feedback f ON f.concern_id = c.concern_id
        WHERE 1 = 1 {$rangeSql}
        ORDER BY c.date_submitted DESC
    ");
    $stmt->execute($params);

    csvRow($out, ['Ticket ID', 'Title', 'Category', 'Status', 'Priority', 'Urgency Score', 'RRT Alert',
        'Student No', 'Student Name', 'College', 'Date Submitted', 'SLA Hours', 'SLA Due', 'First Response',
        'Resolved At', 'Resolution Hours', 'SLA State', 'Times Reopened', 'Satisfaction (/5)']);
    while ($c = $stmt->fetch()) {
        $resolutionHrs = $c['resolved_at']
            ? round((strtotime($c['resolved_at']) - strtotime($c['date_submitted'])) / 3600, 1)
            : '';
        csvRow($out, [
            $c['ticket_id'], $c['title'], $c['category_name'], $c['status_name'], $c['priority'],
            $c['ai_urgency_score'], $c['is_rrt_alert'] ? 'Yes' : 'No',
            $c['student_no'] ?? '', $c['student_name'], $c['student_dept'] ?? 'Not disclosed', $c['date_submitted'],
            $c['sla_hours'], $c['sla_due_at'], $c['first_response_at'] ?? '', $c['resolved_at'] ?? '',
            $resolutionHrs, getSlaState($c)['label'], $c['reopen_count'], $c['satisfaction_rating'] ?? '',
        ]);
    }
}

fclose($out);
exit;
