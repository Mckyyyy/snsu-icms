<?php
// scripts/reset_demo_data.php - Clean the database and load a fresh demo set for the defense (CLI only)
//
//   /Applications/XAMPP/xamppfiles/bin/php scripts/reset_demo_data.php --yes
//   add --no-ai to use the rule-based engine only (faster, no Gemini calls)
//
// DELETES all concerns, chat, feedback, announcements, notifications and every student account except
// the six demo students. A full backup is saved to backups/ first. See database/demo_reset.php.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
if (!in_array('--yes', $argv, true)) {
    fwrite(STDERR, "This deletes all records and loads demo data. A backup is made first.\nRun again with --yes to continue.\n");
    exit(1);
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../database/demo_reset.php';

// 1. Backup
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/backup_database.php'), $code);
if ($code !== 0) {
    fwrite(STDERR, "Backup failed. Nothing was changed.\n");
    exit(1);
}

// 2. Reset
$result = resetDemoDatabase($pdo, !in_array('--no-ai', $argv, true), fn(string $m) => print($m . PHP_EOL));
echo PHP_EOL . 'Removed student accounts: ' . (implode(', ', $result['removed_students']) ?: 'none') . PHP_EOL;
echo "Concerns: {$result['concerns']}  (AI engine: " . json_encode($result['ai_engines']) . ')' . PHP_EOL;
echo "Done. Sign in as juan.delacruz@student.com / student123 or admin@school.edu / admin123." . PHP_EOL;
