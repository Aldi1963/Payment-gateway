<?php
/**
 * Webhook Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class WebhookRepository extends BaseRepository
{
    protected array $jsonColumns = ['metadata'];

    public function __construct()
    {
        parent::__construct('webhook_events');
    }

    /**
     * Find by merchant
     */
    public function findByMerchant(string $merchantId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid ORDER BY `created_at` DESC",
            ['mid' => $merchantId]
        );
    }

    /**
     * Find by status
     */
    public function findByStatus(string $status): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `status` = :status ORDER BY `created_at` DESC",
            ['status' => $status]
        );
    }

    /**
     * Get recent events
     */
    public function getRecent(int $limit = 50): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` ORDER BY `created_at` DESC LIMIT " . (int)$limit
        );
    }
}
