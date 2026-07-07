<?php
/**
 * Transaction Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class TransactionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('transactions.json');
    }

    /**
     * Find by order_id
     */
    public function findByOrderId(string $orderId): ?array
    {
        $records = $this->readAll();
        foreach ($records as $record) {
            if (($record['order_id'] ?? '') === $orderId) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Find by order_id and merchant
     */
    public function findByOrderIdAndMerchant(string $orderId, string $merchantId): ?array
    {
        $records = $this->readAll();
        foreach ($records as $record) {
            if (($record['order_id'] ?? '') === $orderId && ($record['merchant_id'] ?? '') === $merchantId) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Find transactions by merchant
     */
    public function findByMerchant(string $merchantId, array $filters = []): array
    {
        $records = $this->readAll();
        $filtered = array_filter($records, function($r) use ($merchantId, $filters) {
            if (($r['merchant_id'] ?? '') !== $merchantId) return false;
            if (!empty($filters['status']) && ($r['status'] ?? '') !== $filters['status']) return false;
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $searchable = strtolower(($r['order_id'] ?? '') . ' ' . ($r['customer_name'] ?? '') . ' ' . ($r['customer_email'] ?? ''));
                if (!str_contains($searchable, $search)) return false;
            }
            return true;
        });

        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return array_values($filtered);
    }

    /**
     * Get recent transactions
     */
    public function getRecent(int $limit = 10, ?string $merchantId = null): array
    {
        $records = $merchantId ? $this->findByMerchant($merchantId) : $this->findAll();
        return array_slice($records, 0, $limit);
    }

    /**
     * Get transactions by status
     */
    public function findByStatus(string $status): array
    {
        $records = $this->readAll();
        $filtered = array_values(array_filter($records, fn($r) => ($r['status'] ?? '') === $status));
        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $filtered;
    }
}
