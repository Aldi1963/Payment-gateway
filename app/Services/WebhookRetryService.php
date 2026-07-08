<?php
/**
 * Webhook Retry Service
 * 
 * Handles outbound webhook delivery to merchant URLs with:
 * - Queue-based delivery
 * - True exponential backoff with jitter (5 attempts: ~1min, ~4min, ~16min, ~1hr, ~4hr)
 * - Status tracking per delivery attempt
 * - Logging of all attempts
 * - Event filtering per merchant
 * - Manual replay support
 * - Webhook event types: payment.status_changed, payment.created, refund.created, withdrawal.updated
 */

require_once base_path('app/Repositories/WebhookRetryRepository.php');

class WebhookRetryService
{
    private WebhookRetryRepository $retryRepo;
    private int $maxRetries;
    private int $backoffBase; // base seconds for exponential calculation

    // Supported webhook event types
    public const EVENT_PAYMENT_STATUS_CHANGED = 'payment.status_changed';
    public const EVENT_PAYMENT_CREATED = 'payment.created';
    public const EVENT_REFUND_CREATED = 'refund.created';
    public const EVENT_WITHDRAWAL_UPDATED = 'withdrawal.updated';
    public const EVENT_TEST = 'test';

    public const ALL_EVENTS = [
        self::EVENT_PAYMENT_STATUS_CHANGED,
        self::EVENT_PAYMENT_CREATED,
        self::EVENT_REFUND_CREATED,
        self::EVENT_WITHDRAWAL_UPDATED,
    ];

    public function __construct()
    {
        $this->retryRepo = new WebhookRetryRepository();
        $this->maxRetries = (int)setting('webhook_max_retries', 5);
        $this->backoffBase = (int)setting('webhook_retry_backoff_base', 60); // 60 seconds base
    }

    /**
     * Calculate exponential backoff delay with jitter
     * Formula: base * 2^attempt + random_jitter (up to 30% of delay)
     * 
     * @param int $attempt Current attempt number (1-based)
     * @return int Delay in seconds
     */
    private function calculateBackoff(int $attempt): int
    {
        $delay = $this->backoffBase * pow(2, $attempt - 1); // 60, 120, 240, 480, 960...
        $jitter = (int)($delay * 0.3 * (mt_rand(0, 100) / 100)); // 0-30% jitter
        return $delay + $jitter;
    }

    /**
     * Queue a webhook for delivery to merchant
     */
    public function queue(string $merchantId, string $webhookUrl, array $payload, string $transactionId = ''): string
    {
        $id = generate_uuid();
        $entry = [
            'id' => $id,
            'merchant_id' => $merchantId,
            'transaction_id' => $transactionId,
            'url' => $webhookUrl,
            'payload' => $payload,
            'status' => 'pending', // pending, delivered, failed, exhausted
            'attempts' => 0,
            'max_retries' => $this->maxRetries,
            'last_attempt_at' => null,
            'next_retry_at' => now(),
            'last_http_code' => null,
            'last_error' => null,
            'delivered_at' => null,
            'attempts_log' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $this->retryRepo->create($entry);
        return $id;
    }

    /**
     * Process pending webhooks (call this via cron or after webhook received)
     * Returns number of webhooks processed
     */
    public function processQueue(int $limit = 20): int
    {
        $pending = $this->retryRepo->getReadyToProcess($limit);
        $processed = 0;

        foreach ($pending as $webhook) {
            $this->deliver($webhook);
            $processed++;
        }

        return $processed;
    }

    /**
     * Attempt to deliver a single webhook
     */
    public function deliver(array $webhook): bool
    {
        $url = $webhook['url'];
        $payload = is_array($webhook['payload']) ? json_encode($webhook['payload']) : $webhook['payload'];
        $attempt = ($webhook['attempts'] ?? 0) + 1;

        // Make HTTP request
        $result = $this->sendHttp($url, $payload);

        // Log attempt
        $attemptLog = [
            'attempt' => $attempt,
            'timestamp' => now(),
            'http_code' => $result['http_code'],
            'success' => $result['success'],
            'error' => $result['error'] ?? null,
            'response_time_ms' => $result['time_ms'] ?? 0,
        ];

        $attemptsLog = $webhook['attempts_log'] ?? [];
        $attemptsLog[] = $attemptLog;

        if ($result['success']) {
            // Delivered successfully
            $this->retryRepo->update($webhook['id'], [
                'status' => 'delivered',
                'attempts' => $attempt,
                'last_attempt_at' => now(),
                'last_http_code' => $result['http_code'],
                'delivered_at' => now(),
                'attempts_log' => $attemptsLog,
                'updated_at' => now(),
            ]);
            return true;
        }

        // Failed - schedule retry or mark exhausted
        if ($attempt >= $this->maxRetries) {
            $this->retryRepo->update($webhook['id'], [
                'status' => 'exhausted',
                'attempts' => $attempt,
                'last_attempt_at' => now(),
                'last_http_code' => $result['http_code'],
                'last_error' => $result['error'] ?? 'Max retries reached',
                'attempts_log' => $attemptsLog,
                'updated_at' => now(),
            ]);
            app_log("Webhook exhausted for {$webhook['merchant_id']}: {$url} after {$attempt} attempts", 'WARNING');
            return false;
        }

        // Schedule next retry with exponential backoff + jitter
        $delay = $this->calculateBackoff($attempt);
        $nextRetry = date('Y-m-d H:i:s', time() + $delay);

        $this->retryRepo->update($webhook['id'], [
            'status' => 'pending',
            'attempts' => $attempt,
            'last_attempt_at' => now(),
            'last_http_code' => $result['http_code'],
            'last_error' => $result['error'] ?? "HTTP {$result['http_code']}",
            'next_retry_at' => $nextRetry,
            'attempts_log' => $attemptsLog,
            'updated_at' => now(),
        ]);

        app_log("Webhook retry #{$attempt} scheduled for {$webhook['merchant_id']}: next at {$nextRetry} (delay: {$delay}s)", 'INFO');

        return false;
    }

    /**
     * Send HTTP POST to webhook URL
     */
    private function sendHttp(string $url, string $payload): array
    {
        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: ClipkuPay-Webhook/1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $timeMs = (int)((microtime(true) - $startTime) * 1000);

        // Success = 2xx response
        $success = ($httpCode >= 200 && $httpCode < 300);

        return [
            'success' => $success,
            'http_code' => $httpCode,
            'error' => $error ?: ($success ? null : "HTTP {$httpCode}"),
            'response' => $response,
            'time_ms' => $timeMs,
        ];
    }

    /**
     * Dispatch webhook to merchant after payment status change
     * This is the main entry point called by WebhookService/TransactionService
     */
    public function dispatch(string $merchantId, string $webhookUrl, array $transaction, string $status, string $eventType = self::EVENT_PAYMENT_STATUS_CHANGED): void
    {
        if (empty($webhookUrl)) return;

        // Check merchant's event filter
        if (!$this->shouldDeliverEvent($merchantId, $eventType)) {
            app_log("Webhook event '{$eventType}' filtered for merchant {$merchantId}", 'DEBUG');
            return;
        }

        // Build outbound webhook payload
        $payload = [
            'event' => $eventType,
            'transaction_id' => $transaction['id'] ?? '',
            'order_id' => $transaction['order_id'] ?? '',
            'status' => $status,
            'amount' => $transaction['amount'] ?? 0,
            'fee' => $transaction['fee'] ?? 0,
            'net_amount' => $transaction['net_amount'] ?? 0,
            'paid_at' => $transaction['paid_at'] ?? null,
            'timestamp' => now(),
        ];

        // Sign the payload
        $merchantRepo = new MerchantRepository();
        $merchant = $merchantRepo->find($merchantId);
        if ($merchant) {
            $jsonPayload = json_encode($payload);
            $signature = hash_hmac('sha256', $jsonPayload, $merchant['api_key']);
            $payload['_signature'] = $signature;
        }

        $this->queue($merchantId, $webhookUrl, $payload, $transaction['id'] ?? '');

        // Try immediate delivery
        $pending = $this->retryRepo->getReadyToProcess(1);
        if (!empty($pending)) {
            $this->deliver($pending[0]);
        }
    }

    /**
     * Check if merchant wants to receive this event type
     * Based on webhook_events_filter in merchants table
     */
    private function shouldDeliverEvent(string $merchantId, string $eventType): bool
    {
        // Test events always delivered
        if ($eventType === self::EVENT_TEST) return true;

        try {
            $merchantRepo = new MerchantRepository();
            $merchant = $merchantRepo->find($merchantId);
            if (!$merchant) return true; // If merchant not found, deliver anyway

            $filterJson = $merchant['webhook_events_filter'] ?? null;
            if (empty($filterJson)) return true; // No filter = deliver all events

            $allowedEvents = json_decode($filterJson, true);
            if (!is_array($allowedEvents) || empty($allowedEvents)) return true;

            return in_array($eventType, $allowedEvents);
        } catch (\Throwable $e) {
            return true; // On error, deliver
        }
    }

    /**
     * Replay a webhook event - re-sends the same payload
     * Used by admin/merchant to re-trigger a failed webhook
     */
    public function replay(string $webhookId): array
    {
        $webhook = $this->retryRepo->find($webhookId);
        if (!$webhook) {
            return ['success' => false, 'message' => 'Webhook delivery not found'];
        }

        // Create a new delivery entry as a replay
        $newId = generate_uuid();
        $entry = [
            'id' => $newId,
            'merchant_id' => $webhook['merchant_id'],
            'transaction_id' => $webhook['transaction_id'] ?? '',
            'url' => $webhook['url'],
            'payload' => $webhook['payload'],
            'status' => 'pending',
            'attempts' => 0,
            'max_retries' => $this->maxRetries,
            'last_attempt_at' => null,
            'next_retry_at' => now(),
            'last_http_code' => null,
            'last_error' => null,
            'delivered_at' => null,
            'attempts_log' => [['note' => "Replayed from webhook {$webhookId}", 'timestamp' => now()]],
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $this->retryRepo->create($entry);

        // Try immediate delivery
        $fresh = $this->retryRepo->find($newId);
        if ($fresh) {
            $result = $this->deliver($fresh);
            return [
                'success' => $result,
                'message' => $result ? 'Webhook replayed and delivered' : 'Webhook replayed, delivery pending',
                'new_webhook_id' => $newId,
            ];
        }

        return ['success' => true, 'message' => 'Webhook queued for replay', 'new_webhook_id' => $newId];
    }

    /**
     * Get delivery history for a merchant
     */
    public function getByMerchant(string $merchantId, int $limit = 50): array
    {
        return $this->retryRepo->findByMerchant($merchantId, $limit);
    }

    /**
     * Get all deliveries (admin)
     */
    public function getAll(int $limit = 100): array
    {
        return $this->retryRepo->findAll();
    }

    /**
     * Get pending count
     */
    public function getPendingCount(): int
    {
        return $this->retryRepo->countPending();
    }

    /**
     * Manually retry a specific webhook
     */
    public function manualRetry(string $webhookId): array
    {
        $webhook = $this->retryRepo->find($webhookId);
        if (!$webhook) return ['success' => false, 'message' => 'Not found'];
        
        $this->retryRepo->update($webhookId, [
            'status' => 'pending',
            'next_retry_at' => now(),
            'updated_at' => now(),
        ]);
        
        $webhook['status'] = 'pending';
        $result = $this->deliver($webhook);
        return ['success' => $result, 'message' => $result ? 'Delivered' : 'Failed, will retry'];
    }

    /**
     * Get webhook delivery statistics
     */
    public function getStats(): array
    {
        try {
            $pdo = Database::getConnection();
            $stats = [];
            
            // Delivery success rate (last 7 days)
            $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM webhook_retries WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY status");
            $stats['by_status'] = $stmt->fetchAll() ?: [];
            
            // Average delivery time
            $stmt = $pdo->query("SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) as avg_seconds FROM webhook_retries WHERE status = 'delivered' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $row = $stmt->fetch();
            $stats['avg_delivery_seconds'] = $row ? round((float)$row['avg_seconds'], 1) : 0;
            
            // Pending count
            $stmt = $pdo->query("SELECT COUNT(*) FROM webhook_retries WHERE status = 'pending'");
            $stats['pending_count'] = (int)$stmt->fetchColumn();
            
            return $stats;
        } catch (\Throwable $e) {
            return ['error' => 'Could not fetch stats'];
        }
    }
}
