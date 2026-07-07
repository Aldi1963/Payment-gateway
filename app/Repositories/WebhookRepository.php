<?php
/**
 * Webhook Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class WebhookRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('webhook_events.json');
    }

    /**
     * Find by merchant
     */
    public function findByMerchant(string $merchantId): array
    {
        $records = $this->readAll();
        $filtered = array_values(array_filter($records, fn($r) => ($r['merchant_id'] ?? '') === $merchantId));
        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $filtered;
    }

    /**
     * Find by status
     */
    public function findByStatus(string $status): array
    {
        $records = $this->readAll();
        return array_values(array_filter($records, fn($r) => ($r['status'] ?? '') === $status));
    }

    /**
     * Get recent events
     */
    public function getRecent(int $limit = 50): array
    {
        $records = $this->findAll();
        return array_slice($records, 0, $limit);
    }
}
