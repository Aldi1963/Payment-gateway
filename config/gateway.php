<?php
/**
 * Payment Gateway Configuration
 * AldiQRIS Integration Settings
 */

return [
    // AldiQRIS API Configuration
    'aldiqris' => [
        'base_url' => getenv('ALDIQRIS_BASE_URL') ?: 'https://aldiqris.pages.dev',
        'api_key' => getenv('ALDIQRIS_API_KEY') ?: '',
        'timeout' => 30,
        'ssl_verify' => getenv('APP_ENV') === 'production',
        'endpoint_create' => '/api/trx',
    ],

    // Webhook Configuration
    'webhook' => [
        'secret_key' => getenv('WEBHOOK_SECRET') ?: '',
        'signature_header' => 'X-Signature',
        'hash_algo' => 'sha256',
        'allowed_ips' => [], // Empty = allow all
        'max_payload_size' => 65536, // 64KB
    ],

    // Payment URL extraction keys (ordered by priority)
    'payment_url_keys' => [
        'payment_url',
        'checkout_url',
        'payment_link',
        'link',
        'url',
        'data.payment_url',
        'data.checkout_url',
        'data.payment_link',
    ],

    // QR URL extraction keys (ordered by priority)
    'qr_url_keys' => [
        'qr_url',
        'qris_url',
        'qr_image',
        'data.qr_url',
        'data.qris_url',
        'data.qr_image',
    ],

    // Order ID extraction keys from webhook
    'order_id_keys' => [
        'order_id',
        'data.order_id',
        'transaction.order_id',
        'invoice_id',
        'reference_id',
    ],

    // Status extraction keys from webhook
    'status_keys' => [
        'transaction_status',
        'status',
        'data.status',
        'transaction.status',
        'payment_status',
    ],

    // Status mapping (incoming => internal)
    'status_mapping' => [
        'paid' => 'PAID',
        'success' => 'PAID',
        'settlement' => 'PAID',
        'completed' => 'PAID',
        'pending' => 'PENDING',
        'unpaid' => 'PENDING',
        'waiting' => 'PENDING',
        'failed' => 'FAILED',
        'cancel' => 'FAILED',
        'canceled' => 'FAILED',
        'cancelled' => 'FAILED',
        'error' => 'FAILED',
        'expired' => 'EXPIRED',
        'expire' => 'EXPIRED',
    ],

    // Settlement Configuration
    'settlement' => [
        'auto_settle' => false, // Manual settlement by default
        'settle_delay_hours' => 24, // Hours before auto-settlement
        'min_settlement_amount' => 50000,
    ],

    // Notification
    'notification' => [
        'enabled' => false,
        'whatsapp_api' => '',
        'email_enabled' => false,
    ],
];
