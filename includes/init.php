<?php
/**
 * Application Initialization
 * Include this file at the top of every page
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Load helpers
require_once dirname(__DIR__) . '/app/Helpers.php';
require_once dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/includes/icons.php';

// Initialize database connection
require_once dirname(__DIR__) . '/app/Database.php';

try {
    // Test that database connection is available
    // Only attempt if config file exists (skip for install.php)
    $dbConfigPath = dirname(__DIR__) . '/config/database.php';
    if (file_exists($dbConfigPath)) {
        Database::getConnection();
    }
} catch (\Throwable $e) {
    // Database not configured or unavailable
    $isInstallPage = str_contains($_SERVER['PHP_SELF'] ?? '', 'install.php');
    if (!$isInstallPage) {
        // Show user-friendly error
        http_response_code(503);
        echo '<!DOCTYPE html><html><head><title>Database Error</title></head><body>';
        echo '<h1>Database Connection Error</h1>';
        echo '<p>Could not connect to the database. Please check your configuration or run the installer.</p>';
        echo '<p><a href="/install.php">Run Installer</a></p>';
        echo '</body></html>';
        exit;
    }
}

// Initialize session
Auth::init();

// Force HTTPS redirect (if enabled in settings)
if (setting('force_https', '0') === '1') {
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        $isApi = str_contains($_SERVER['PHP_SELF'] ?? '', 'api/');
        $isCron = str_contains($_SERVER['PHP_SELF'] ?? '', 'cron.php');
        if (!$isApi && !$isCron) {
            $redirectUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $redirectUrl, true, 301);
            exit;
        }
    }
}

// Validate session fingerprint (anti-hijacking)
if (!Auth::validateSession()) {
    // Session was invalidated - redirect to login
    if (!str_contains($_SERVER['PHP_SELF'] ?? '', 'login.php') && 
        !str_contains($_SERVER['PHP_SELF'] ?? '', 'webhook.php') &&
        !str_contains($_SERVER['PHP_SELF'] ?? '', 'api/') &&
        !str_contains($_SERVER['PHP_SELF'] ?? '', 'install.php') &&
        !str_contains($_SERVER['PHP_SELF'] ?? '', 'verify.php') &&
        !str_contains($_SERVER['PHP_SELF'] ?? '', 'pay.php') &&
        !str_contains($_SERVER['PHP_SELF'] ?? '', 'cron.php')) {
        flash('error', 'Sesi tidak valid. Silakan login ulang.');
        redirect('/login.php');
    }
}
