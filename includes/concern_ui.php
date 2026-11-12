<?php
// includes/concern_ui.php - Shared rendering for concern detail pages (student & admin)

/**
 * Pending → In Progress → Resolved stepper.
 */
function renderStatusTracker(string $statusName): string {
    if ($statusName === 'Withdrawn') {
        return '<div class="alert alert-info" style="margin: 0; background: var(--bg-muted); border-color: var(--border-strong); color: var(--text-secondary);">'
            . '<span class="alert-icon">' . icon('x', '', 18) . '</span>'
            . '<div class="alert-text"><strong>Withdrawn.</strong> The student withdrew this concern before it was worked on. It is kept on record but is no longer tracked.</div></div>';
    }
    $steps = ['Pending', 'In Progress', 'Resolved'];
    $currentIndex = array_search($statusName, $steps, true);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;

    $html = '<div class="status-tracker" role="list" aria-label="Status progress">';
    foreach ($steps as $i => $label) {
        $classes = [];
        if ($i < $currentIndex || ($i === $currentIndex && $label === 'Resolved')) {
            $classes[] = 'completed';
        }
        if ($i === $currentIndex) {
            $classes[] = 'current';
        }
        $iconHtml = in_array('completed', $classes, true) ? icon('check', '', 18) : (string)($i + 1);
        $html .= '<div class="status-step ' . implode(' ', $classes) . '" role="listitem"' . ($i === $currentIndex ? ' aria-current="step"' : '') . '>'
               . '<div class="status-step-icon">' . $iconHtml . '</div>'
               . '<span class="status-step-label">' . e($label) . '</span>'
               . '</div>';
    }
    return $html . '</div>';
}

/**
 * Attachment block: inline image preview for photos and a download chip, both served
 * from the database through api/attachment.php. $meta comes from getConcernAttachmentMeta().
 */
function renderAttachment(?array $meta, string $rootPrefix = '../'): string {
    if (!$meta) {
        return '';
    }
    $isImage  = attachmentIsImage($meta['mime_type']);
    $viewUrl  = e(concernAttachmentUrl($meta, false, $rootPrefix));
    $saveUrl  = e(concernAttachmentUrl($meta, true, $rootPrefix));
    $name     = e($meta['original_name']);
    $ext      = strtoupper(pathinfo($meta['original_name'], PATHINFO_EXTENSION) ?: explode('/', $meta['mime_type'])[1]);

    $html = '<div class="concern-description-block"><div class="concern-description-title">Attachment</div>';
    if ($isImage) {
        $html .= '<a href="' . $viewUrl . '" target="_blank" rel="noopener" class="attachment-image" style="display: block;" title="Open full image">'
               . '<img src="' . $viewUrl . '" alt="Concern Evidence Photo" loading="lazy"></a>';
    }
    return $html . '<a href="' . $saveUrl . '" class="attachment-preview-card" title="Download ' . $name . '">'
        . icon($isImage ? 'camera' : 'paperclip', '', 18) . '<span>' . $name . '</span>'
        . '<span class="text-xs text-muted">' . e($ext) . ' · ' . e(formatFileSize((int)$meta['file_size'])) . '</span>'
        . icon('download', '', 16) . '</a></div>';
}

/**
 * Activity timeline of admin responses (and student reopen events).
 */
function renderResponseTimeline(array $responses, string $emptyText): string {
    if (!$responses) {
        return '<div class="empty-state" style="padding: 28px 16px; border: 1px dashed var(--border-strong); border-radius: var(--radius-md);">'
            . '<div class="empty-state-icon">' . icon('clock', '', 22) . '</div>'
            . '<p>' . e($emptyText) . '</p></div>';
    }
    $html = '<div class="timeline">';
    foreach ($responses as $r) {
        $isReopen = !empty($r['is_reopen']);
        $html .= '<div class="timeline-item' . ($isReopen ? ' is-reopen' : '') . '">'
            . '<div class="timeline-dot"></div>'
            . '<div class="timeline-box">'
            . '<div class="timeline-header">'
            . '<div class="flex items-center gap-2" style="flex-wrap: wrap;">'
            . '<span class="timeline-author">' . icon($r['status_name'] === 'Withdrawn' ? 'x' : ($isReopen ? 'refresh' : 'user'), '', 15) . ' ' . e($r['admin_name']) . '</span>'
            . (!empty($r['department_endorsed']) ? '<span class="timeline-department">Endorsed to ' . e($r['department_endorsed']) . '</span>' : '')
            . '</div>'
            . '<div class="flex items-center gap-2"><span class="timeline-time">' . formatDateTime($r['date_responded']) . '</span>'
            . renderStatusBadge($r['status_name']) . '</div>'
            . '</div>'
            . '<div class="timeline-content">' . nl2br(e($r['response_text'])) . '</div>'
            . '</div></div>';
    }
    return $html . '</div>';
}
