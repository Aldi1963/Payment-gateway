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

// Ensure storage directory and critical files exist
$storageDir = dirname(__DIR__) . '/storage';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
}
$requiredFiles = [
    'users.json', 'merchants.json', 'transactions.json', 'wallets.json',
    'withdrawals.json', 'settlements.json', 'webhook_events.json',
    'audit_logs.json', 'settings.json', 'wallet_ledger.json',
    'notifications.json', 'config_changes.json', 'fee_rules.json',
    'webhook_retries.json', 'refunds.json',
];
foreach ($requiredFiles as $f) {
    $path = $storageDir . '/' . $f;
    if (!file_exists($path)) {
        @file_put_contents($path, '[]', LOCK_EX);
    }
}
if (!file_exists($storageDir . '/logs.txt')) {
    @touch($storageDir . '/logs.txt');
}
$rateLimitDir = $storageDir . '/rate_limits';
if (!is_dir($rateLimitDir)) {
    @mkdir($rateLimitDir, 0755, true);
}

// Initialize session
Auth::init();

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
