<?php
// actions/iso_results_action.php - Administrators record the ISO/IEC 25010 survey results
// The Reports page shows only what is entered here, so no score appears before the survey is done.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/reports.php';

requireAdmin();
$admin = getCurrentUser();
$back  = '../admin/reports.php#iso';
requirePostWithCsrf($back);

if (($_POST['action'] ?? '') === 'clear') {
    $pdo->exec("DELETE FROM iso_evaluation_results");
    logAdminActivity($pdo, (int)$admin['id'], 'iso_results_updated', 'report', null, 'Cleared ISO 25010 results');
    setFlash('success', 'The ISO 25010 results were cleared.');
    header("Location: {$back}");
    exit;
}

$surveyDate = trim((string)($_POST['survey_date'] ?? ''));
if ($surveyDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $surveyDate)) {
    $surveyDate = '';
}

$rows = [];
foreach (ISO_CHARACTERISTICS as $key => $info) {
    $mean = trim((string)($_POST['mean'][$key] ?? ''));
    $resp = trim((string)($_POST['respondents'][$key] ?? ''));
    if ($mean === '' && $resp === '') {
        continue; // not evaluated yet
    }
    if (!is_numeric($mean) || (float)$mean < 1 || (float)$mean > 5 || !ctype_digit($resp) || (int)$resp < 1) {
        setFlash('error', "{$info['label']}: enter a mean from 1.00 to 5.00 and the number of respondents (at least 1).");
        header("Location: {$back}");
        exit;
    }
    $rows[$key] = [round((float)$mean, 2), (int)$resp];
}
if (!$rows) {
    setFlash('error', 'Enter the results of at least one characteristic.');
    header("Location: {$back}");
    exit;
}

$up = $pdo->prepare("
    INSERT INTO iso_evaluation_results (characteristic, mean_score, respondents, survey_date, updated_by)
    VALUES (:c, :m, :r, :d, :a)
    ON DUPLICATE KEY UPDATE mean_score = VALUES(mean_score), respondents = VALUES(respondents),
        survey_date = VALUES(survey_date), updated_by = VALUES(updated_by)
");
foreach ($rows as $key => [$mean, $resp]) {
    $up->execute(['c' => $key, 'm' => $mean, 'r' => $resp, 'd' => $surveyDate ?: null, 'a' => $admin['id']]);
}
logAdminActivity($pdo, (int)$admin['id'], 'iso_results_updated', 'report', null,
    implode(', ', array_map(fn($k, $v) => ISO_CHARACTERISTICS[$k]['label'] . " {$v[0]}", array_keys($rows), $rows)));
setFlash('success', 'ISO 25010 results saved.');
header("Location: {$back}");
exit;
