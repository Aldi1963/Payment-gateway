<?php
/**
 * Webhook Service
 * Handles incoming webhook events
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
     * Process incoming webhook
     */
    public function process(string $rawPayload, array $headers): array
    {
        $signature = $headers['HTTP_X_SIGNATURE'] ?? $headers['X-Signature'] ?? '';
        $data = json_decode($rawPayload, true);

        if (!$data) {
            $this->logEvent('invalid', null, $rawPayload, 'Invalid JSON payload');
            return ['success' => false, 'message' => 'Invalid JSON payload', 'code' => 400];
        }

        // Extract order_id from payload
        $orderId = $this->aldiQris->extractOrderId($data);
        if (!$orderId) {
            $this->logEvent('invalid', null, $rawPayload, 'Missing order_id in payload');
            return ['success' => false, 'message' => 'Missing order_id', 'code' => 400];
        }

        // Find transaction to get merchant
        $transaction = $this->transactionService->findByOrderId($orderId);
        if (!$transaction) {
            $this->logEvent('invalid', null, $rawPayload, "Transaction not found for order_id: {$orderId}");
            return ['success' => false, 'message' => 'Transaction not found', 'code' => 404];
        }

        // Get merchant for logging and reference
        $merchant = $this->merchantRepo->find($transaction['merchant_id']);
        if (!$merchant) {
            $this->logEvent('invalid', null, $rawPayload, "Merchant not found for transaction");
            return ['success' => false, 'message' => 'Merchant not found', 'code' => 404];
        }

        // Validate signature using AldiQRIS provider API key
        // AldiQRIS signs webhooks with the same API key used to create transactions
        // SECURITY: Signature is MANDATORY - reject requests without signature
        $secretKey = setting('aldiqris_api_key', config('gateway.aldiqris.api_key', ''));
        if (empty($secretKey)) {
            // Fallback: if provider key not configured, cannot validate
            $this->logEvent('invalid', $merchant['id'], $rawPayload, 'AldiQRIS API key not configured for webhook validation');
            return ['success' => false, 'message' => 'Webhook validation not configured', 'code' => 500];
        }

        if (empty($signature)) {
            $this->logEvent('invalid_signature', $merchant['id'], $rawPayload, 'Missing webhook signature header');
            $this->auditService->log(
                'system', 'system', $merchant['id'],
                'webhook_invalid',
                "Missing X-Signature header for order {$orderId}",
                ['order_id' => $orderId, 'ip' => get_client_ip()]
            );
            return ['success' => false, 'message' => 'Missing signature', 'code' => 401];
        }

        $isValid = $this->aldiQris->validateWebhookSignature($rawPayload, $signature, $secretKey);
        if (!$isValid) {
            $this->logEvent('invalid_signature', $merchant['id'], $rawPayload, 'Invalid webhook signature');
            $this->auditService->log(
                'system', 'system', $merchant['id'],
                'webhook_invalid',
                "Invalid webhook signature for order {$orderId}",
                ['order_id' => $orderId, 'ip' => get_client_ip()]
            );
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

        // Log webhook event
        $this->logEvent(
            $updated ? 'success' : 'error',
            $merchant['id'],
            $rawPayload,
            $updated ? "Status updated to {$status} for order {$orderId}" : "Failed to update status for {$orderId}",
            [
                'order_id' => $orderId,
                'status' => $status,
                'signature_valid' => true,
                'transaction_id' => $transaction['id'],
            ]
        );

        // Audit log
        $this->auditService->log(
            'system', 'system', $merchant['id'],
            'webhook_received',
            "Webhook received for order {$orderId}, status: {$status}",
            ['order_id' => $orderId, 'status' => $status, 'ip' => get_client_ip()]
        );

        return ['success' => true, 'message' => 'Webhook processed successfully', 'code' => 200];
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
