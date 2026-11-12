<?php
// includes/anonymity.php - Anonymous concerns
//
// A student can submit a concern anonymously. It stays linked to their account so they can
// track it, receive notifications and rate it, but every administrator-facing screen, export,
// report, email and notification hides who sent it. Admin queries select the student columns
// through anonStudentColumns() so the identity never reaches the page.

const ANONYMOUS_LABEL = 'Anonymous student';

/**
 * SELECT fragment for the student columns of a concern, masked when the concern is anonymous.
 * Produces: student_name, student_no, student_email, student_dept, program
 */
function anonStudentColumns(string $c = 'c', string $s = 's'): string {
    $anon = "{$c}.is_anonymous = 1";
    return "IF({$anon}, '" . ANONYMOUS_LABEL . "', {$s}.name) AS student_name,
            IF({$anon}, NULL, {$s}.student_no) AS student_no,
            IF({$anon}, NULL, {$s}.email) AS student_email,
            IF({$anon}, NULL, {$s}.department) AS student_dept,
            IF({$anon}, NULL, {$s}.program) AS program";
}

/** Name to use in messages to administrators about a concern. */
function concernSenderName(array $concern, string $realName): string {
    return !empty($concern['is_anonymous']) ? 'An anonymous student' : $realName;
}

/** Avatar for a concern's sender: initials, or a hidden-identity icon for anonymous concerns. */
function senderAvatar(array $row, string $extraClass = '', string $style = ''): string {
    $styleAttr = $style !== '' ? ' style="' . e($style) . '"' : '';
    if (!empty($row['is_anonymous'])) {
        return '<div class="avatar avatar-anon ' . e($extraClass) . '"' . $styleAttr . ' title="Identity hidden">' . icon('eye-off', '', 14) . '</div>';
    }
    return '<div class="avatar ' . e($extraClass) . '"' . $styleAttr . '>' . e(userInitials($row['student_name'] ?? '?')) . '</div>';
}
