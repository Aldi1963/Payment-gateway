<?php
/**
 * Settlement Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class SettlementRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('settlements.json');
    }

    /**
     * Find settlements by merchant
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
}
