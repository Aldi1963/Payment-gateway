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

// Initialize session
Auth::init();

// Validate session fingerprint (anti-hijacking)
if (!Auth::validateSession()) {
    // Session was invalidated - redirect to login
    if (!str_contains($_SERVER['PHP_SELF'] ?? '', 'login.php') && 
        !str_contains($_SERVER['PHP_SELF'] ?? '', 'webhook.php') &&
        !str_contains($_SERVER['PHP_SELF'] ?? '', 'api/')) {
        flash('error', 'Sesi tidak valid. Silakan login ulang.');
        redirect('/login.php');
    }
}
