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
 * - Idempotency key support
 * - CORS headers
 * 
 * FEATURES:
 * - Pagination on list endpoints
 * - Filtering and sorting
 * - Versioned API support
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/RateLimiter.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Services/TransactionService.php');
require_once base_path('app/Services/WalletService.php');
require_once base_path('app/Services/WithdrawalService.php');
require_once base_path('app/Services/IdempotencyService.php');
require_once base_path('app/Services/PaginationService.php');

class ApiController
{
    private ?array $merchant = null;
    private RateLimiter $rateLimiter;
    private PaginationService $pagination;

    public function __construct()
    {
        $this->rateLimiter = new RateLimiter();
        $this->pagination = new PaginationService();
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
     * Handle idempotency check for write operations
     * Returns true if should proceed, false if cached response was sent
     */
    private function handleIdempotency(string $action, string $requestBody): bool
    {
        $idempotencyKey = IdempotencyService::extractFromHeaders();
        if ($idempotencyKey === null) {
            return true; // No idempotency key, proceed normally
        }

        $idempotencyService = new IdempotencyService();
        $result = $idempotencyService->check($this->merchant['id'], $idempotencyKey, $action, $requestBody);

        if ($result === null) {
            return true; // Key doesn't exist, proceed normally
        }

        if ($result['conflict'] ?? false) {
            json_response([
                'error' => 'Unprocessable Entity',
                'message' => $result['message'],
            ], 422);
        }

        // Return cached response
        http_response_code($result['response_code']);
        header('X-Idempotency-Replayed: true');
        echo $result['response_body'];
        exit;
    }

    /**
     * Store idempotency response after processing
     */
    private function storeIdempotencyResponse(string $action, string $requestBody, int $responseCode, array $responseData): void
    {
        $idempotencyKey = IdempotencyService::extractFromHeaders();
        if ($idempotencyKey === null) {
            return;
        }

        $idempotencyService = new IdempotencyService();
        $idempotencyService->store(
            $this->merchant['id'],
            $idempotencyKey,
            $action,
            $requestBody,
            $responseCode,
            json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
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

        // Check idempotency
        $this->handleIdempotency('create_transaction', $rawInput);

        $input = json_decode($rawInput, true);
        if (!$input) {
            json_response(['error' => 'Bad Request', 'message' => 'Invalid JSON body'], 400);
        }

        $transactionService = new TransactionService();
        $result = $transactionService->create($input, $this->merchant['id']);

        if ($result['success']) {
            $tx = $result['transaction'];
            $responseData = [
                'success' => true,
                'data' => [
                    'id' => $tx['id'],
                    'order_id' => $tx['order_id'],
                    'amount' => $tx['amount'],
                    'currency' => $tx['currency'] ?? 'IDR',
                    'fee' => $tx['fee'],
                    'net_amount' => $tx['net_amount'],
                    'status' => $tx['status'],
                    'payment_channel' => $tx['payment_channel'] ?? 'qris',
                    'payment_method' => $tx['payment_method'] ?? null,
                    'payment_url' => $tx['payment_url'],
                    'qr_url' => $tx['qr_url'],
                    'created_at' => $tx['created_at'],
                ],
            ];
            // Include snap_token for Midtrans (frontend Snap.js integration)
            if (!empty($tx['snap_token'])) {
                $responseData['data']['snap_token'] = $tx['snap_token'];
            }

            $this->storeIdempotencyResponse('create_transaction', $rawInput, 201, $responseData);
            json_response($responseData, 201);
        } else {
            $responseData = ['success' => false, 'error' => $result['message']];
            $this->storeIdempotencyResponse('create_transaction', $rawInput, 400, $responseData);
            json_response($responseData, 400);
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
                'currency' => $tx['currency'] ?? 'IDR',
                'fee' => $tx['fee'],
                'net_amount' => $tx['net_amount'],
                'status' => $tx['status'],
                'payment_channel' => $tx['payment_channel'] ?? null,
                'payment_method' => $tx['payment_method'] ?? null,
                'payment_url' => $tx['payment_url'],
                'qr_url' => $tx['qr_url'],
                'customer_name' => $tx['customer_name'] ?? null,
                'customer_email' => $tx['customer_email'] ?? null,
                'paid_at' => $tx['paid_at'],
                'expired_at' => $tx['expired_at'],
                'created_at' => $tx['created_at'],
                'updated_at' => $tx['updated_at'],
            ],
        ]);
    }

    /**
     * GET /api/transactions - List all transactions with pagination
     */
    public function listTransactions(): void
    {
        $this->authenticate();

        $params = $this->pagination->parseParams();
        $sort = $this->pagination->parseSortParams(
            ['created_at', 'amount', 'status', 'order_id', 'paid_at'],
            'created_at',
            'desc'
        );
        $filters = $this->pagination->parseFilters(['status', 'payment_channel', 'payment_method']);

        $transactionService = new TransactionService();
        $allFilters = array_merge($filters, [
            'sort_by' => $sort['sort_by'],
            'sort_order' => $sort['sort_order'],
            'page' => $params['page'],
            'per_page' => $params['per_page'],
        ]);

        $transactions = $transactionService->getByMerchant($this->merchant['id'], $allFilters);

        // If the service returns all data, paginate in memory
        $totalCount = count($transactions);
        $paginatedData = array_slice($transactions, $params['offset'], $params['per_page']);

        $data = array_map(fn($tx) => [
            'id' => $tx['id'],
            'order_id' => $tx['order_id'],
            'amount' => $tx['amount'],
            'currency' => $tx['currency'] ?? 'IDR',
            'fee' => $tx['fee'],
            'net_amount' => $tx['net_amount'],
            'status' => $tx['status'],
            'payment_channel' => $tx['payment_channel'] ?? null,
            'payment_method' => $tx['payment_method'] ?? null,
            'customer_name' => $tx['customer_name'] ?? null,
            'paid_at' => $tx['paid_at'],
            'created_at' => $tx['created_at'],
        ], $paginatedData);

        $response = $this->pagination->buildResponse($data, $totalCount, $params['page'], $params['per_page']);
        json_response(array_merge(['success' => true], $response));
    }

    /**
     * GET /api/transactions/{order_id}/status - Quick status check
     */
    public function getTransactionStatus(string $orderId): void
    {
        $this->authenticate();

        $orderId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $orderId);

        $transactionService = new TransactionService();
        $tx = $transactionService->findByOrderId($orderId);

        if (!$tx || $tx['merchant_id'] !== $this->merchant['id']) {
            json_response(['error' => 'Not Found', 'message' => 'Transaction not found'], 404);
        }

        json_response([
            'success' => true,
            'data' => [
                'order_id' => $tx['order_id'],
                'status' => $tx['status'],
                'paid_at' => $tx['paid_at'],
                'expired_at' => $tx['expired_at'],
                'amount' => $tx['amount'],
                'net_amount' => $tx['net_amount'],
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
     * GET /api/withdrawals - List withdrawals with pagination
     */
    public function getWithdrawals(): void
    {
        $this->authenticate();

        $params = $this->pagination->parseParams();
        $filters = $this->pagination->parseFilters(['status']);

        $withdrawalService = new WithdrawalService();
        $withdrawals = $withdrawalService->getByMerchant($this->merchant['id']);

        // Apply status filter
        if (!empty($filters['status'])) {
            $withdrawals = array_filter($withdrawals, fn($w) => strtoupper($w['status']) === strtoupper($filters['status']));
            $withdrawals = array_values($withdrawals);
        }

        $totalCount = count($withdrawals);
        $paginatedData = array_slice($withdrawals, $params['offset'], $params['per_page']);

        $data = array_map(fn($w) => [
            'id' => $w['id'],
            'amount' => $w['amount'],
            'fee' => $w['fee'] ?? 0,
            'net_amount' => $w['net_amount'] ?? $w['amount'],
            'bank_name' => $w['bank_name'],
            'account_number' => $w['account_number'],
            'account_name' => $w['account_name'] ?? null,
            'status' => $w['status'],
            'created_at' => $w['created_at'],
            'processed_at' => $w['processed_at'] ?? null,
        ], $paginatedData);

        $response = $this->pagination->buildResponse($data, $totalCount, $params['page'], $params['per_page']);
        json_response(array_merge(['success' => true], $response));
    }

    /**
     * POST /api/withdrawals - Create withdrawal request
     */
    public function createWithdrawal(): void
    {
        $this->authenticate();

        $rawInput = file_get_contents('php://input');
        if (strlen($rawInput) > 65536) {
            json_response(['error' => 'Bad Request', 'message' => 'Request body too large'], 413);
        }

        // Check idempotency
        $this->handleIdempotency('create_withdrawal', $rawInput);

        $input = json_decode($rawInput, true);
        if (!$input) {
            json_response(['error' => 'Bad Request', 'message' => 'Invalid JSON body'], 400);
        }

        // Validate required fields
        $required = ['amount', 'bank_name', 'account_number', 'account_name'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                json_response(['error' => 'Bad Request', 'message' => "Field '{$field}' is required"], 400);
            }
        }

        $amount = (int)$input['amount'];
        $minWithdrawal = (int)setting('min_withdrawal', 10000);
        if ($amount < $minWithdrawal) {
            json_response(['error' => 'Bad Request', 'message' => "Minimum withdrawal amount is " . format_currency($minWithdrawal)], 400);
        }

        // Check available balance
        $walletService = new WalletService();
        $wallet = $walletService->getByMerchant($this->merchant['id']);
        if (($wallet['available_balance'] ?? 0) < $amount) {
            json_response(['error' => 'Bad Request', 'message' => 'Insufficient balance'], 400);
        }

        $withdrawalService = new WithdrawalService();
        $result = $withdrawalService->create([
            'merchant_id' => $this->merchant['id'],
            'amount' => $amount,
            'bank_name' => sanitize($input['bank_name']),
            'account_number' => sanitize($input['account_number']),
            'account_name' => sanitize($input['account_name']),
            'note' => sanitize($input['note'] ?? ''),
        ]);

        if ($result['success'] ?? false) {
            $responseData = [
                'success' => true,
                'data' => [
                    'id' => $result['withdrawal']['id'] ?? null,
                    'amount' => $amount,
                    'bank_name' => $input['bank_name'],
                    'account_number' => $input['account_number'],
                    'status' => 'PENDING',
                    'message' => 'Withdrawal request created successfully',
                ],
            ];
            $this->storeIdempotencyResponse('create_withdrawal', $rawInput, 201, $responseData);
            json_response($responseData, 201);
        } else {
            $responseData = ['success' => false, 'error' => $result['message'] ?? 'Failed to create withdrawal'];
            json_response($responseData, 400);
        }
    }

    /**
     * POST /api/refunds - Create refund request
     */
    public function createRefund(): void
    {
        $this->authenticate();

        $rawInput = file_get_contents('php://input');
        if (strlen($rawInput) > 65536) {
            json_response(['error' => 'Bad Request', 'message' => 'Request body too large'], 413);
        }

        $this->handleIdempotency('create_refund', $rawInput);

        $input = json_decode($rawInput, true);
        if (!$input) {
            json_response(['error' => 'Bad Request', 'message' => 'Invalid JSON body'], 400);
        }

        // Validate required fields
        if (empty($input['order_id'])) {
            json_response(['error' => 'Bad Request', 'message' => "Field 'order_id' is required"], 400);
        }

        // Find transaction
        $transactionService = new TransactionService();
        $tx = $transactionService->findByOrderId($input['order_id']);
        if (!$tx || $tx['merchant_id'] !== $this->merchant['id']) {
            json_response(['error' => 'Not Found', 'message' => 'Transaction not found'], 404);
        }

        if ($tx['status'] !== 'PAID') {
            json_response(['error' => 'Bad Request', 'message' => 'Only PAID transactions can be refunded'], 400);
        }

        // Determine refund amount
        $refundAmount = (int)($input['amount'] ?? $tx['net_amount']);
        $maxRefundable = (int)$tx['net_amount'] - (int)($tx['refund_amount'] ?? 0);
        if ($refundAmount > $maxRefundable) {
            json_response(['error' => 'Bad Request', 'message' => "Maximum refundable amount is " . format_currency($maxRefundable)], 400);
        }
        if ($refundAmount <= 0) {
            json_response(['error' => 'Bad Request', 'message' => 'Refund amount must be greater than 0'], 400);
        }

        require_once base_path('app/Services/RefundService.php');
        $refundService = new RefundService();
        $result = $refundService->create([
            'transaction_id' => $tx['id'],
            'order_id' => $tx['order_id'],
            'merchant_id' => $this->merchant['id'],
            'amount' => $refundAmount,
            'type' => $refundAmount >= (int)$tx['net_amount'] ? 'full' : 'partial',
            'reason' => sanitize($input['reason'] ?? 'Refund via API'),
            'initiated_by' => $this->merchant['id'],
            'initiated_by_role' => 'merchant',
        ]);

        if ($result['success'] ?? false) {
            $responseData = [
                'success' => true,
                'data' => [
                    'id' => $result['refund']['id'] ?? null,
                    'order_id' => $tx['order_id'],
                    'refund_amount' => $refundAmount,
                    'type' => $refundAmount >= (int)$tx['net_amount'] ? 'full' : 'partial',
                    'status' => 'completed',
                    'message' => 'Refund processed successfully',
                ],
            ];
            $this->storeIdempotencyResponse('create_refund', $rawInput, 201, $responseData);
            json_response($responseData, 201);
        } else {
            $responseData = ['success' => false, 'error' => $result['message'] ?? 'Failed to process refund'];
            json_response($responseData, 400);
        }
    }

    /**
     * GET /api/settlements - List settlements with pagination
     */
    public function getSettlements(): void
    {
        $this->authenticate();

        $params = $this->pagination->parseParams();
        $filters = $this->pagination->parseFilters(['status', 'period']);

        require_once base_path('app/Services/SettlementService.php');
        $settlementService = new SettlementService();
        $settlements = $settlementService->getByMerchant($this->merchant['id']);

        // Apply filters
        if (!empty($filters['status'])) {
            $settlements = array_filter($settlements, fn($s) => strtoupper($s['status']) === strtoupper($filters['status']));
            $settlements = array_values($settlements);
        }
        if (!empty($filters['period'])) {
            $settlements = array_filter($settlements, fn($s) => $s['period'] === $filters['period']);
            $settlements = array_values($settlements);
        }

        $totalCount = count($settlements);
        $paginatedData = array_slice($settlements, $params['offset'], $params['per_page']);

        $data = array_map(fn($s) => [
            'id' => $s['id'],
            'period' => $s['period'],
            'total_transactions' => $s['total_transactions'],
            'total_gross' => $s['total_gross'],
            'total_fee' => $s['total_fee'],
            'total_net' => $s['total_net'],
            'status' => $s['status'],
            'created_at' => $s['created_at'],
            'approved_at' => $s['approved_at'] ?? null,
        ], $paginatedData);

        $response = $this->pagination->buildResponse($data, $totalCount, $params['page'], $params['per_page']);
        json_response(array_merge(['success' => true], $response));
    }

    /**
     * POST /api/webhook/test - Test webhook delivery
     */
    public function testWebhook(): void
    {
        $this->authenticate();

        $webhookUrl = $this->merchant['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            json_response(['error' => 'Bad Request', 'message' => 'No webhook URL configured for this merchant'], 400);
        }

        // SECURITY: Validate webhook URL is not targeting internal networks (SSRF protection)
        $urlCheck = validate_webhook_url($webhookUrl);
        if (!$urlCheck['safe']) {
            json_response(['error' => 'Bad Request', 'message' => 'Webhook URL is not safe: ' . $urlCheck['reason']], 400);
        }

        // Build test payload
        $testPayload = json_encode([
            'event' => 'test',
            'message' => 'This is a test webhook from PayGate Pro',
            'merchant_id' => $this->merchant['id'],
            'timestamp' => now(),
            'data' => [
                'order_id' => 'TEST-' . strtoupper(substr(generate_random(8), 0, 8)),
                'amount' => 10000,
                'status' => 'PAID',
            ],
        ]);

        // Generate signature
        $signature = hash_hmac('sha256', $testPayload, $this->merchant['api_key']);

        // Send test webhook
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $testPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Signature: ' . $signature,
                'X-Webhook-Event: test',
                'User-Agent: PayGatePro/2.0 Webhook',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $success = $httpCode >= 200 && $httpCode < 300;

        json_response([
            'success' => $success,
            'data' => [
                'webhook_url' => $webhookUrl,
                'http_code' => $httpCode,
                'delivered' => $success,
                'error' => $error ?: null,
                'response_preview' => $response ? substr($response, 0, 200) : null,
            ],
            'message' => $success ? 'Test webhook delivered successfully' : 'Webhook delivery failed',
        ]);
    }

    /**
     * GET /api/stats - Get merchant statistics
     */
    public function getStats(): void
    {
        $this->authenticate();

        $transactionService = new TransactionService();
        $stats = $transactionService->getMerchantStats($this->merchant['id']);

        json_response([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
