<?php
/**
 * Schema Introspection Helper
 *
 * Lightweight helpers to check the current database schema state at runtime.
 * Used to:
 *   - Guard code paths that depend on migrations having been run
 *   - Power schema health checks
 *   - Give clear "database not migrated" messages instead of fatal 500s
 *
 * All lookups are cached per-request to avoid repeated information_schema hits.
 */

require_once __DIR__ . '/Database.php';

class Schema
{
    /** @var array<string,bool> cache of table existence */
    private static array $tableCache = [];

    /** @var array<string,bool> cache of column existence keyed "table.column" */
    private static array $columnCache = [];

    private static ?string $dbName = null;

    /**
     * Resolve the current database name (cached).
     */
    private static function dbName(): ?string
    {
        if (self::$dbName === null) {
            try {
                $pdo = Database::getConnection();
                self::$dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            } catch (\Throwable $e) {
                self::$dbName = '';
            }
        }
        return self::$dbName ?: null;
    }

    /**
     * Check whether a table exists in the current database.
     */
    public static function hasTable(string $table): bool
    {
        if (array_key_exists($table, self::$tableCache)) {
            return self::$tableCache[$table];
        }

        $exists = false;
        $db = self::dbName();
        if ($db !== null) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t"
                );
                $stmt->execute(['db' => $db, 't' => $table]);
                $exists = (int)$stmt->fetchColumn() > 0;
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return self::$tableCache[$table] = $exists;
    }

    /**
     * Check whether a column exists on a table in the current database.
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }

        $exists = false;
        $db = self::dbName();
        if ($db !== null) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c"
                );
                $stmt->execute(['db' => $db, 't' => $table, 'c' => $column]);
                $exists = (int)$stmt->fetchColumn() > 0;
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return self::$columnCache[$key] = $exists;
    }

    /**
     * Is the multi-project feature schema present?
     * Requires the pivot table, WA configs table, and the merchants.slug column.
     */
    public static function multiProjectReady(): bool
    {
        return self::hasTable('user_merchants')
            && self::hasTable('merchant_wa_configs')
            && self::hasColumn('merchants', 'slug')
            && self::hasColumn('merchants', 'owner_id');
    }

    /**
     * Is the account-level API key feature present?
     * Requires the users.api_key column.
     */
    public static function accountApiKeyReady(): bool
    {
        return self::hasColumn('users', 'api_key');
    }

    /**
     * Return a list of missing pieces for the multi-project schema (for diagnostics).
     */
    public static function multiProjectMissing(): array
    {
        $missing = [];
        if (!self::hasTable('user_merchants')) $missing[] = 'table:user_merchants';
        if (!self::hasTable('merchant_wa_configs')) $missing[] = 'table:merchant_wa_configs';
        if (!self::hasColumn('merchants', 'slug')) $missing[] = 'column:merchants.slug';
        if (!self::hasColumn('merchants', 'owner_id')) $missing[] = 'column:merchants.owner_id';
        return $missing;
    }

    /**
     * Clear the internal caches (useful right after running migrations).
     */
    public static function flushCache(): void
    {
        self::$tableCache = [];
        self::$columnCache = [];
        self::$dbName = null;
    }
}
