<?php
/**
 * API Endpoint Router
 * Handles internal merchant API and admin AJAX requests
 * 
 * Supported API versions:
 *   v1 (current) - Query parameter based: ?action=xxx
 *   v1 path-based: /api/v1/transactions, etc.
 * 
 * Merchant API (Bearer auth):
 *   POST ?action=create_transaction         Create new transaction
 *   GET  ?action=get_transaction&order_id=X Get transaction by order_id
 *   GET  ?action=transactions               List transactions (paginated)
 *   GET  ?action=transaction_status&order_id=X  Quick status check
 *   GET  ?action=wallet                     Get wallet balance
 *   GET  ?action=withdrawals                List withdrawals (paginated)
 *   POST ?action=create_withdrawal          Create withdrawal request
 *   POST ?action=refund                     Create refund
 *   GET  ?action=settlements                List settlements (paginated)
 *   GET  ?action=stats                      Get merchant statistics
 *   POST ?action=webhook_test               Test webhook delivery
 * 
 * Internal AJAX (session auth):
 *   GET  ?action=tx_detail&id=XXX
 */

require_once dirname(__DIR__) . '/includes/init.php';

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
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key, X-Idempotency-Key, X-CSRF-Token');
header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After, X-Idempotency-Replayed');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-API-Version: 2.0.0');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Internal AJAX endpoints (session-based)
if ($action === 'tx_detail') {
    if (!Auth::check()) {
        json_response(['error' => 'Unauthorized'], 401);
    }
    require_once base_path('app/Services/TransactionService.php');
    $txService = new TransactionService();
    $tx = $txService->find($_GET['id'] ?? '');
    
    if (!$tx) {
        json_response(['error' => 'Not found'], 404);
    }
    // Access check for merchants
    if (Auth::isMerchant() && $tx['merchant_id'] !== Auth::merchantId()) {
        json_response(['error' => 'Forbidden'], 403);
    }
    json_response(['success' => true, 'transaction' => $tx]);
}

// External Merchant API (Bearer token auth)
require_once base_path('app/Controllers/ApiController.php');
$apiController = new ApiController();

switch ($action) {
    // === TRANSACTIONS ===
    case 'create_transaction':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed', 'message' => 'Use POST method'], 405);
        }
        $apiController->createTransaction();
        break;

    case 'get_transaction':
        $orderId = $_GET['order_id'] ?? '';
        if (empty($orderId)) {
            json_response(['error' => 'Missing order_id parameter'], 400);
        }
        $apiController->getTransaction($orderId);
        break;

    case 'transactions':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_response(['error' => 'Method not allowed', 'message' => 'Use GET method'], 405);
        }
        $apiController->listTransactions();
        break;

    case 'transaction_status':
        $orderId = $_GET['order_id'] ?? '';
        if (empty($orderId)) {
            json_response(['error' => 'Missing order_id parameter'], 400);
        }
        $apiController->getTransactionStatus($orderId);
        break;

    // === WALLET ===
    case 'wallet':
        $apiController->getWallet();
        break;

    // === WITHDRAWALS ===
    case 'withdrawals':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $apiController->createWithdrawal();
        } else {
            $apiController->getWithdrawals();
        }
        break;

    case 'create_withdrawal':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed', 'message' => 'Use POST method'], 405);
        }
        $apiController->createWithdrawal();
        break;

    // === REFUNDS ===
    case 'refund':
    case 'refunds':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed', 'message' => 'Use POST method'], 405);
        }
        $apiController->createRefund();
        break;

    // === SETTLEMENTS ===
    case 'settlements':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_response(['error' => 'Method not allowed', 'message' => 'Use GET method'], 405);
        }
        $apiController->getSettlements();
        break;

    // === STATISTICS ===
    case 'stats':
    case 'statistics':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            json_response(['error' => 'Method not allowed', 'message' => 'Use GET method'], 405);
        }
        $apiController->getStats();
        break;

    // === WEBHOOK TEST ===
    case 'webhook_test':
    case 'test_webhook':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed', 'message' => 'Use POST method'], 405);
        }
        $apiController->testWebhook();
        break;

    // === DEFAULT / INFO ===
    default:
        json_response([
            'success' => true,
            'service' => setting('app_name', 'PayGate Pro') . ' API',
            'version' => '2.0.0',
            'endpoints' => [
                'transactions' => [
                    'POST ?action=create_transaction' => 'Create new transaction',
                    'GET  ?action=get_transaction&order_id=XXX' => 'Get transaction detail',
                    'GET  ?action=transactions' => 'List transactions (paginated)',
                    'GET  ?action=transaction_status&order_id=XXX' => 'Quick status check',
                ],
                'wallet' => [
                    'GET  ?action=wallet' => 'Get wallet balance',
                ],
                'withdrawals' => [
                    'GET  ?action=withdrawals' => 'List withdrawals (paginated)',
                    'POST ?action=create_withdrawal' => 'Create withdrawal request',
                ],
                'refunds' => [
                    'POST ?action=refund' => 'Create refund',
                ],
                'settlements' => [
                    'GET  ?action=settlements' => 'List settlements (paginated)',
                ],
                'statistics' => [
                    'GET  ?action=stats' => 'Get merchant statistics',
                ],
                'webhooks' => [
                    'POST ?action=webhook_test' => 'Test webhook delivery',
                ],
                'health' => [
                    'GET  /api/health.php' => 'System health check (no auth required)',
                ],
            ],
            'auth' => 'Bearer YOUR_ACCOUNT_API_KEY (one key for all projects)',
            'headers' => [
                'Authorization' => 'Bearer <account_api_key> (required)',
                'X-Project-Id' => '<project_id> (required when account has >1 project; or use X-Project: <slug>)',
                'Content-Type' => 'application/json (for POST requests)',
                'Idempotency-Key' => '<unique-key> (optional, for POST requests)',
            ],
            'note' => 'Legacy per-project API keys remain valid; with a legacy key the project is inferred and X-Project-Id is not needed. Webhook signature (X-Signature) uses the per-project Webhook Signing Secret.',
            'pagination' => [
                'page' => 'Page number (default: 1)',
                'per_page' => 'Items per page (default: 20, max: 100)',
                'sort_by' => 'Sort field (varies per endpoint)',
                'sort_order' => 'asc or desc (default: desc)',
            ],
        ]);
}
