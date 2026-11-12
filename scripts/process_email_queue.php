<?php
// scripts/process_email_queue.php - Deliver pending emails from the outbox (CLI only)
//
//   /Applications/XAMPP/xamppfiles/bin/php scripts/process_email_queue.php
//
// Useful with 'send_on_request' => false in config/mail.php, e.g. every minute via cron:
//   * * * * * /Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/xamppfiles/htdocs/TrackingSystem/scripts/process_email_queue.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// Only one worker at a time (the web app starts one after each request that queues email)
$lock = @fopen(__DIR__ . '/../storage/email_queue.lock', 'c');
if (!$lock) {
    fwrite(STDERR, "Cannot create storage/email_queue.lock (check folder permissions)." . PHP_EOL);
    exit(1);
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Another worker is already sending." . PHP_EOL;
    exit;
}
$sent = $failed = 0;
do { // keep going while new emails arrive during sending
    [$s, $f] = processEmailQueue($pdo, 50);
    $sent += $s;
    $failed += $f;
} while ($s + $f > 0);
$pending = (int)$pdo->query("SELECT COUNT(*) FROM email_outbox WHERE status = 'Pending'")->fetchColumn();
echo "Driver: " . mailConfig()['driver'] . " | sent {$sent}, failed {$failed}, still pending {$pending}" . PHP_EOL;
