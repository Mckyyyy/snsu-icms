<?php
// database/migrate.php - Versioned schema migration runner for ICMS-DPT-RRT
//
// Order of operations (idempotent, safe to run on every request):
//   1. Baseline:   apply database/schema.sql if the core tables do not exist yet.
//   2. Seed:       apply database/seed.sql if the lookup table `statuses` is empty.
//   3. Migrations: apply every database/migrations/NNN_*.sql not yet recorded
//                  in `schema_migrations`, in filename order.
//
// CLI usage:  /Applications/XAMPP/xamppfiles/bin/php database/migrate.php

/**
 * Split a SQL file into individual statements.
 * Strips `--` comments and the CREATE DATABASE / USE statements so the
 * runner always targets the database chosen in config/db.php.
 */
function splitSqlStatements(string $sql): array {
    $lines = [];
    foreach (preg_split('/\R/', $sql) as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $lines[] = $line;
    }
    $statements = [];
    foreach (preg_split('/;\s*(?:\R|$)/', implode("\n", $lines)) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || preg_match('/^(CREATE\s+DATABASE|USE)\b/i', $stmt)) {
            continue;
        }
        $statements[] = $stmt;
    }
    return $statements;
}

/**
 * Execute a SQL file statement-by-statement so any failure surfaces
 * (PDO::exec on a multi-statement string silently drops later errors).
 */
function applySqlFile(PDO $pdo, string $file): void {
    foreach (splitSqlStatements(file_get_contents($file)) as $stmt) {
        $pdo->exec($stmt);
    }
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $stmt->execute(['t' => $table]);
    return (bool)$stmt->fetch();
}

/**
 * Bring the database up to date. Returns a log of actions taken.
 */
function runMigrations(PDO $pdo): array {
    $log = [];
    $dir = __DIR__;
    $migrationFiles = glob($dir . '/migrations/*.sql') ?: [];
    sort($migrationFiles);

    // Fast path: everything already applied and seeded.
    if (tableExists($pdo, 'schema_migrations')) {
        $applied = (int)$pdo->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
        // +1 accounts for the baseline row.
        if ($applied >= count($migrationFiles) + 1) {
            return $log;
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
          `migration` VARCHAR(150) NOT NULL PRIMARY KEY,
          `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $applied = $pdo->query("SELECT migration FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);

    // 1. Baseline
    if (!in_array('000_baseline', $applied, true)) {
        if (!tableExists($pdo, 'concerns')) {
            applySqlFile($pdo, $dir . '/schema.sql');
            $log[] = 'Applied baseline schema.sql';
        } else {
            $log[] = 'Baseline tables already present; recorded as applied';
        }
        $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES ('000_baseline')")->execute();
    }

    // 2. Seed (only when lookup data is missing — e.g. fresh install or a failed earlier seed)
    $hasStatuses = (int)$pdo->query("SELECT COUNT(*) FROM statuses")->fetchColumn() > 0;
    if (!$hasStatuses && file_exists($dir . '/seed.sql')) {
        applySqlFile($pdo, $dir . '/seed.sql');
        $log[] = 'Applied seed.sql';
        $seededNow = true;
    }

    // 3. Incremental migrations
    foreach ($migrationFiles as $file) {
        $name = basename($file, '.sql');
        if (in_array($name, $applied, true)) {
            continue;
        }
        // MySQL DDL auto-commits, so a transaction cannot wrap ALTER TABLE.
        // Each migration must therefore be written to be re-runnable or small.
        applySqlFile($pdo, $file);
        $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (:m)")->execute(['m' => $name]);
        $log[] = "Applied migration {$name}";
        if ($name === '013_concern_attachments_blob') {
            $importAttachments = true;
        }
    }

    // Attachments moved from uploads/ into MySQL: copy the existing files into the new table
    if (!empty($importAttachments)) {
        require_once __DIR__ . '/../includes/helpers.php';
        $r = importLegacyAttachmentFiles($pdo);
        $log[] = "Imported {$r['imported']} attachment file(s) into the database ({$r['missing']} missing, {$r['skipped']} skipped)";
    }

    // Fresh install: re-date the seed rows so the demo looks current (needs the later migrations' columns)
    if (!empty($seededNow) && file_exists($dir . '/demo_data.php')) {
        require_once __DIR__ . '/../includes/helpers.php';
        require_once $dir . '/demo_data.php';
        refreshDemoData($pdo);
        $log[] = 'Refreshed demo data dates';
    }

    return $log;
}

// CLI entry point
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    define('ICMS_SKIP_AUTO_MIGRATE', true);
    require_once __DIR__ . '/../config/db.php';
    $log = runMigrations(getDBConnection());
    echo $log ? implode(PHP_EOL, $log) . PHP_EOL : "Database is up to date." . PHP_EOL;
    $rows = getDBConnection()->query("SELECT migration, applied_at FROM schema_migrations ORDER BY migration")->fetchAll();
    foreach ($rows as $r) {
        echo "  [x] {$r['migration']}  ({$r['applied_at']})" . PHP_EOL;
    }
}
