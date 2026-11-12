<?php
// config/mail.local.example.php
// Copy to config/mail.local.php and fill in to send real emails.
//
// Gmail: enable 2-Step Verification on the account, then create an
// "App password" (Google Account → Security → App passwords) and paste it below.
// Do NOT use the normal Gmail password.

return [
    'driver'     => 'smtp',
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => 'your.account@gmail.com',
    'password'   => 'xxxx xxxx xxxx xxxx',   // 16-character App Password
    'from_email' => 'your.account@gmail.com',
    'from_name'  => 'SNSU Concern Management (ICMS)',
    'app_url'    => 'http://localhost/TrackingSystem',

    // Real addresses (e.g. a student's Gmail) are delivered to the student.
    // Mail for made-up demo addresses (@school.edu, @student.com, @example.com, ...) goes here instead:
    'demo_redirect_to' => 'your.account@gmail.com',
    // Optional: send EVERY email to one inbox (the subject shows the intended recipient). Keep '' normally.
    'redirect_all_to' => '',
];
