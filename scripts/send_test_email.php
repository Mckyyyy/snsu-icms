<?php
// scripts/send_test_email.php - Verify mail settings (CLI only)
//
//   /Applications/XAMPP/xamppfiles/bin/php scripts/send_test_email.php you@example.com
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$to = $argv[1] ?? '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/send_test_email.php you@example.com\n");
    exit(1);
}
$cfg = mailConfig();
echo "Driver: {$cfg['driver']}" . ($cfg['driver'] === 'smtp' ? " ({$cfg['host']}:{$cfg['port']}, {$cfg['encryption']})" : '') . PHP_EOL;

try {
    [$text, $html] = buildEmailBodies('ICMS test email', ['If you can read this, email notifications are configured correctly.'], appUrl('index.php'), 'Open the system');
    deliverEmail(['to_email' => $to, 'to_name' => null, 'subject' => 'ICMS test email', 'body_text' => $text, 'body_html' => $html]);
    echo $cfg['driver'] === 'log' ? "Written to storage/mail.log (log mode — no real email sent)." . PHP_EOL : "Sent to {$to}." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "FAILED: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
