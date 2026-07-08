<?php
/**
 * Webhook Retry Service
 * 
 * Handles outbound webhook delivery to merchant URLs with:
 * - Queue-based delivery
 * - Exponential backoff retry (3 attempts: 30s, 120s, 600s)
 * - Status tracking per delivery attempt
 * - Logging of all attempts
 */

require_once base_path('app/Repositories/WebhookRetryRepository.php');

class WebhookRetryService
{
    private WebhookRetryRepository $retryRepo;
    private int $maxRetries;
    private array $backoffSeconds; // delay between retries

    public function __construct()
    {
        $this->retryRepo = new WebhookRetryRepository();
        $this->maxRetries = (int)setting('webhook_max_retries', 3);
        // Exponential backoff: 30s, 2min, 10min
        $this->backoffSeconds = [30, 120, 600];
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

        // Schedule next retry with exponential backoff
        $backoffIndex = min($attempt - 1, count($this->backoffSeconds) - 1);
        $delay = $this->backoffSeconds[$backoffIndex];
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
    public function dispatch(string $merchantId, string $webhookUrl, array $transaction, string $status): void
    {
        if (empty($webhookUrl)) return;

        // Build outbound webhook payload
        $payload = [
            'event' => 'payment.status_changed',
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
}
