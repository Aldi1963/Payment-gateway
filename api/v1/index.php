<?php
/**
 * API v1 - Path-based Router
 * 
 * RESTful path-based API endpoints:
 *   POST   /api/v1/transactions              Create transaction
 *   GET    /api/v1/transactions              List transactions
 *   GET    /api/v1/transactions/{order_id}   Get transaction
 *   GET    /api/v1/transactions/{order_id}/status  Quick status
 *   GET    /api/v1/wallet                    Get wallet
 *   GET    /api/v1/withdrawals               List withdrawals
 *   POST   /api/v1/withdrawals               Create withdrawal
 *   POST   /api/v1/refunds                   Create refund
 *   GET    /api/v1/settlements               List settlements
 *   GET    /api/v1/stats                     Get statistics
 *   POST   /api/v1/webhooks/test             Test webhook
 * 
 * Requires Apache mod_rewrite or Nginx configuration to route
 * all /api/v1/* requests to this file.
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';

// Set CORS headers
$allowedOrigins = setting('cors_allowed_origins', '*');
if ($allowedOrigins === '*' || $allowedOrigins === '') {
    header('Access-Control-Allow-Origin: *');
} else {
    // Specific allow-list configured: only reflect the origin if it matches.
    $reqOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowList = array_map('trim', explode(',', $allowedOrigins));
    if ($reqOrigin !== '' && in_array($reqOrigin, $allowList, true)) {
        header('Access-Control-Allow-Origin: ' . $reqOrigin);
        header('Vary: Origin');
    }
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key, X-Idempotency-Key');
header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After, X-Idempotency-Replayed');
header('Access-Control-Max-Age: 86400');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-API-Version: 1.0.0');

// Parse the request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Remove base path prefix to get the API path
// e.g., /api/v1/transactions -> transactions
$basePath = '/api/v1/';
$apiPath = '';
if (str_contains($path, $basePath)) {
    $apiPath = substr($path, strpos($path, $basePath) + strlen($basePath));
}
$apiPath = trim($apiPath, '/');
$segments = $apiPath ? explode('/', $apiPath) : [];

require_once base_path('app/Controllers/ApiController.php');
$apiController = new ApiController();

// Route matching
$resource = $segments[0] ?? '';
$resourceId = $segments[1] ?? null;
$subResource = $segments[2] ?? null;

switch ($resource) {
    case 'transactions':
        if ($method === 'POST' && !$resourceId) {
            $apiController->createTransaction();
        } elseif ($method === 'GET' && !$resourceId) {
            $apiController->listTransactions();
        } elseif ($method === 'GET' && $resourceId && $subResource === 'status') {
            $apiController->getTransactionStatus($resourceId);
        } elseif ($method === 'GET' && $resourceId) {
            $apiController->getTransaction($resourceId);
        } else {
            json_response(['error' => 'Method not allowed'], 405);
        }
        break;

    case 'wallet':
        if ($method === 'GET') {
            $apiController->getWallet();
        } else {
            json_response(['error' => 'Method not allowed'], 405);
        }
        break;

    case 'withdrawals':
        if ($method === 'POST') {
            $apiController->createWithdrawal();
        } elseif ($method === 'GET') {
            $apiController->getWithdrawals();
        } else {
            json_response(['error' => 'Method not allowed'], 405);
        }
        break;

    case 'refunds':
        if ($method === 'POST') {
            $apiController->createRefund();
        } else {
            json_response(['error' => 'Method not allowed'], 405);
        }
        break;

    case 'settlements':
        if ($method === 'GET') {
            $apiController->getSettlements();
        } else {
            json_response(['error' => 'Method not allowed'], 405);
        }
        break;

    case 'stats':
    case 'statistics':
        if ($method === 'GET') {
            $apiController->getStats();
        } else {
            json_response(['error' => 'Method not allowed'], 405);
        }
        break;

    case 'webhooks':
        if ($method === 'POST' && $resourceId === 'test') {
            $apiController->testWebhook();
        } else {
            json_response(['error' => 'Not found'], 404);
        }
        break;

    case '':
        json_response([
            'success' => true,
            'service' => setting('app_name', 'PayGate Pro') . ' API',
            'version' => '1.0.0',
            'base_url' => '/api/v1',
            'endpoints' => [
                'POST   /api/v1/transactions' => 'Create transaction',
                'GET    /api/v1/transactions' => 'List transactions',
                'GET    /api/v1/transactions/{order_id}' => 'Get transaction',
                'GET    /api/v1/transactions/{order_id}/status' => 'Status check',
                'GET    /api/v1/wallet' => 'Get wallet balance',
                'GET    /api/v1/withdrawals' => 'List withdrawals',
                'POST   /api/v1/withdrawals' => 'Create withdrawal',
                'POST   /api/v1/refunds' => 'Create refund',
                'GET    /api/v1/settlements' => 'List settlements',
                'GET    /api/v1/stats' => 'Get statistics',
                'POST   /api/v1/webhooks/test' => 'Test webhook',
            ],
        ]);
        break;

    default:
        json_response(['error' => 'Not found', 'message' => "Unknown resource: {$resource}"], 404);
}
