<?php
// config/db.php - PDO connection, hardened session bootstrap and auto-migration
// ICMS-DPT-RRT: Institutional Concern Management System

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Per-server settings (web hosting): config/local.php, copied from config/local.example.php.
// It is not in Git. Without it the XAMPP defaults below are used.
$localConfig = is_file(__DIR__ . '/local.php') ? (array)require __DIR__ . '/local.php' : [];
$setting = fn(string $key, string $env, string $default) =>
    array_key_exists($key, $localConfig) ? (string)$localConfig[$key] : (getenv($env) !== false ? (string)getenv($env) : $default);

define('DB_HOST', $setting('db_host', 'DB_HOST', '127.0.0.1'));
define('DB_PORT', $setting('db_port', 'DB_PORT', '3306'));
define('DB_USER', $setting('db_user', 'DB_USER', 'root'));
define('DB_PASS', $setting('db_pass', 'DB_PASS', ''));
define('DB_NAME', $setting('db_name', 'DB_NAME', 'icms_dpt_rrt'));
// Chat: how long the server holds a sync request open (seconds), and how long the browser waits before
// the next one (ms). XAMPP: 20 / 0 (instant). Free shared hosting: 0 / 3000 (light on the server).
define('CHAT_LONG_POLL_SECONDS', (int)($localConfig['chat_long_poll_seconds'] ?? 20));
define('CHAT_POLL_DELAY_MS', (int)($localConfig['chat_poll_delay_ms'] ?? 0));
unset($localConfig, $setting);

// Application timezone (SLA deadlines are computed in PHP and MySQL — keep them aligned)
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../database/migrate.php';

/**
 * Obtain singleton PDO connection. Creates the database if missing and
 * brings the schema up to date via database/migrate.php.
 */
function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $base = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";

    try {
        try {
            $pdo = new PDO($base . ";dbname=" . DB_NAME, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // 1049 = unknown database → create it, then reconnect
            if ((int)($e->errorInfo[1] ?? 0) !== 1049) {
                throw $e;
            }
            $raw = new PDO($base, DB_USER, DB_PASS, $options);
            $raw->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO($base . ";dbname=" . DB_NAME, DB_USER, DB_PASS, $options);
        }

        $pdo->exec("SET time_zone = '+08:00'");

        if (!defined('ICMS_SKIP_AUTO_MIGRATE')) {
            runMigrations($pdo);
        }
        return $pdo;
    } catch (PDOException $ex) {
        error_log('[ICMS] DB error: ' . $ex->getMessage());
        http_response_code(500);
        die("<div style='font-family:sans-serif;padding:30px;background:#fef2f2;border:1px solid #f87171;color:#991b1b;border-radius:8px;max-width:700px;margin:50px auto;'>"
            . "<h2>Database Connection Error</h2>"
            . "<p>Could not connect to or initialize the MySQL database at <strong>" . htmlspecialchars(DB_HOST . ":" . DB_PORT) . "</strong>.</p>"
            . "<p>Please ensure MySQL is running in the XAMPP control panel.</p>"
            . "</div>");
    }
}

// Global handle for scripts that include this config
$pdo = getDBConnection();
