<?php
/**
 * Transaction Service
 * Business logic for payment transactions
 * Supports multiple payment channels via PaymentChannelManager
 */

require_once base_path('app/Repositories/TransactionRepository.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Services/PaymentChannelManager.php');
require_once base_path('app/Services/AldiQrisService.php');
require_once base_path('app/Services/FeeService.php');
require_once base_path('app/Services/WalletService.php');
require_once base_path('app/Services/AuditLogService.php');

class TransactionService
{
    private TransactionRepository $transactionRepo;
    private MerchantRepository $merchantRepo;
    private FeeService $feeService;
    private WalletService $walletService;
    private AuditLogService $auditService;

    public function __construct()
    {
        $this->transactionRepo = new TransactionRepository();
        $this->merchantRepo = new MerchantRepository();
        $this->feeService = new FeeService();
        $this->walletService = new WalletService();
        $this->auditService = new AuditLogService();
    }

    /**
     * Create a new payment transaction
     * 
     * @param array $data Transaction data including optional 'payment_channel' and 'payment_method'
     * @param string $merchantId Merchant ID
     * @return array Result with success status and transaction data
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

        // Determine payment channel (null = customer will choose later on pay.php)
        $channelCode = !empty($data['payment_channel']) ? $data['payment_channel'] : null;
        $paymentMethod = $data['payment_method'] ?? null;

        // If channel specified, validate it
        if ($channelCode) {
            $channelManager = PaymentChannelManager::getInstance();
            $channel = $channelManager->getChannel($channelCode);
            if (!$channel) {
                return ['success' => false, 'message' => "Channel '{$channelCode}' tidak tersedia."];
            }
            if (!$channel->isEnabled()) {
                return ['success' => false, 'message' => "Channel '{$channelCode}' tidak aktif. Hubungi admin."];
            }

            // Validate provider credentials based on channel
            if ($channelCode === 'qris') {
                $providerApiKey = setting('aldiqris_api_key', config('gateway.aldiqris.api_key', ''));
                if (empty($providerApiKey)) {
                    return ['success' => false, 'message' => 'API key AldiQRIS belum dikonfigurasi.'];
                }
            } elseif ($channelCode === 'midtrans') {
                $midtransKey = setting('midtrans_server_key', '');
                if (empty($midtransKey)) {
                    return ['success' => false, 'message' => 'Midtrans Server Key belum dikonfigurasi.'];
                }
            }
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
            $redirectUrl = app_url('pay.php') . '?order_id=' . urlencode($orderId) . '&status=success';
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
            'payment_channel' => $channelCode,
            'payment_method' => $paymentMethod,
            'snap_token' => null,
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

        // payment_url always points to our branded checkout
        $transaction['payment_url'] = app_url('pay.php?order_id=' . urlencode($orderId));

        // If channel specified, call provider now. Otherwise customer picks later on pay.php
        if ($channelCode) {
            // Build channel-specific payload
            $channelPayload = [
                'order_id' => $orderId,
                'amount' => $amount,
                'link_name' => $transaction['link_name'],
                'webhook_url' => $webhookUrl,
                'redirect_url' => $redirectUrl,
                'merchant_id' => $merchantId,
                'customer_name' => $data['customer_name'] ?? '',
                'customer_wa' => $data['customer_wa'] ?? '',
                'customer_email' => $data['customer_email'] ?? '',
                'payment_method' => $paymentMethod,
            ];

            if ($channelCode === 'qris') {
                $channelPayload['api_key'] = $providerApiKey ?? '';
            }

            $transaction['api_request'] = json_encode($channelPayload);

            // Call the payment channel
            $apiResult = $channel->createPayment($channelPayload);
            $transaction['api_response'] = $apiResult['raw_response'] ?? json_encode($apiResult);

            if ($apiResult['success']) {
                if ($channelCode === 'qris') {
                    $transaction['qr_url'] = $apiResult['qr_url'];
                    $providerPaymentUrl = $apiResult['payment_url'];
                    if ($providerPaymentUrl) {
                        $transaction['note'] = ($transaction['note'] ? $transaction['note'] . ' | ' : '') .
                                               'provider_url:' . $providerPaymentUrl;
                    }
                } elseif ($channelCode === 'midtrans') {
                    $transaction['qr_url'] = $apiResult['qr_url'] ?? null;
                    $midtransMeta = array_filter([
                        'va_number' => $apiResult['va_number'] ?? null,
                        'va_bank' => $apiResult['va_bank'] ?? null,
                        'deeplink' => $apiResult['deeplink'] ?? null,
                        'payment_code' => $apiResult['payment_code'] ?? null,
                        'payment_type' => $apiResult['payment_type'] ?? null,
                        'midtrans_transaction_id' => $apiResult['midtrans_transaction_id'] ?? null,
                        'expiry_time' => $apiResult['expiry_time'] ?? null,
                    ]);
                    $transaction['snap_token'] = json_encode($midtransMeta);
                } else {
                    $transaction['qr_url'] = $apiResult['qr_url'] ?? null;
                }
            } else {
                $transaction['status'] = 'FAILED';
                $transaction['note'] = ($transaction['note'] ? $transaction['note'] . ' | ' : '') .
                                       'API Error: ' . ($apiResult['error'] ?? 'Unknown');
            }

            $apiSuccess = $apiResult['success'];
        } else {
            // No channel selected - save as PENDING, customer will pick method on pay.php
            $apiSuccess = true;
        }

        // Save transaction
        $this->transactionRepo->create($transaction);

        // Audit log
        $this->auditService->log(
            Auth::id(),
            Auth::role(),
            $merchantId,
            'create_transaction',
            "Created {$channelCode} transaction {$orderId} amount " . format_currency($amount),
            ['transaction_id' => $transactionId, 'order_id' => $orderId, 'amount' => $amount, 'channel' => $channelCode, 'method' => $paymentMethod]
        );

        return [
            'success' => $apiSuccess,
            'message' => $apiSuccess ? 'Transaksi berhasil dibuat.' : ($apiResult['error'] ?? 'Gagal membuat transaksi.'),
            'transaction' => $transaction,
        ];
    }

    /**
     * Select payment method for existing PENDING transaction (called from pay.php)
     * This triggers the actual provider API call
     */
    public function selectPaymentMethod(string $orderId, string $channelCode, ?string $paymentMethod = null): array
    {
        $transaction = $this->transactionRepo->findByOrderId($orderId);
        if (!$transaction) {
            return ['success' => false, 'message' => 'Transaksi tidak ditemukan.'];
        }
        if ($transaction['status'] !== 'PENDING') {
            return ['success' => false, 'message' => 'Transaksi sudah diproses.'];
        }
        // Don't re-call if already has payment details
        if (!empty($transaction['qr_url']) || !empty($transaction['snap_token'])) {
            return ['success' => true, 'message' => 'Metode sudah dipilih.', 'transaction' => $transaction];
        }

        $channelManager = PaymentChannelManager::getInstance();
        $channel = $channelManager->getChannel($channelCode);
        if (!$channel || !$channel->isEnabled()) {
            return ['success' => false, 'message' => "Channel '{$channelCode}' tidak tersedia."];
        }

        // Build payload
        $merchant = $this->merchantRepo->find($transaction['merchant_id']);
        $webhookUrl = $transaction['webhook_url'] ?: app_url('webhook.php');
        $redirectUrl = $transaction['redirect_url'] ?: app_url('pay.php?order_id=' . urlencode($orderId) . '&status=success');

        $channelPayload = [
            'order_id' => $orderId,
            'amount' => (int)$transaction['amount'],
            'link_name' => $transaction['link_name'] ?? '',
            'webhook_url' => $webhookUrl,
            'redirect_url' => $redirectUrl,
            'merchant_id' => $transaction['merchant_id'],
            'customer_name' => $transaction['customer_name'] ?? '',
            'customer_wa' => $transaction['customer_wa'] ?? '',
            'customer_email' => $transaction['customer_email'] ?? '',
            'payment_method' => $paymentMethod,
        ];

        if ($channelCode === 'qris') {
            $channelPayload['api_key'] = setting('aldiqris_api_key', config('gateway.aldiqris.api_key', ''));
        }

        // Call provider
        $apiResult = $channel->createPayment($channelPayload);

        // Update transaction
        $updates = [
            'payment_channel' => $channelCode,
            'payment_method' => $paymentMethod,
            'api_request' => json_encode($channelPayload),
            'api_response' => $apiResult['raw_response'] ?? json_encode($apiResult),
            'updated_at' => now(),
        ];

        if ($apiResult['success']) {
            if ($channelCode === 'qris') {
                $updates['qr_url'] = $apiResult['qr_url'] ?? null;
            } elseif ($channelCode === 'midtrans') {
                $updates['qr_url'] = $apiResult['qr_url'] ?? null;
                $midtransMeta = array_filter([
                    'va_number' => $apiResult['va_number'] ?? null,
                    'va_bank' => $apiResult['va_bank'] ?? null,
                    'deeplink' => $apiResult['deeplink'] ?? null,
                    'payment_code' => $apiResult['payment_code'] ?? null,
                    'payment_type' => $apiResult['payment_type'] ?? null,
                    'midtrans_transaction_id' => $apiResult['midtrans_transaction_id'] ?? null,
                    'expiry_time' => $apiResult['expiry_time'] ?? null,
                ]);
                $updates['snap_token'] = json_encode($midtransMeta);
            }
        } else {
            $updates['status'] = 'FAILED';
            $updates['note'] = ($transaction['note'] ? $transaction['note'] . ' | ' : '') .
                               'API Error: ' . ($apiResult['error'] ?? 'Unknown');
        }

        $this->transactionRepo->update($transaction['id'], $updates);

        // Return fresh data
        $fresh = $this->transactionRepo->find($transaction['id']);
        return [
            'success' => $apiResult['success'],
            'message' => $apiResult['success'] ? 'Metode pembayaran dipilih.' : ($apiResult['error'] ?? 'Gagal memproses.'),
            'transaction' => $fresh,
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

        // If paid, credit wallet and send notifications
        if ($newStatus === 'PAID' && $oldStatus !== 'PAID') {
            $this->walletService->creditTransaction($transaction);
            
            // Send notifications (email + WA)
            $this->sendPaymentNotifications($transaction);
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
     * Send payment notifications to merchant (email + WA)
     */
    private function sendPaymentNotifications(array $transaction): void
    {
        try {
            $merchant = $this->merchantRepo->find($transaction['merchant_id']);
            if (!$merchant) return;

            // Email notification
            if (setting('notif_on_payment', '0') === '1' || ($merchant['notif_email_payment'] ?? '0') === '1') {
                $notifEmail = $merchant['notif_email_payment'] ?? $merchant['email'] ?? '';
                if (!empty($notifEmail) && $notifEmail !== '0' && $notifEmail !== '1') {
                    // Use merchant email
                    $emailTo = $notifEmail;
                } else {
                    $emailTo = $merchant['email'] ?? '';
                }
                if (!empty($emailTo) && is_valid_email($emailTo)) {
                    require_once base_path('app/Services/EmailService.php');
                    $emailService = new EmailService();
                    $emailService->sendPaymentNotification($emailTo, $transaction);
                }
            }

            // WhatsApp notification (per-project config via WhatsAppService)
            // Uses the project's own WA provider/API key. Sends to the customer
            // and optionally the project admin number, based on merchant_wa_configs.
            require_once base_path('app/Services/WhatsAppService.php');
            $waService = new WhatsAppService();
            $waService->sendPaymentNotification($transaction['merchant_id'], $transaction);
        } catch (\Throwable $e) {
            app_log("Notification error: " . $e->getMessage(), 'ERROR');
        }
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
