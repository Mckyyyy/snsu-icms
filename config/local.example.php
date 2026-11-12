<?php
// config/local.example.php
// For web hosting (e.g. InfinityFree): copy this file to config/local.php and fill it in.
// config/local.php is not in Git. On XAMPP you do not need it.
//
// InfinityFree: Control Panel → MySQL Databases shows the host name, user name, password
// and database name (they look like the examples below).

return [
    'db_host' => 'sql123.infinityfree.com',
    'db_port' => '3306',
    'db_user' => 'if0_12345678',
    'db_pass' => 'your-vPanel-password',
    'db_name' => 'if0_12345678_icms',

    // Chat on free hosting: answer at once (no long waits) and poll every 3 seconds,
    // so the shared server's process limits are not exhausted.
    'chat_long_poll_seconds' => 0,
    'chat_poll_delay_ms'     => 3000,
];
