<?php
/**
 * API Controller
 * Handles merchant API requests (external integrations)
 * 
 * SECURITY:
 * - Rate limiting per IP (file-based)
 * - Timing-safe API key comparison
 * - IP whitelist enforcement
 * - Input size limit
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/RateLimiter.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Services/TransactionService.php');
require_once base_path('app/Services/WalletService.php');
require_once base_path('app/Services/WithdrawalService.php');

class ApiController
{
    private ?array $merchant = null;
    private RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->rateLimiter = new RateLimiter();
    }

    /**
     * Authenticate API request via Bearer token
     * SECURITY: timing-safe comparison, rate limiting, IP whitelist
     */
    public function authenticate(): bool
    {
        $ip = Auth::getTrustedClientIp();

        // Rate limit API authentication attempts per IP
        $rateLimitKey = RateLimiter::apiKey('auth', $ip);
        $maxAttempts = (int)setting('api_rate_limit', 60); // 60 requests per window
        $window = (int)setting('api_rate_window', 60); // 60-second window

        $rateCheck = $this->rateLimiter->check($rateLimitKey, $maxAttempts, $window);
        if (!$rateCheck['allowed']) {
            header('Retry-After: ' . $rateCheck['retry_after']);
            header('X-RateLimit-Limit: ' . $maxAttempts);
            header('X-RateLimit-Remaining: 0');
            json_response([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Try again in ' . $rateCheck['retry_after'] . ' seconds.',
                'retry_after' => $rateCheck['retry_after'],
            ], 429);
        }

        // Record this attempt
        $this->rateLimiter->hit($rateLimitKey, $window);

        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $maxAttempts);
        header('X-RateLimit-Remaining: ' . max(0, $rateCheck['remaining'] - 1));

        // Extract Bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) {
            json_response(['error' => 'Unauthorized', 'message' => 'Missing or invalid Authorization header'], 401);
        }

        $apiKey = substr($authHeader, 7);

        // Validate API key format (basic sanity check before DB lookup)
        if (strlen($apiKey) < 10 || strlen($apiKey) > 128) {
            json_response(['error' => 'Unauthorized', 'message' => 'Invalid API key format'], 401);
        }

        // Find merchant by API key (timing-safe)
        $merchantRepo = new MerchantRepository();
        $this->merchant = $merchantRepo->findByApiKeySecure($apiKey);

        if (!$this->merchant) {
            // Add extra delay on failed auth to prevent timing attacks
            usleep(random_int(100000, 300000)); // 100-300ms
            json_response(['error' => 'Unauthorized', 'message' => 'Invalid API key'], 401);
        }

        if ($this->merchant['status'] !== 'active') {
            json_response(['error' => 'Forbidden', 'message' => 'Merchant account is not active'], 403);
        }

        // IP Whitelist enforcement
        $whitelist = $this->merchant['ip_whitelist'] ?? '';
        if (!empty($whitelist)) {
            $allowedIps = is_array($whitelist) 
                ? $whitelist 
                : array_filter(array_map('trim', explode("\n", $whitelist)));
            
            if (!empty($allowedIps) && !in_array($ip, $allowedIps)) {
                app_log("API access denied for merchant {$this->merchant['id']} from IP {$ip} (not whitelisted)", 'SECURITY');
                json_response(['error' => 'Forbidden', 'message' => 'IP address not allowed'], 403);
            }
        }

        return true;
    }

    /**
     * POST /api/transactions - Create transaction
     */
    public function createTransaction(): void
    {
        $this->authenticate();

        // Limit request body size
        $rawInput = file_get_contents('php://input');
        if (strlen($rawInput) > 65536) {
            json_response(['error' => 'Bad Request', 'message' => 'Request body too large'], 413);
        }

        $input = json_decode($rawInput, true);
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

        // Sanitize order_id input
        $orderId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $orderId);

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
