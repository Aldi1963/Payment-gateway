<?php
/**
 * API Endpoint
 * Handles internal merchant API and admin AJAX requests
 * 
 * Merchant API (Bearer auth):
 *   POST ?action=create_transaction
 *   GET  ?action=get_transaction&order_id=XXX
 *   GET  ?action=wallet
 *   GET  ?action=withdrawals
 * 
 * Internal AJAX (session auth):
 *   GET  ?action=tx_detail&id=XXX
 */

require_once dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

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
    case 'create_transaction':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['error' => 'Method not allowed'], 405);
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

    case 'wallet':
        $apiController->getWallet();
        break;

    case 'withdrawals':
        $apiController->getWithdrawals();
        break;

    default:
        json_response([
            'success' => true,
            'service' => 'Clipku Pay API',
            'version' => '1.0.0',
            'endpoints' => [
                'POST ?action=create_transaction',
                'GET  ?action=get_transaction&order_id=XXX',
                'GET  ?action=wallet',
                'GET  ?action=withdrawals',
            ],
            'auth' => 'Bearer YOUR_API_KEY',
        ]);
}
