<?php
/**
 * Database Migration Runner
 * ------------------------------------------------------------------
 * Applies pending SQL migrations and records them in `schema_migrations`
 * so each migration runs exactly once. Safe to run repeatedly (idempotent):
 * individual statements that would fail because an object already exists
 * (duplicate column/key/table/entry) are tolerated and skipped.
 *
 * Usage (from project root):
 *   php scripts/migrate.php            Apply all pending migrations
 *   php scripts/migrate.php status     Show applied / pending migrations
 *   php scripts/migrate.php --pretend  Show what WOULD run, without applying
 *
 * Migration files are collected from:
 *   - migrations/            (legacy plain SQL)
 *   - database/migrations/   (newer migrations)
 * Files are applied in ascending filename order.
 *
 * Can also be invoked programmatically:
 *   require 'scripts/migrate.php';  Migrator::runPending();  // when RUN_MIGRATIONS_INLINE defined
 * ------------------------------------------------------------------
 */

if (!defined('MIGRATE_BOOTSTRAP')) {
    require_once __DIR__ . '/../app/Helpers.php';
    require_once __DIR__ . '/../app/Database.php';
    require_once __DIR__ . '/../app/Schema.php';
}

class Migrator
{
    /** MySQL error codes that indicate an object already exists (idempotent-safe). */
    private const IGNORABLE_ERRORS = [
        1050, // Table already exists
        1060, // Duplicate column name
        1061, // Duplicate key name
        1062, // Duplicate entry for unique key
        1091, // Can't DROP; check that column/key exists
        1826, // Duplicate foreign key constraint name
        1022, // Can't write; duplicate key in table
    ];

    private static array $migrationDirs = [
        __DIR__ . '/../migrations',
        __DIR__ . '/../database/migrations',
    ];

    /**
     * Ensure the schema_migrations tracking table exists.
     */
    public static function ensureTrackingTable(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `migration` VARCHAR(255) NOT NULL,
                `checksum` VARCHAR(64) NULL DEFAULT NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `statements_run` INT NOT NULL DEFAULT 0,
                `statements_skipped` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Collect all migration files, keyed and sorted by basename.
     * @return array<string,string> [basename => fullpath]
     */
    public static function collectMigrations(): array
    {
        $files = [];
        foreach (self::$migrationDirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach (glob($dir . '/*.sql') ?: [] as $path) {
                $files[basename($path)] = $path;
            }
        }
        // Sort by basename ascending for deterministic order
        uksort($files, 'strcmp');
        return $files;
    }

    /**
     * Get the set of already-applied migration names.
     * @return array<string,bool>
     */
    public static function appliedMigrations(PDO $pdo): array
    {
        $applied = [];
        $rows = $pdo->query("SELECT `migration` FROM `schema_migrations`")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($rows as $m) {
            $applied[$m] = true;
        }
        return $applied;
    }

    /**
     * Split a SQL script into individual statements.
     * Quote- and comment-aware; ignores ';' inside strings/identifiers and comments.
     * @return string[]
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $len = strlen($sql);
        $inSingle = false; $inDouble = false; $inBacktick = false;
        $inLineComment = false; $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            // Handle comment termination
            if ($inLineComment) {
                if ($ch === "\n") { $inLineComment = false; $buffer .= $ch; }
                continue;
            }
            if ($inBlockComment) {
                if ($ch === '*' && $next === '/') { $inBlockComment = false; $i++; }
                continue;
            }

            // Detect comment start (only outside quotes)
            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($ch === '-' && $next === '-') { $inLineComment = true; $i++; continue; }
                if ($ch === '#') { $inLineComment = true; continue; }
                if ($ch === '/' && $next === '*') { $inBlockComment = true; $i++; continue; }
            }

            // Toggle quote states
            if ($ch === "'" && !$inDouble && !$inBacktick) {
                // handle escaped quote
                if ($inSingle && $next === "'") { $buffer .= "''"; $i++; continue; }
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && !$inBacktick) {
                if ($inDouble && $next === '"') { $buffer .= '""'; $i++; continue; }
                $inDouble = !$inDouble;
            } elseif ($ch === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            // Statement terminator
            if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') $statements[] = $trimmed;
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') $statements[] = $trimmed;

        // Filter out pure SET/no-op lines? Keep SET statements (harmless).
        return array_filter($statements, fn($s) => $s !== '');
    }

    /**
     * Apply a single migration file. Returns [run, skipped].
     */
    public static function applyFile(PDO $pdo, string $path, bool $pretend = false): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException("Cannot read migration file: {$path}");
        }
        $statements = self::splitStatements($sql);
        $run = 0; $skipped = 0;

        foreach ($statements as $stmt) {
            if ($pretend) { $run++; continue; }
            try {
                $pdo->exec($stmt);
                $run++;
            } catch (\PDOException $e) {
                $code = (int)($e->errorInfo[1] ?? 0);
                if (in_array($code, self::IGNORABLE_ERRORS, true)) {
                    $skipped++;
                    continue; // object already exists — safe to ignore
                }
                // Re-throw with context
                throw new \RuntimeException(
                    "Error in " . basename($path) . " (MySQL {$code}): " . $e->getMessage() .
                    "\nStatement: " . substr($stmt, 0, 200),
                    0, $e
                );
            }
        }

        return [$run, $skipped];
    }

    /**
     * Run all pending migrations.
     * @return array report
     */
    public static function runPending(bool $pretend = false): array
    {
        $pdo = Database::getConnection();
        self::ensureTrackingTable($pdo);

        $all = self::collectMigrations();
        $applied = self::appliedMigrations($pdo);

        $report = ['applied' => [], 'skipped_already' => [], 'pretend' => $pretend];

        foreach ($all as $name => $path) {
            if (isset($applied[$name])) {
                $report['skipped_already'][] = $name;
                continue;
            }

            [$run, $skipped] = self::applyFile($pdo, $path, $pretend);

            if (!$pretend) {
                $stmt = $pdo->prepare(
                    "INSERT INTO `schema_migrations` (`migration`, `checksum`, `applied_at`, `statements_run`, `statements_skipped`)
                     VALUES (:m, :c, NOW(), :r, :s)
                     ON DUPLICATE KEY UPDATE `applied_at` = NOW(), `statements_run` = :r2, `statements_skipped` = :s2"
                );
                $checksum = hash_file('sha256', $path);
                $stmt->execute([
                    'm' => $name, 'c' => $checksum,
                    'r' => $run, 's' => $skipped, 'r2' => $run, 's2' => $skipped,
                ]);
            }

            $report['applied'][] = ['migration' => $name, 'statements_run' => $run, 'statements_skipped' => $skipped];
        }

        // Refresh schema cache after applying
        if (!$pretend && class_exists('Schema')) {
            Schema::flushCache();
        }

        return $report;
    }

    /**
     * Build a status report of applied vs pending migrations.
     */
    public static function status(): array
    {
        $pdo = Database::getConnection();
        self::ensureTrackingTable($pdo);
        $all = self::collectMigrations();
        $applied = self::appliedMigrations($pdo);

        $status = [];
        foreach ($all as $name => $path) {
            $status[] = ['migration' => $name, 'applied' => isset($applied[$name])];
        }
        return $status;
    }
}

// ------------------------------------------------------------------
// CLI entrypoint (only when run directly, not when required inline)
// ------------------------------------------------------------------
if (!defined('MIGRATE_BOOTSTRAP') && PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $cmd = $argv[1] ?? 'migrate';

    try {
        if ($cmd === 'status') {
            $rows = Migrator::status();
            echo "Migration status:\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($rows as $r) {
                printf("  [%s] %s\n", $r['applied'] ? 'X' : ' ', $r['migration']);
            }
            $pending = count(array_filter($rows, fn($r) => !$r['applied']));
            echo str_repeat('-', 60) . "\n";
            echo "Total: " . count($rows) . " migration(s), {$pending} pending.\n";
            exit(0);
        }

        $pretend = in_array('--pretend', $argv, true) || $cmd === '--pretend';
        $report = Migrator::runPending($pretend);

        if ($pretend) {
            echo "DRY RUN (no changes applied)\n";
        }

        if (empty($report['applied'])) {
            echo "Database is up to date. Nothing to migrate.\n";
        } else {
            echo ($pretend ? "Would apply" : "Applied") . " " . count($report['applied']) . " migration(s):\n";
            foreach ($report['applied'] as $m) {
                printf("  - %s (%d run, %d skipped)\n", $m['migration'], $m['statements_run'], $m['statements_skipped']);
            }
        }
        echo "Already applied: " . count($report['skipped_already']) . " migration(s).\n";
        exit(0);
    } catch (\Throwable $e) {
        fwrite(STDERR, "\nMIGRATION FAILED:\n" . $e->getMessage() . "\n");
        exit(1);
    }
}
