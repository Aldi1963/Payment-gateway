<?php
/**
 * Application Configuration
 * Payment Gateway SaaS Multi Merchant
 */

return [
    // Application
    'app_name' => 'Clipku Pay',
    'app_version' => '1.0.0',
    'app_url' => getenv('APP_URL') ?: 'http://localhost',
    'app_env' => getenv('APP_ENV') ?: 'production',
    'app_debug' => getenv('APP_DEBUG') === 'true',

    // Paths
    'base_path' => dirname(__DIR__),
    'storage_path' => dirname(__DIR__) . '/storage',
    'public_path' => dirname(__DIR__) . '/public',

    // Session
    'session_name' => 'paygate_session',
    'session_lifetime' => 7200, // 2 hours
    'session_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'session_httponly' => true,

    // Security
    'csrf_token_name' => '_csrf_token',
    'password_min_length' => 8,
    'login_max_attempts' => 5,
    'login_lockout_time' => 900, // 15 minutes

    // Pagination
    'per_page' => 20,

    // Timezone
    'timezone' => 'Asia/Jakarta',

    // Roles
    'roles' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'finance' => 'Finance',
        'support' => 'Support',
        'merchant' => 'Merchant',
        'staff_merchant' => 'Staff Merchant',
    ],

    // User Statuses
    'user_statuses' => ['active', 'inactive', 'suspended'],

    // Merchant Statuses
    'merchant_statuses' => ['pending', 'active', 'suspended', 'rejected'],

    // Transaction Statuses
    'transaction_statuses' => ['PENDING', 'PAID', 'FAILED', 'EXPIRED', 'REFUNDED'],

    // Withdrawal Statuses
    'withdrawal_statuses' => [
        'PENDING', 'REVIEWING', 'APPROVED', 'PROCESSING',
        'SUCCESS', 'FAILED', 'REJECTED', 'CANCELED'
    ],

    // Settlement Statuses
    'settlement_statuses' => ['PENDING', 'APPROVED', 'TRANSFERRED', 'COMPLETED', 'FAILED'],

    // Minimum withdrawal amount (in Rupiah)
    'min_withdrawal' => 10000,

    // Fee Types
    'fee_types' => ['flat', 'percentage', 'hybrid'],

    // Default fee settings
    'default_fee_type' => 'percentage',
    'default_fee_value' => 0.7, // 0.7%
    'default_fee_flat' => 0, // Rp 0

    // File upload
    'max_file_size' => 5242880, // 5MB
];
