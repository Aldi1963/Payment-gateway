<?php
/**
 * Transaction Service
 * Business logic for payment transactions
 */

require_once base_path('app/Repositories/TransactionRepository.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Services/AldiQrisService.php');
require_once base_path('app/Services/FeeService.php');
require_once base_path('app/Services/WalletService.php');
require_once base_path('app/Services/AuditLogService.php');

class TransactionService
{
    private TransactionRepository $transactionRepo;
    private MerchantRepository $merchantRepo;
    private AldiQrisService $aldiQris;
    private FeeService $feeService;
    private WalletService $walletService;
    private AuditLogService $auditService;

    public function __construct()
    {
        $this->transactionRepo = new TransactionRepository();
        $this->merchantRepo = new MerchantRepository();
        $this->aldiQris = new AldiQrisService();
        $this->feeService = new FeeService();
        $this->walletService = new WalletService();
        $this->auditService = new AuditLogService();
    }

    /**
     * Create a new payment transaction
     */
    public function create(array $data, string $merchantId): array
    {
        // Get merchant
        $merchant = $this->merchantRepo->find($merchantId);
        if (!$merchant) {
            return ['success' => false, 'message' => 'Merchant tidak ditemukan.'];
        }
        if ($merchant['status'] !== 'active') {
            return ['success' => false, 'message' => 'Merchant tidak aktif.'];
        }
        // Validate provider API key (AldiQRIS)
        $providerApiKey = setting('aldiqris_api_key', config('gateway.aldiqris.api_key', ''));
        if (empty($providerApiKey)) {
            return ['success' => false, 'message' => 'API key AldiQRIS belum dikonfigurasi.'];
        }

        // Validate amount
        $amount = (int)($data['amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount harus lebih dari 0.'];
        }

        // Generate or validate order_id
        $orderId = !empty($data['order_id']) ? trim($data['order_id']) : generate_order_id();
        
        // Check uniqueness per merchant
        $existing = $this->transactionRepo->findByOrderIdAndMerchant($orderId, $merchantId);
        if ($existing) {
            return ['success' => false, 'message' => 'Order ID sudah digunakan.'];
        }

        // Validate optional fields
        if (!empty($data['customer_email']) && !is_valid_email($data['customer_email'])) {
            return ['success' => false, 'message' => 'Format email tidak valid.'];
        }
        if (!empty($data['customer_wa']) && !is_valid_phone($data['customer_wa'])) {
            return ['success' => false, 'message' => 'Format nomor WhatsApp tidak valid.'];
        }

        // Calculate fee using Fee Engine
        $feeResult = $this->feeService->calculateTransaction($amount, $merchantId);
        $fee = $feeResult['fee'];
        $netAmount = $amount - $fee;

        // Build webhook URL
        $webhookUrl = $data['webhook_url'] ?? $merchant['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            $webhookUrl = app_url('webhook.php');
        }

        // Build redirect URL
        $redirectUrl = $data['redirect_url'] ?? $merchant['redirect_url'] ?? '';
        if (empty($redirectUrl)) {
            $redirectUrl = app_url('success.php') . '?order_id=' . urlencode($orderId);
        }

        // Prepare transaction record
        $transactionId = generate_uuid();
        $transaction = [
            'id' => $transactionId,
            'merchant_id' => $merchantId,
            'order_id' => $orderId,
            'amount' => $amount,
            'fee' => $fee,
            'fee_type' => $feeResult['fee_type'],
            'fee_rule_id' => $feeResult['rule_id'],
            'fee_snapshot' => $feeResult['snapshot'],
            'net_amount' => $netAmount,
            'status' => 'PENDING',
            'link_name' => $data['link_name'] ?? "Tagihan {$orderId}",
            'customer_name' => $data['customer_name'] ?? '',
            'customer_wa' => $data['customer_wa'] ?? '',
            'customer_email' => $data['customer_email'] ?? '',
            'webhook_url' => $webhookUrl,
            'redirect_url' => $redirectUrl,
            'note' => $data['note'] ?? '',
            'payment_url' => null,
            'qr_url' => null,
            'api_request' => null,
            'api_response' => null,
            'paid_at' => null,
            'expired_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Build API payload
        $apiPayload = [
            'order_id' => $orderId,
            'amount' => $amount,
            'link_name' => $transaction['link_name'],
            'webhook_url' => $webhookUrl,
            'redirect_url' => $redirectUrl,
        ];

        // Add customer info if available
        if (!empty($data['customer_name']) || !empty($data['customer_wa']) || !empty($data['customer_email'])) {
            $apiPayload['customer'] = array_filter([
                'name' => $data['customer_name'] ?? '',
                'wa' => $data['customer_wa'] ?? '',
                'email' => $data['customer_email'] ?? '',
            ]);
        }

        $transaction['api_request'] = json_encode($apiPayload);

        // Call AldiQRIS API using provider API key
        $apiResult = $this->aldiQris->createTransaction($apiPayload, $providerApiKey);
        $transaction['api_response'] = $apiResult['raw_response'] ?? json_encode($apiResult);

        if ($apiResult['success']) {
            $transaction['payment_url'] = $apiResult['payment_url'];
            $transaction['qr_url'] = $apiResult['qr_url'];
        } else {
            // Still save the transaction even if API fails
            $transaction['status'] = 'FAILED';
            $transaction['note'] = ($transaction['note'] ? $transaction['note'] . ' | ' : '') . 
                                   'API Error: ' . ($apiResult['error'] ?? 'Unknown');
        }

        // Save transaction
        $this->transactionRepo->create($transaction);

        // Audit log
        $this->auditService->log(
            Auth::id(),
            Auth::role(),
            $merchantId,
            'create_transaction',
            "Created transaction {$orderId} amount " . format_currency($amount),
            ['transaction_id' => $transactionId, 'order_id' => $orderId, 'amount' => $amount]
        );

        return [
            'success' => $apiResult['success'],
            'message' => $apiResult['success'] ? 'Transaksi berhasil dibuat.' : ($apiResult['error'] ?? 'Gagal membuat transaksi.'),
            'transaction' => $transaction,
        ];
    }

    /**
     * Update transaction status from webhook
     */
    public function updateStatus(string $orderId, string $newStatus, array $webhookData = []): bool
    {
        $transaction = $this->transactionRepo->findByOrderId($orderId);
        if (!$transaction) {
            app_log("Transaction not found for order_id: {$orderId}", 'WARNING');
            return false;
        }

        $oldStatus = $transaction['status'];
        if ($oldStatus === $newStatus) {
            return true; // No change needed
        }

        // Update transaction
        $updates = [
            'status' => $newStatus,
            'updated_at' => now(),
        ];

        if ($newStatus === 'PAID' && empty($transaction['paid_at'])) {
            $updates['paid_at'] = now();
        }

        $this->transactionRepo->update($transaction['id'], $updates);

        // If paid, credit wallet
        if ($newStatus === 'PAID' && $oldStatus !== 'PAID') {
            $this->walletService->creditTransaction($transaction);
        }

        // Audit log
        $this->auditService->log(
            'system',
            'system',
            $transaction['merchant_id'],
            'status_changed',
            "Transaction {$orderId} status changed from {$oldStatus} to {$newStatus}",
            ['transaction_id' => $transaction['id'], 'old_status' => $oldStatus, 'new_status' => $newStatus, 'webhook_data' => $webhookData]
        );

        return true;
    }

    /**
     * Get transactions by merchant
     */
    public function getByMerchant(string $merchantId, array $filters = []): array
    {
        return $this->transactionRepo->findByMerchant($merchantId, $filters);
    }

    /**
     * Get all transactions (admin)
     */
    public function getAll(array $filters = []): array
    {
        return $this->transactionRepo->findAll($filters);
    }

    /**
     * Get transaction by ID
     */
    public function find(string $id): ?array
    {
        return $this->transactionRepo->find($id);
    }

    /**
     * Get transaction by order_id
     */
    public function findByOrderId(string $orderId): ?array
    {
        return $this->transactionRepo->findByOrderId($orderId);
    }

    /**
     * Get statistics for merchant dashboard
     */
    public function getMerchantStats(string $merchantId): array
    {
        $transactions = $this->transactionRepo->findByMerchant($merchantId);
        
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');
        
        $stats = [
            'total_transactions' => count($transactions),
            'total_revenue' => 0,
            'today_transactions' => 0,
            'today_revenue' => 0,
            'month_transactions' => 0,
            'month_revenue' => 0,
            'pending_count' => 0,
            'paid_count' => 0,
            'failed_count' => 0,
            'expired_count' => 0,
        ];

        foreach ($transactions as $tx) {
            $txDate = substr($tx['created_at'], 0, 10);
            $txMonth = substr($tx['created_at'], 0, 7);

            if ($tx['status'] === 'PAID') {
                $stats['total_revenue'] += $tx['net_amount'];
                $stats['paid_count']++;
                if ($txDate === $today) {
                    $stats['today_revenue'] += $tx['net_amount'];
                }
                if ($txMonth === $thisMonth) {
                    $stats['month_revenue'] += $tx['net_amount'];
                }
            }
            
            if ($txDate === $today) $stats['today_transactions']++;
            if ($txMonth === $thisMonth) $stats['month_transactions']++;
            
            match($tx['status']) {
                'PENDING' => $stats['pending_count']++,
                'FAILED' => $stats['failed_count']++,
                'EXPIRED' => $stats['expired_count']++,
                default => null,
            };
        }

        return $stats;
    }

    /**
     * Get admin statistics
     */
    public function getAdminStats(): array
    {
        $transactions = $this->transactionRepo->findAll();
        
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');
        
        $stats = [
            'total_transactions' => count($transactions),
            'total_revenue' => 0,
            'total_fee' => 0,
            'today_transactions' => 0,
            'today_revenue' => 0,
            'month_transactions' => 0,
            'month_revenue' => 0,
            'pending_count' => 0,
            'paid_count' => 0,
            'failed_count' => 0,
        ];

        foreach ($transactions as $tx) {
            $txDate = substr($tx['created_at'], 0, 10);
            $txMonth = substr($tx['created_at'], 0, 7);

            if ($tx['status'] === 'PAID') {
                $stats['total_revenue'] += $tx['amount'];
                $stats['total_fee'] += $tx['fee'];
                $stats['paid_count']++;
                if ($txDate === $today) $stats['today_revenue'] += $tx['amount'];
            }
            
            if ($txDate === $today) $stats['today_transactions']++;
            if ($txMonth === $thisMonth) $stats['month_transactions']++;
            if ($tx['status'] === 'PENDING') $stats['pending_count']++;
            if ($tx['status'] === 'FAILED') $stats['failed_count']++;
        }

        return $stats;
    }
}
