<?php
// scripts/sla_monitor.php - Run SLA escalation now (CLI / cron)
//   /Applications/XAMPP/xamppfiles/bin/php scripts/sla_monitor.php
// The web app also runs it automatically every 2 minutes while people use the system.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$sent = runSlaMonitor($pdo);
echo "SLA monitor: {$sent['at_risk']} at-risk alert(s), {$sent['breached']} breach alert(s) sent." . PHP_EOL;
