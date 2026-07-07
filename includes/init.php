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
