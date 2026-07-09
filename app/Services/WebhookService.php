<?php
/**
 * Webhook Service
 * Handles incoming webhook events from multiple providers (AldiQRIS & Midtrans)
 * 
 * Detection logic:
 * - If payload contains 'signature_key' field → Midtrans webhook
 * - Otherwise → AldiQRIS webhook (validated via X-Signature header)
 */

require_once base_path('app/Repositories/WebhookRepository.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Services/AldiQrisService.php');
require_once base_path('app/Services/TransactionService.php');
require_once base_path('app/Services/AuditLogService.php');

class WebhookService
{
    private WebhookRepository $webhookRepo;
    private MerchantRepository $merchantRepo;
    private AldiQrisService $aldiQris;
    private TransactionService $transactionService;
    private AuditLogService $auditService;

    public function __construct()
    {
        $this->webhookRepo = new WebhookRepository();
        $this->merchantRepo = new MerchantRepository();
        $this->aldiQris = new AldiQrisService();
        $this->transactionService = new TransactionService();
        $this->auditService = new AuditLogService();
    }

    /**
     * Process incoming webhook - auto-detect provider
     */
    public function process(string $rawPayload, array $headers): array
    {
        $data = json_decode($rawPayload, true);

        if (!$data) {
            $this->logEvent('invalid', null, $rawPayload, 'Invalid JSON payload');
            return ['success' => false, 'message' => 'Invalid JSON payload', 'code' => 400];
        }

        // Auto-detect provider based on payload structure
        if (isset($data['signature_key']) || isset($data['transaction_id'])) {
            // Midtrans webhook (contains signature_key in body)
            return $this->processMidtrans($rawPayload, $data, $headers);
        }

        // Default: AldiQRIS webhook (signature in X-Signature header)
        return $this->processAldiQris($rawPayload, $data, $headers);
    }

    /**
     * Process AldiQRIS webhook
     */
    private function processAldiQris(string $rawPayload, array $data, array $headers): array
    {
        $signature = $headers['HTTP_X_SIGNATURE'] ?? $headers['X-Signature'] ?? '';

        // Extract order_id from payload
        $orderId = $this->aldiQris->extractOrderId($data);
        if (!$orderId) {
            $this->logEvent('invalid', null, $rawPayload, 'Missing order_id in payload');
            return ['success' => false, 'message' => 'Missing order_id', 'code' => 400];
        }

        // Find transaction
        $transaction = $this->transactionService->findByOrderId($orderId);
        if (!$transaction) {
            $this->logEvent('invalid', null, $rawPayload, "Transaction not found for order_id: {$orderId}");
            return ['success' => false, 'message' => 'Transaction not found', 'code' => 404];
        }

        // Get merchant for logging
        $merchant = $this->merchantRepo->find($transaction['merchant_id']);
        if (!$merchant) {
            $this->logEvent('invalid', null, $rawPayload, "Merchant not found for transaction");
            return ['success' => false, 'message' => 'Merchant not found', 'code' => 404];
        }

        // Validate signature using AldiQRIS provider API key
        $secretKey = setting('aldiqris_api_key', config('gateway.aldiqris.api_key', ''));
        if (empty($secretKey)) {
            $this->logEvent('invalid', $merchant['id'], $rawPayload, 'AldiQRIS API key not configured for webhook validation');
            return ['success' => false, 'message' => 'Webhook validation not configured', 'code' => 500];
        }

        if (empty($signature)) {
            $this->logEvent('invalid_signature', $merchant['id'], $rawPayload, 'Missing webhook signature header');
            $this->auditService->log('system', 'system', $merchant['id'], 'webhook_invalid',
                "Missing X-Signature header for order {$orderId}", ['order_id' => $orderId, 'ip' => get_client_ip()]);
            return ['success' => false, 'message' => 'Missing signature', 'code' => 401];
        }

        $isValid = $this->aldiQris->validateWebhookSignature($rawPayload, $signature, $secretKey);
        if (!$isValid) {
            $this->logEvent('invalid_signature', $merchant['id'], $rawPayload, 'Invalid webhook signature');
            $this->auditService->log('system', 'system', $merchant['id'], 'webhook_invalid',
                "Invalid webhook signature for order {$orderId}", ['order_id' => $orderId, 'ip' => get_client_ip()]);
            return ['success' => false, 'message' => 'Invalid signature', 'code' => 403];
        }

        // Extract status
        $status = $this->aldiQris->extractStatus($data);
        if (!$status) {
            $this->logEvent('warning', $merchant['id'], $rawPayload, "Could not extract status for order {$orderId}");
            return ['success' => false, 'message' => 'Could not extract status', 'code' => 400];
        }

        // Update transaction status
        $updated = $this->transactionService->updateStatus($orderId, $status, $data);

        $this->logEvent(
            $updated ? 'success' : 'error', $merchant['id'], $rawPayload,
            $updated ? "AldiQRIS: Status updated to {$status} for order {$orderId}" : "AldiQRIS: Failed to update status for {$orderId}",
            ['order_id' => $orderId, 'status' => $status, 'provider' => 'aldiqris', 'signature_valid' => true, 'transaction_id' => $transaction['id']]
        );

        $this->auditService->log('system', 'system', $merchant['id'], 'webhook_received',
            "AldiQRIS webhook for order {$orderId}, status: {$status}",
            ['order_id' => $orderId, 'status' => $status, 'provider' => 'aldiqris', 'ip' => get_client_ip()]);

        return ['success' => true, 'message' => 'Webhook processed successfully', 'code' => 200];
    }

    /**
     * Process Midtrans webhook notification
     * 
     * Midtrans signature: SHA512(order_id + status_code + gross_amount + server_key)
     * Signature is in the payload body as 'signature_key', not in headers
     */
    private function processMidtrans(string $rawPayload, array $data, array $headers): array
    {
        $orderId = $data['order_id'] ?? '';
        if (empty($orderId)) {
            $this->logEvent('invalid', null, $rawPayload, 'Midtrans: Missing order_id');
            return ['success' => false, 'message' => 'Missing order_id', 'code' => 400];
        }

        // Find transaction — Midtrans may send the suffixed order_id (e.g. "INV-123-2")
        // when we retried due to 406 conflict. Try exact match first, then strip suffix.
        $transaction = $this->transactionService->findByOrderId($orderId);
        if (!$transaction && preg_match('/^(.+)-\d+$/', $orderId, $m)) {
            $transaction = $this->transactionService->findByOrderId($m[1]);
        }
        if (!$transaction) {
            $this->logEvent('invalid', null, $rawPayload, "Midtrans: Transaction not found for order_id: {$orderId}");
            return ['success' => false, 'message' => 'Transaction not found', 'code' => 404];
        }

        // Get merchant for logging
        $merchant = $this->merchantRepo->find($transaction['merchant_id']);
        if (!$merchant) {
            $this->logEvent('invalid', null, $rawPayload, "Midtrans: Merchant not found");
            return ['success' => false, 'message' => 'Merchant not found', 'code' => 404];
        }

        // Validate Midtrans signature
        $serverKey = setting('midtrans_server_key', '');
        if (empty($serverKey)) {
            $this->logEvent('invalid', $merchant['id'], $rawPayload, 'Midtrans: Server key not configured');
            return ['success' => false, 'message' => 'Midtrans server key not configured', 'code' => 500];
        }

        $signatureKey = $data['signature_key'] ?? '';
        if (empty($signatureKey)) {
            $this->logEvent('invalid_signature', $merchant['id'], $rawPayload, 'Midtrans: Missing signature_key in payload');
            return ['success' => false, 'message' => 'Missing signature_key', 'code' => 401];
        }

        // Midtrans signature formula: SHA512(order_id + status_code + gross_amount + server_key)
        // NOTE: use the order_id from Midtrans payload (may include our retry suffix),
        // NOT our internal order_id — Midtrans signed with what they received.
        $statusCode = $data['status_code'] ?? '';
        $grossAmount = $data['gross_amount'] ?? '';
        $calculatedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if (!hash_equals($calculatedSignature, $signatureKey)) {
            $this->logEvent('invalid_signature', $merchant['id'], $rawPayload, "Midtrans: Invalid signature for order {$orderId}");
            $this->auditService->log('system', 'system', $merchant['id'], 'webhook_invalid',
                "Midtrans: Invalid signature for order {$orderId}", ['order_id' => $orderId, 'ip' => get_client_ip()]);
            return ['success' => false, 'message' => 'Invalid signature', 'code' => 403];
        }

        // Map Midtrans status to internal status
        $transactionStatus = $data['transaction_status'] ?? '';
        $fraudStatus = $data['fraud_status'] ?? 'accept';
        $status = $this->mapMidtransStatus($transactionStatus, $fraudStatus);

        if (!$status) {
            $this->logEvent('warning', $merchant['id'], $rawPayload, "Midtrans: Could not map status '{$transactionStatus}' for order {$orderId}");
            return ['success' => false, 'message' => 'Could not extract status', 'code' => 400];
        }

        // Update transaction status
        $updated = $this->transactionService->updateStatus($orderId, $status, $data);

        $this->logEvent(
            $updated ? 'success' : 'error', $merchant['id'], $rawPayload,
            $updated ? "Midtrans: Status updated to {$status} for order {$orderId}" : "Midtrans: Failed to update status for {$orderId}",
            [
                'order_id' => $orderId,
                'status' => $status,
                'provider' => 'midtrans',
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus,
                'payment_type' => $data['payment_type'] ?? '',
                'signature_valid' => true,
                'transaction_id' => $transaction['id'],
            ]
        );

        $paymentType = $data['payment_type'] ?? 'unknown';

        $this->auditService->log('system', 'system', $merchant['id'], 'webhook_received',
            "Midtrans webhook for order {$orderId}, status: {$status} (payment: {$paymentType})",
            ['order_id' => $orderId, 'status' => $status, 'provider' => 'midtrans', 'payment_type' => $paymentType, 'ip' => get_client_ip()]);

        return ['success' => true, 'message' => 'Webhook processed successfully', 'code' => 200];
    }

    /**
     * Map Midtrans transaction_status to internal status
     */
    private function mapMidtransStatus(string $transactionStatus, string $fraudStatus = 'accept'): ?string
    {
        if ($fraudStatus === 'deny') {
            return 'FAILED';
        }

        return match($transactionStatus) {
            'capture' => ($fraudStatus === 'accept') ? 'PAID' : 'PENDING',
            'settlement' => 'PAID',
            'pending' => 'PENDING',
            'deny', 'cancel' => 'FAILED',
            'expire' => 'EXPIRED',
            'refund', 'partial_refund' => 'REFUNDED',
            default => null,
        };
    }

    /**
     * Log webhook event
     */
    private function logEvent(string $status, ?string $merchantId, string $payload, string $message, array $meta = []): void
    {
        $event = [
            'id' => generate_uuid(),
            'merchant_id' => $merchantId,
            'status' => $status,
            'payload' => $payload,
            'message' => $message,
            'ip' => get_client_ip(),
            'user_agent' => get_user_agent(),
            'metadata' => $meta,
            'created_at' => now(),
        ];
        $this->webhookRepo->create($event);
    }

    /**
     * Get all webhook events (admin)
     */
    public function getAll(array $filters = []): array
    {
        return $this->webhookRepo->findAll($filters);
    }

    /**
     * Get webhook events by merchant
     */
    public function getByMerchant(string $merchantId): array
    {
        return $this->webhookRepo->findByMerchant($merchantId);
    }
}
