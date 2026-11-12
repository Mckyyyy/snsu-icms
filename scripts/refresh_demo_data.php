<?php
// scripts/refresh_demo_data.php - Re-date the demo/seed data relative to now (CLI only)
//   /Applications/XAMPP/xamppfiles/bin/php scripts/refresh_demo_data.php
// Run this the day before a demo or the defense so SLA states look realistic.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../database/demo_data.php';

$result = refreshDemoData($pdo);
@unlink(__DIR__ . '/../storage/sla_monitor.last'); // let SLA alerts fire on the next page view
echo json_encode($result) . PHP_EOL;
