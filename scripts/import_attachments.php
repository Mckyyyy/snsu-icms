<?php
// scripts/import_attachments.php - Copy attachment files still referenced from the old uploads/ folder
// into the database (concern_attachments). Safe to run any number of times. CLI only.
//
//   Windows (WAMP): C:\wamp64\bin\php\php8.3.14\php.exe scripts\import_attachments.php
//
// The migration runs this automatically once; use it again if an old file is restored to uploads/ later.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$r = importLegacyAttachmentFiles(getDBConnection());
echo "Imported {$r['imported']} file(s) into the database; {$r['missing']} referenced file(s) not found in uploads/; {$r['skipped']} skipped (see the PHP error log)." . PHP_EOL;
$left = getDBConnection()->query("SELECT concern_id, attachment_path FROM concerns WHERE attachment_path IS NOT NULL")->fetchAll();
foreach ($left as $row) {
    echo "  still waiting for: concern {$row['concern_id']} → {$row['attachment_path']}" . PHP_EOL;
}
