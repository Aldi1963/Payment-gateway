<?php
/**
 * Health Check Endpoint
 * GET /api/health.php
 * 
 * Returns system health status including:
 * - Database connectivity
 * - Payment provider connectivity
 * - System information
 * - Service status
 * 
 * No authentication required (public endpoint for monitoring)
 */

require_once dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');

$startTime = microtime(true);
$checks = [];
$overallStatus = 'healthy';

// ============================================
// 1. DATABASE CHECK
// ============================================
try {
    $dbStart = microtime(true);
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SELECT 1");
    $stmt->fetch();
    $dbLatency = round((microtime(true) - $dbStart) * 1000, 2);
    
    $checks['database'] = [
        'status' => 'healthy',
        'latency_ms' => $dbLatency,
        'message' => 'MySQL connection successful',
    ];
} catch (\Throwable $e) {
    $checks['database'] = [
        'status' => 'unhealthy',
        'latency_ms' => null,
        'message' => 'Database connection failed',
    ];
    $overallStatus = 'unhealthy';
}

// ============================================
// 1b. DATABASE SCHEMA CHECK (migration drift)
// ============================================
if (($checks['database']['status'] ?? '') === 'healthy') {
    try {
        require_once base_path('app/Schema.php');

        // Critical tables/columns that code depends on
        $required = [
            'table:user_merchants' => Schema::hasTable('user_merchants'),
            'table:merchant_wa_configs' => Schema::hasTable('merchant_wa_configs'),
            'table:schema_migrations' => Schema::hasTable('schema_migrations'),
            'column:merchants.slug' => Schema::hasColumn('merchants', 'slug'),
            'column:merchants.owner_id' => Schema::hasColumn('merchants', 'owner_id'),
        ];

        $missing = array_keys(array_filter($required, fn($v) => $v === false));

        // Determine pending migrations if tracking table exists
        $pendingMigrations = [];
        if (Schema::hasTable('schema_migrations')) {
            try {
                if (!defined('MIGRATE_BOOTSTRAP')) define('MIGRATE_BOOTSTRAP', true);
                require_once base_path('scripts/migrate.php');
                foreach (Migrator::status() as $row) {
                    if (!$row['applied']) $pendingMigrations[] = $row['migration'];
                }
            } catch (\Throwable $e) {
                // ignore — status best-effort
            }
        }

        if (empty($missing) && empty($pendingMigrations)) {
            $checks['database_schema'] = [
                'status' => 'healthy',
                'message' => 'Schema up to date',
            ];
        } else {
            $checks['database_schema'] = [
                'status' => 'unhealthy',
                'missing' => $missing,
                'pending_migrations' => $pendingMigrations,
                'message' => 'Schema drift detected. Run: php scripts/migrate.php',
            ];
            $overallStatus = 'unhealthy';
        }
    } catch (\Throwable $e) {
        $checks['database_schema'] = [
            'status' => 'unknown',
            'message' => 'Schema check failed',
        ];
    }
}

// ============================================
// 2. PAYMENT PROVIDER: AldiQRIS
// ============================================
$aldiqrisUrl = setting('aldiqris_base_url', config('gateway.aldiqris.base_url', ''));
if (!empty($aldiqrisUrl)) {
    try {
        $providerStart = microtime(true);
        $ch = curl_init(rtrim($aldiqrisUrl, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_NOBODY => true,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $providerLatency = round((microtime(true) - $providerStart) * 1000, 2);
        
        if ($httpCode > 0 && $httpCode < 500) {
            $checks['provider_aldiqris'] = [
                'status' => 'healthy',
                'latency_ms' => $providerLatency,
                'http_code' => $httpCode,
                'message' => 'AldiQRIS reachable',
            ];
        } else {
            $checks['provider_aldiqris'] = [
                'status' => 'degraded',
                'latency_ms' => $providerLatency,
                'http_code' => $httpCode,
                'message' => $curlError ?: 'AldiQRIS returned error',
            ];
            if ($overallStatus === 'healthy') $overallStatus = 'degraded';
        }
    } catch (\Throwable $e) {
        $checks['provider_aldiqris'] = [
            'status' => 'unhealthy',
            'latency_ms' => null,
            'message' => 'AldiQRIS check failed',
        ];
        if ($overallStatus === 'healthy') $overallStatus = 'degraded';
    }
} else {
    $checks['provider_aldiqris'] = [
        'status' => 'not_configured',
        'message' => 'AldiQRIS base URL not set',
    ];
}

// ============================================
// 3. PAYMENT PROVIDER: Midtrans
// ============================================
$midtransKey = setting('midtrans_server_key', '');
$midtransEnv = setting('midtrans_environment', 'sandbox');
$midtransBaseUrl = $midtransEnv === 'production' 
    ? 'https://api.midtrans.com' 
    : 'https://api.sandbox.midtrans.com';

if (!empty($midtransKey)) {
    try {
        $providerStart = microtime(true);
        $ch = curl_init($midtransBaseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_NOBODY => true,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $providerLatency = round((microtime(true) - $providerStart) * 1000, 2);
        
        $checks['provider_midtrans'] = [
            'status' => ($httpCode > 0 && $httpCode < 500) ? 'healthy' : 'degraded',
            'latency_ms' => $providerLatency,
            'environment' => $midtransEnv,
            'message' => ($httpCode > 0 && $httpCode < 500) ? 'Midtrans reachable' : 'Midtrans returned error',
        ];
        if ($httpCode === 0 || $httpCode >= 500) {
            if ($overallStatus === 'healthy') $overallStatus = 'degraded';
        }
    } catch (\Throwable $e) {
        $checks['provider_midtrans'] = [
            'status' => 'unhealthy',
            'latency_ms' => null,
            'message' => 'Midtrans check failed',
        ];
        if ($overallStatus === 'healthy') $overallStatus = 'degraded';
    }
} else {
    $checks['provider_midtrans'] = [
        'status' => 'not_configured',
        'message' => 'Midtrans server key not set',
    ];
}

// ============================================
// 4. DISK SPACE CHECK
// ============================================
$storagePath = storage_path();
if (is_dir($storagePath)) {
    $freeSpace = disk_free_space($storagePath);
    $totalSpace = disk_total_space($storagePath);
    $usedPercent = round((1 - $freeSpace / $totalSpace) * 100, 1);
    
    $checks['disk'] = [
        'status' => $usedPercent < 90 ? 'healthy' : ($usedPercent < 95 ? 'degraded' : 'unhealthy'),
        'used_percent' => $usedPercent,
        'free_bytes' => $freeSpace,
        'message' => "Disk usage: {$usedPercent}%",
    ];
    if ($usedPercent >= 95) $overallStatus = 'unhealthy';
    elseif ($usedPercent >= 90 && $overallStatus === 'healthy') $overallStatus = 'degraded';
} else {
    $checks['disk'] = [
        'status' => 'unknown',
        'message' => 'Storage path not found',
    ];
}

// ============================================
// 5. PHP EXTENSIONS CHECK
// ============================================
$requiredExtensions = ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}
$checks['php_extensions'] = [
    'status' => empty($missingExtensions) ? 'healthy' : 'unhealthy',
    'missing' => $missingExtensions,
    'message' => empty($missingExtensions) ? 'All required extensions loaded' : 'Missing: ' . implode(', ', $missingExtensions),
];
if (!empty($missingExtensions)) $overallStatus = 'unhealthy';

// ============================================
// 6. CRON JOB CHECK (last run within expected interval)
// ============================================
$logFile = storage_path('logs.txt');
if (file_exists($logFile)) {
    $lastModified = filemtime($logFile);
    $minutesSinceLog = round((time() - $lastModified) / 60);
    $checks['cron'] = [
        'status' => $minutesSinceLog < 10 ? 'healthy' : ($minutesSinceLog < 60 ? 'degraded' : 'unknown'),
        'last_log_minutes_ago' => $minutesSinceLog,
        'message' => "Last log activity: {$minutesSinceLog} minutes ago",
    ];
} else {
    $checks['cron'] = [
        'status' => 'unknown',
        'message' => 'Log file not found',
    ];
}

// ============================================
// RESPONSE
// ============================================
$totalLatency = round((microtime(true) - $startTime) * 1000, 2);

$httpCode = match($overallStatus) {
    'healthy' => 200,
    'degraded' => 200,
    'unhealthy' => 503,
    default => 200,
};

http_response_code($httpCode);
echo json_encode([
    'status' => $overallStatus,
    'timestamp' => date('c'),
    'version' => '2.0.0',
    'service' => setting('app_name', 'PayGate Pro'),
    'environment' => getenv('APP_ENV') ?: 'development',
    'php_version' => PHP_VERSION,
    'total_check_ms' => $totalLatency,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
