<?php
/**
 * API Controller
 * Handles merchant API requests (external integrations)
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Services/TransactionService.php');
require_once base_path('app/Services/WalletService.php');
require_once base_path('app/Services/WithdrawalService.php');

class ApiController
{
    private ?array $merchant = null;

    /**
     * Authenticate API request via Bearer token
     */
    public function authenticate(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) {
            json_response(['error' => 'Unauthorized', 'message' => 'Missing or invalid Authorization header'], 401);
        }

        $apiKey = substr($authHeader, 7);
        $merchantRepo = new MerchantRepository();
        $this->merchant = $merchantRepo->findByApiKey($apiKey);

        if (!$this->merchant) {
            json_response(['error' => 'Unauthorized', 'message' => 'Invalid API key'], 401);
        }

        if ($this->merchant['status'] !== 'active') {
            json_response(['error' => 'Forbidden', 'message' => 'Merchant account is not active'], 403);
        }

        return true;
    }

    /**
     * POST /api/transactions - Create transaction
     */
    public function createTransaction(): void
    {
        $this->authenticate();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            json_response(['error' => 'Bad Request', 'message' => 'Invalid JSON body'], 400);
        }

        $transactionService = new TransactionService();
        $result = $transactionService->create($input, $this->merchant['id']);

        if ($result['success']) {
            $tx = $result['transaction'];
            json_response([
                'success' => true,
                'data' => [
                    'id' => $tx['id'],
                    'order_id' => $tx['order_id'],
                    'amount' => $tx['amount'],
                    'fee' => $tx['fee'],
                    'net_amount' => $tx['net_amount'],
                    'status' => $tx['status'],
                    'payment_url' => $tx['payment_url'],
                    'qr_url' => $tx['qr_url'],
                    'created_at' => $tx['created_at'],
                ],
            ], 201);
        } else {
            json_response(['success' => false, 'error' => $result['message']], 400);
        }
    }

    /**
     * GET /api/transactions/{order_id}
     */
    public function getTransaction(string $orderId): void
    {
        $this->authenticate();

        $transactionService = new TransactionService();
        $tx = $transactionService->findByOrderId($orderId);

        if (!$tx || $tx['merchant_id'] !== $this->merchant['id']) {
            json_response(['error' => 'Not Found', 'message' => 'Transaction not found'], 404);
        }

        json_response([
            'success' => true,
            'data' => [
                'id' => $tx['id'],
                'order_id' => $tx['order_id'],
                'amount' => $tx['amount'],
                'fee' => $tx['fee'],
                'net_amount' => $tx['net_amount'],
                'status' => $tx['status'],
                'payment_url' => $tx['payment_url'],
                'qr_url' => $tx['qr_url'],
                'paid_at' => $tx['paid_at'],
                'created_at' => $tx['created_at'],
            ],
        ]);
    }

    /**
     * GET /api/wallet
     */
    public function getWallet(): void
    {
        $this->authenticate();

        $walletService = new WalletService();
        $wallet = $walletService->getByMerchant($this->merchant['id']);

        json_response([
            'success' => true,
            'data' => [
                'available_balance' => $wallet['available_balance'] ?? 0,
                'pending_balance' => $wallet['pending_balance'] ?? 0,
                'hold_balance' => $wallet['hold_balance'] ?? 0,
                'withdrawn_balance' => $wallet['withdrawn_balance'] ?? 0,
                'total_received' => $wallet['total_received'] ?? 0,
                'total_fee' => $wallet['total_fee'] ?? 0,
            ],
        ]);
    }

    /**
     * GET /api/withdrawals
     */
    public function getWithdrawals(): void
    {
        $this->authenticate();

        $withdrawalService = new WithdrawalService();
        $withdrawals = $withdrawalService->getByMerchant($this->merchant['id']);

        $data = array_map(fn($w) => [
            'id' => $w['id'],
            'amount' => $w['amount'],
            'bank_name' => $w['bank_name'],
            'account_number' => $w['account_number'],
            'status' => $w['status'],
            'created_at' => $w['created_at'],
        ], $withdrawals);

        json_response(['success' => true, 'data' => $data]);
    }
}
