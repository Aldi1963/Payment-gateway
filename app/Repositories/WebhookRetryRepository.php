<?php
/**
 * Webhook Retry Repository
 * Stores outbound webhook delivery queue
 */

require_once __DIR__ . '/BaseRepository.php';

class WebhookRetryRepository extends BaseRepository
{
    protected array $jsonColumns = ['payload', 'attempts_log'];

    public function __construct()
    {
        parent::__construct('webhook_retries');
    }

    /**
     * Get webhooks ready to be processed
     * (status=pending AND next_retry_at <= now)
     */
    public function getReadyToProcess(int $limit = 20): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `status` = :status AND `next_retry_at` <= NOW() ORDER BY `next_retry_at` ASC LIMIT " . (int)$limit,
            ['status' => 'pending']
        );
    }

    /**
     * Get by merchant
     */
    public function findByMerchant(string $merchantId, int $limit = 50): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid ORDER BY `created_at` DESC LIMIT " . (int)$limit,
            ['mid' => $merchantId]
        );
    }

    /**
     * Count pending
     */
    public function countPending(): int
    {
        return (int)$this->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = :status",
            ['status' => 'pending']
        );
    }
}
