<?php
// config/mail.local.php (web host copy) - Real email delivery through Gmail SMTP.
// Never commit this file (it is listed in .gitignore).
return [
    'driver'     => 'smtp',
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => 'errictraya65@gmail.com',
    'password'   => 'wxvz dggb svia jswb',   // Gmail App Password
    'from_email' => 'errictraya65@gmail.com',
    'from_name'  => 'SNSU Concern Management (ICMS)',
    'app_url'    => '',   // empty = use the address the site is visited on (e.g. https://yoursite.infinityfreeapp.com)

    // Real addresses (e.g. a student's Gmail) are delivered to the student.
    // Mail for the made-up demo addresses (@school.edu, @student.com, @example.com, and the demo
    // SNSU staff accounts below) comes to this inbox instead, with the intended recipient in the subject.
    'demo_redirect_to' => 'errictraya65@gmail.com',
    'demo_recipients'  => ['@school.edu', '@student.com', '@example.com', '@example.org', '@test.com',
                           'facilities@snsu.edu.ph', 'ccis.dean@snsu.edu.ph'],
    // Set to an inbox to send EVERY email there (whole-system demo mode). '' = normal delivery.
    'redirect_all_to' => '',
    'timeout'    => 5,     // shorter wait on shared hosting
];
