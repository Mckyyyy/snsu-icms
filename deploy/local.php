<?php
// config/local.php - InfinityFree database connection (server-specific; not in Git)
return [
    'db_host' => 'sql205.infinityfree.com',
    'db_port' => '3306',
    'db_user' => 'if0_43394566',
    'db_pass' => 'iD4zQjcpgaIv',
    'db_name' => 'if0_43394566_icms',

    // Free shared hosting: answer chat syncs at once and poll every 3 seconds
    'chat_long_poll_seconds' => 0,
    'chat_poll_delay_ms'     => 3000,
];
