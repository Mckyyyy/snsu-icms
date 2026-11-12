<?php
// includes/helpers.php - Utility functions for ICMS-DPT-RRT

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/ai_engine.php';
require_once __DIR__ . '/ai_llm.php';
require_once __DIR__ . '/sla.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/concern_ui.php';
require_once __DIR__ . '/sla_monitor.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/duplicates.php';
require_once __DIR__ . '/registration.php';
require_once __DIR__ . '/recovery.php';
require_once __DIR__ . '/avatars.php';

/**
 * Cache-busting suffix for a file under assets/ ("css/base.css" -> "?v=1731234567").
 * After a new version is uploaded, browsers fetch the new file instead of a stale cached copy.
 */
function assetVersion(string $path): string {
    $file = __DIR__ . '/../assets/' . ltrim($path, '/');
    return is_file($file) ? '?v=' . filemtime($file) : '';
}
require_once __DIR__ . '/anonymity.php';
require_once __DIR__ . '/messages.php';

/**
 * Two-letter initials for avatars ("Dr. Elena Ramos" -> "ER").
 */
function userInitials(string $name): string {
    $clean = preg_replace('/^(Dr|Engr|Prof|Mr|Ms|Mrs|Atty)\.?\s+/i', '', trim($name));
    $parts = preg_split('/\s+/', $clean) ?: [''];
    $first = mb_substr($parts[0], 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last) ?: '?';
}

/**
 * Sanitize string for HTML output
 */
/** "1 rating", "3 ratings" */
function plural(int $n, string $word, ?string $pluralWord = null): string {
    return $n . ' ' . ($n === 1 ? $word : ($pluralWord ?? $word . 's'));
}

function e(?string $str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format ticket ID from the row's own auto-increment ID (e.g. SCF-2026-034).
 * Called after INSERT so two simultaneous submissions can never collide
 * (the old MAX(concern_id)+1 approach could).
 */
function formatTicketId(int $concernId, ?string $year = null): string {
    return sprintf("SCF-%s-%03d", $year ?? date('Y'), $concernId);
}

/**
 * Render Status badge HTML matching Figma prototype
 */
function renderStatusBadge(string $statusName): string {
    $name = trim($statusName);
    $lower = strtolower($name);
    
    if (strpos($lower, 'pending') !== false) {
        return '<span class="status-pill status-pending"><span class="status-dot"></span>Pending</span>';
    } elseif (strpos($lower, 'progress') !== false) {
        return '<span class="status-pill status-progress"><span class="status-dot"></span>In Progress</span>';
    } elseif (strpos($lower, 'withdrawn') !== false) {
        return '<span class="status-pill status-withdrawn"><span class="status-dot"></span>Withdrawn</span>';
    } elseif (strpos($lower, 'resolved') !== false) {
        return '<span class="status-pill status-resolved"><span class="status-dot"></span>Resolved</span>';
    }
    return '<span class="status-pill">' . e($name) . '</span>';
}

/**
 * Render Priority badge HTML matching Figma & DPT-RRT specifications
 */
function renderPriorityBadge(string $priority, ?int $score = null): string {
    $p = ucfirst(strtolower(trim($priority)));
    $scoreText = ($score !== null) ? " (" . (int)$score . ")" : "";
    
    switch ($p) {
        case 'Critical':
            return '<span class="priority-pill priority-critical"><span class="pulse-ring"></span>Critical' . $scoreText . '</span>';
        case 'High':
            return '<span class="priority-pill priority-high">High' . $scoreText . '</span>';
        case 'Medium':
            return '<span class="priority-pill priority-medium">Medium' . $scoreText . '</span>';
        case 'Low':
        default:
            return '<span class="priority-pill priority-low">Low' . $scoreText . '</span>';
    }
}

/**
 * Format datetime e.g. "May 12, 2024 (09:30 AM)"
 */
function formatDateTime(?string $datetime): string {
    if (!$datetime) return 'N/A';
    $time = strtotime($datetime);
    if (!$time) return e($datetime);
    return date('M j, Y · g:i A', $time);
}

/**
 * Format date e.g. "May 12, 2024"
 */
function formatDate(?string $datetime): string {
    if (!$datetime) return 'N/A';
    $time = strtotime($datetime);
    if (!$time) return e($datetime);
    return date('M j, Y', $time);
}

/**
 * Set flash alert message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render clean SVG vector icon
 */
function getIcon(string $name, string $extraClass = '', ?int $size = null): string {
    $dim = $size ? "width='{$size}' height='{$size}'" : "";
    $classAttr = "class='svg-icon " . e($extraClass) . "'";

    $icons = [
        'dashboard' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><rect x='3' y='3' width='7' height='7'/><rect x='14' y='3' width='7' height='7'/><rect x='14' y='14' width='7' height='7'/><rect x='3' y='14' width='7' height='7'/></svg>",
        'edit' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M12 20h9'/><path d='M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z'/></svg>",
        'clipboard' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2'/><rect x='8' y='2' width='8' height='4' rx='1' ry='1'/></svg>",
        'file-text' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/><polyline points='14 2 14 8 20 8'/><line x1='16' y1='13' x2='8' y2='13'/><line x1='16' y1='17' x2='8' y2='17'/><polyline points='10 9 9 9 8 9'/></svg>",
        'message-square' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'/></svg>",
        'megaphone' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M3 11l18-5v12L3 13v-2z'/><path d='M11.6 16.8a3 3 0 1 1-5.8-1.6'/></svg>",
        'bell' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9'/><path d='M13.73 21a2 2 0 0 1-3.46 0'/></svg>",
        'user' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/><circle cx='12' cy='7' r='4'/></svg>",
        'users' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/><circle cx='9' cy='7' r='4'/><path d='M23 21v-2a4 4 0 0 0-3-3.87'/><path d='M16 3.13a4 4 0 0 1 0 7.75'/></svg>",
        'log-out' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4'/><polyline points='16 17 21 12 16 7'/><line x1='21' y1='12' x2='9' y2='12'/></svg>",
        'clock' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><circle cx='12' cy='12' r='10'/><polyline points='12 6 12 12 16 14'/></svg>",
        'archive' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polyline points='21 8 21 21 3 21 3 8'/><rect x='1' y='3' width='22' height='5'/><line x1='10' y1='12' x2='14' y2='12'/></svg>",
        'refresh' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polyline points='23 4 23 10 17 10'/><polyline points='1 20 1 14 7 14'/><path d='M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15'/></svg>",
        'check' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polyline points='20 6 9 17 4 12'/></svg>",
        'check-circle' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M22 11.08V12a10 10 0 1 1-5.93-9.14'/><polyline points='22 4 12 14.01 9 11.01'/></svg>",
        'eye' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z'/><circle cx='12' cy='12' r='3'/></svg>",
        'trash' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polyline points='3 6 5 6 21 6'/><path d='M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2'/></svg>",
        'lock' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'/><path d='M7 11V7a5 5 0 0 1 10 0v4'/></svg>",
        'copy' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><rect x='9' y='9' width='13' height='13' rx='2' ry='2'/><path d='M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1'/></svg>",
        'upload' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'/><polyline points='17 8 12 3 7 8'/><line x1='12' y1='3' x2='12' y2='15'/></svg>",
        'award' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><circle cx='12' cy='8' r='7'/><polyline points='8.21 13.89 7 23 12 20 17 23 15.79 13.88'/></svg>",
        'chevron-right' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polyline points='9 18 15 12 9 6'/></svg>",
        'search' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><circle cx='11' cy='11' r='8'/><line x1='21' y1='21' x2='16.65' y2='16.65'/></svg>",
        'paperclip' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48'/></svg>",
        'alert-triangle' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'/><line x1='12' y1='9' x2='12' y2='13'/><line x1='12' y1='17' x2='12.01' y2='17'/></svg>",
        'alert-circle' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><circle cx='12' cy='12' r='10'/><line x1='12' y1='8' x2='12' y2='12'/><line x1='12' y1='16' x2='12.01' y2='16'/></svg>",
        'zap' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polygon points='13 2 3 14 12 14 11 22 21 10 12 10 13 2'/></svg>",
        'star' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>",
        'star-filled' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24' fill='currentColor'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg>",
        'printer' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polyline points='6 9 6 2 18 2 18 9'/><path d='M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'/><rect x='6' y='14' width='12' height='8'/></svg>",
        'arrow-left' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><line x1='19' y1='12' x2='5' y2='12'/><polyline points='12 19 5 12 12 5'/></svg>",
        'arrow-right' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><line x1='5' y1='12' x2='19' y2='12'/><polyline points='12 5 19 12 12 19'/></svg>",
        'camera' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z'/><circle cx='12' cy='13' r='4'/></svg>",
        'shield' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'/></svg>",
        'bar-chart' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><line x1='12' y1='20' x2='12' y2='10'/><line x1='18' y1='20' x2='18' y2='4'/><line x1='6' y1='20' x2='6' y2='16'/></svg>",
        'trending-up' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polyline points='23 6 13.5 15.5 8.5 10.5 1 18'/><polyline points='17 6 23 6 23 12'/></svg>",
        'plus' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><line x1='12' y1='5' x2='12' y2='19'/><line x1='5' y1='12' x2='19' y2='12'/></svg>",
        'info' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><circle cx='12' cy='12' r='10'/><line x1='12' y1='16' x2='12' y2='12'/><line x1='12' y1='8' x2='12.01' y2='8'/></svg>",
        'x' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><line x1='18' y1='6' x2='6' y2='18'/><line x1='6' y1='6' x2='18' y2='18'/></svg>",
        'wrench' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z'/></svg>",
        'target' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><circle cx='12' cy='12' r='10'/><circle cx='12' cy='12' r='6'/><circle cx='12' cy='12' r='2'/></svg>",
        'thumbs-up' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3'/></svg>",
        'calendar' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><rect x='3' y='4' width='18' height='18' rx='2' ry='2'/><line x1='16' y1='2' x2='16' y2='6'/><line x1='8' y1='2' x2='8' y2='6'/><line x1='3' y1='10' x2='21' y2='10'/></svg>",
        'folder' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z'/></svg>",
        'menu' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><line x1='3' y1='12' x2='21' y2='12'/><line x1='3' y1='6' x2='21' y2='6'/><line x1='3' y1='18' x2='21' y2='18'/></svg>",
        'settings' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><circle cx='12' cy='12' r='3'/><path d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'/></svg>",
        'mail' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z'/><polyline points='22,6 12,13 2,6'/></svg>",
        'phone' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><rect x='5' y='2' width='14' height='20' rx='2' ry='2'/><line x1='12' y1='18' x2='12.01' y2='18'/></svg>",
        'book' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M4 19.5A2.5 2.5 0 0 1 6.5 17H20'/><path d='M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z'/></svg>",
        'filter' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><polygon points='22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3'/></svg>",
        'download' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'/><polyline points='7 10 12 15 17 10'/><line x1='12' y1='15' x2='12' y2='3'/></svg>",
        'eye-off' => "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><path d='M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24'/><line x1='1' y1='1' x2='23' y2='23'/></svg>"
    ];

    return $icons[$name] ?? "<svg {$classAttr} {$dim} viewBox='0 0 24 24'><circle cx='12' cy='12' r='10'/></svg>";
}

/**
 * Render clean SVG star rating
 */
function renderStarRating(int $rating, int $max = 5): string {
    $html = '<span class="star-rating-display" style="display: inline-flex; align-items: center; gap: 3px;">';
    for ($i = 1; $i <= $max; $i++) {
        if ($i <= $rating) {
            $html .= '<svg class="svg-icon" style="width: 16px; height: 16px; fill: #f59e0b; stroke: #d97706;" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        } else {
            $html .= '<svg class="svg-icon" style="width: 16px; height: 16px; fill: none; stroke: #cbd5e1;" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
        }
    }
    $html .= '</span>';
    return $html;
}

/**
 * Shorthand for getIcon
 */
function icon(string $name, string $extraClass = '', ?int $size = null): string {
    return getIcon($name, $extraClass, $size);
}

/**
 * Render Flash Alert as Top-Right Toast Notification
 */
function renderFlash(): void {
    $flash = getFlash();
    if (!$flash) return;
    
    $title = match($flash['type']) {
        'success' => 'Success',
        'error'   => 'Error Notice',
        'warning' => 'Attention Needed',
        default   => 'Information'
    };
    
    $jsonMsg = json_encode($flash['message']);
    $jsonType = json_encode($flash['type']);
    $jsonTitle = json_encode($title);

    echo "<script>
      (function() {
        function triggerToast() {
          if (typeof showToast === 'function') {
            showToast({$jsonMsg}, {$jsonType}, {$jsonTitle});
          }
        }
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', triggerToast);
        } else {
          triggerToast();
        }
      })();
    </script>";
}

/**
 * Return JSON response
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
