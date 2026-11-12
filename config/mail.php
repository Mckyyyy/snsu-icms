<?php
// config/mail.php - Email settings
//
// DEFAULT (no setup needed): driver "log" — emails are written to storage/mail.log
// so every notification can be inspected without an internet connection.
//
// TO SEND REAL EMAIL: copy config/mail.local.example.php to config/mail.local.php
// and fill in SMTP details (e.g. a Gmail account with an App Password).
// mail.local.php overrides the values below and should never be shared/committed.

$mailConfig = [
    'driver'          => getenv('MAIL_DRIVER') ?: 'log',   // 'log' | 'smtp' | 'off'
    'host'            => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
    'port'            => (int)(getenv('MAIL_PORT') ?: 587),
    'encryption'      => getenv('MAIL_ENCRYPTION') ?: 'tls', // 'tls' (STARTTLS, 587) | 'ssl' (465) | 'none'
    'username'        => getenv('MAIL_USERNAME') ?: '',
    'password'        => getenv('MAIL_PASSWORD') ?: '',
    'from_email'      => getenv('MAIL_FROM') ?: 'no-reply@snsu.edu.ph',
    'from_name'       => 'SNSU Concern Management (ICMS)',
    // Absolute base URL used for links inside emails
    'app_url'         => getenv('APP_URL') ?: 'http://localhost/TrackingSystem',
    // Send queued emails at the end of the request that queued them (set false if using the cron script)
    'send_on_request' => true,
    'timeout'         => 10,
    // Keep true. Set false in mail.local.php only if Email Check reports a certificate error on the web host.
    'ssl_verify'      => true,
    // Demo safety (see includes/mailer.php mailRedirectTarget):
    'redirect_all_to'  => '',   // non-empty = EVERY email goes to this inbox
    'demo_redirect_to' => '',   // inbox that receives mail meant for the made-up addresses below
    'demo_recipients'  => ['@school.edu', '@student.com', '@example.com', '@example.org', '@test.com'],
];

$local = __DIR__ . '/mail.local.php';
if (is_file($local)) {
    $mailConfig = array_merge($mailConfig, (array)require $local);
}

return $mailConfig;
