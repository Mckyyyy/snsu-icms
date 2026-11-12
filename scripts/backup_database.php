<?php
// scripts/backup_database.php - Save a full backup of the icms_dpt_rrt database (CLI only)
//
//   macOS:   /Applications/XAMPP/xamppfiles/bin/php scripts/backup_database.php
//   Windows: C:\xampp\php\php.exe scripts\backup_database.php
//
// Writes backups/icms_dpt_rrt_YYYYMMDD_HHMMSS.sql (structure + data). Uses XAMPP's
// mysqldump when it can find it and falls back to a pure-PHP export otherwise.
// Restore with:  mysql --max-allowed-packet=64M -u root icms_dpt_rrt < backups/<file>.sql
// The backups/ folder is blocked from the web and excluded from Git (it contains password hashes).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
define('ICMS_SKIP_AUTO_MIGRATE', true);
require_once __DIR__ . '/../config/db.php';

$dir = __DIR__ . '/../backups';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
if (!is_file($dir . '/.htaccess')) {
    file_put_contents($dir . '/.htaccess', "# Database backups - never served over the web\nRequire all denied\n");
}
$file = $dir . '/' . DB_NAME . '_' . date('Ymd_His') . '.sql';

// ---- 1. Prefer mysqldump (exact, fast) ----
$candidates = [
    '/Applications/XAMPP/xamppfiles/bin/mysqldump',
    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    trim((string)@shell_exec(stripos(PHP_OS, 'WIN') === 0 ? 'where mysqldump 2>NUL' : 'command -v mysqldump 2>/dev/null')),
];
$dump = null;
foreach ($candidates as $c) {
    if ($c !== '' && is_file(strtok($c, "\r\n"))) {
        $dump = strtok($c, "\r\n");
        break;
    }
}

$method = '';
if ($dump && function_exists('proc_open')) {
    $cmd = [
        $dump, '--host=' . DB_HOST, '--port=' . DB_PORT, '--user=' . DB_USER,
        '--single-transaction', '--skip-routines', '--default-character-set=utf8mb4', '--hex-blob', '--max-allowed-packet=64M',
        '--add-drop-table', '--result-file=' . $file, DB_NAME,
    ];
    // Password goes through the environment, not the command line
    $env = array_merge(getenv(), ['MYSQL_PWD' => DB_PASS]);
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    if (is_resource($proc)) {
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($proc) === 0 && is_file($file) && filesize($file) > 0) {
            $method = 'mysqldump';
        } else {
            fwrite(STDERR, "mysqldump failed, using the PHP exporter instead. " . trim($err) . PHP_EOL);
            @unlink($file);
        }
    }
}

// ---- 2. Fallback: export with PDO ----
if ($method === '') {
    $pdo = getDBConnection();
    $out = fopen($file, 'w');
    fwrite($out, "-- ICMS-DPT-RRT database backup (PHP exporter)\n-- Database: " . DB_NAME . "\n-- Created: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
    foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$table]) {
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
        fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");
        $rows = $pdo->query("SELECT * FROM `{$table}`");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            // Binary values (attachment images in concern_attachments.file_data) are written as hex so they restore byte-for-byte
            $vals = array_map(fn($v) => $v === null ? 'NULL' : (mb_check_encoding((string)$v, 'UTF-8') ? $pdo->quote((string)$v) : '0x' . bin2hex((string)$v)), array_values($row));
            fwrite($out, "INSERT INTO `{$table}` (`" . implode('`, `', array_keys($row)) . "`) VALUES (" . implode(', ', $vals) . ");\n");
        }
        fwrite($out, "\n");
    }
    fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($out);
    $method = 'PHP exporter';
}

$tables = preg_match_all('/^CREATE TABLE/m', file_get_contents($file));
printf("Backup saved: %s\nMethod: %s | %d tables | %.1f KB\n", realpath($file), $method, $tables, filesize($file) / 1024);
// Attachments up to 20 MB become ~40 MB hex statements: the client needs a larger packet than its default
echo "Restore: mysql --max-allowed-packet=64M -u " . DB_USER . " " . DB_NAME . " < " . basename($file) . PHP_EOL;
