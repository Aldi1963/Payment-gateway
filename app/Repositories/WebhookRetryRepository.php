<?php
/**
 * Webhook Retry Repository
 * Stores outbound webhook delivery queue
 */

require_once __DIR__ . '/BaseRepository.php';

class WebhookRetryRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('webhook_retries.json');
    }

    /**
     * Get webhooks ready to be processed
     * (status=pending AND next_retry_at <= now)
     */
    public function getReadyToProcess(int $limit = 20): array
    {
        $records = $this->readAll();
        $now = now();
        $filtered = array_filter($records, fn($r) =>
            ($r['status'] ?? '') === 'pending' &&
            (($r['next_retry_at'] ?? '') <= $now)
        );
        usort($filtered, fn($a, $b) => strcmp($a['next_retry_at'] ?? '', $b['next_retry_at'] ?? ''));
        return array_slice(array_values($filtered), 0, $limit);
    }

    /**
     * Get by merchant
     */
    public function findByMerchant(string $merchantId, int $limit = 50): array
    {
        $records = $this->readAll();
        $filtered = array_filter($records, fn($r) => ($r['merchant_id'] ?? '') === $merchantId);
        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return array_slice(array_values($filtered), 0, $limit);
    }

    /**
     * Count pending
     */
    public function countPending(): int
    {
        $records = $this->readAll();
        return count(array_filter($records, fn($r) => ($r['status'] ?? '') === 'pending'));
    }
}
