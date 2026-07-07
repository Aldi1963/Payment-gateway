<?php
/**
 * Database Connection Singleton
 * PDO wrapper for MySQL connectivity
 */

class Database
{
    private static ?PDO $pdo = null;
    private static bool $available = true;

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $configPath = base_path('config/database.php');
            if (!file_exists($configPath)) {
                self::$available = false;
                throw new \RuntimeException('Database not configured. Please run install.php');
            }
            $config = require $configPath;
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            self::$pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        }
        return self::$pdo;
    }

    /**
     * Check if database is available (config exists and connection works)
     */
    public static function isAvailable(): bool
    {
        if (!file_exists(base_path('config/database.php'))) {
            return false;
        }
        try {
            self::getConnection();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function close(): void
    {
        self::$pdo = null;
    }
}
