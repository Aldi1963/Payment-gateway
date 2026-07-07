<?php
/**
 * Transaction Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class TransactionRepository extends BaseRepository
{
    protected array $jsonColumns = ['fee_snapshot'];

    public function __construct()
    {
        parent::__construct('transactions');
    }

    /**
     * Find by order_id
     */
    public function findByOrderId(string $orderId): ?array
    {
        return $this->fetchOne("SELECT * FROM `{$this->table}` WHERE `order_id` = :oid LIMIT 1", [
            'oid' => $orderId
        ]);
    }

    /**
     * Find by order_id and merchant
     */
    public function findByOrderIdAndMerchant(string $orderId, string $merchantId): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `order_id` = :oid AND `merchant_id` = :mid LIMIT 1",
            ['oid' => $orderId, 'mid' => $merchantId]
        );
    }

    /**
     * Find transactions by merchant
     */
    public function findByMerchant(string $merchantId, array $filters = []): array
    {
        $where = ["`merchant_id` = :mid"];
        $params = ['mid' => $merchantId];

        if (!empty($filters['status'])) {
            $where[] = "`status` = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(`order_id` LIKE :search OR `customer_name` LIKE :search OR `customer_email` LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $where) . " ORDER BY `created_at` DESC";
        return $this->query($sql, $params);
    }

    /**
     * Get recent transactions
     */
    public function getRecent(int $limit = 10, ?string $merchantId = null): array
    {
        if ($merchantId) {
            return $this->query(
                "SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid ORDER BY `created_at` DESC LIMIT " . (int)$limit,
                ['mid' => $merchantId]
            );
        }
        return $this->query(
            "SELECT * FROM `{$this->table}` ORDER BY `created_at` DESC LIMIT " . (int)$limit
        );
    }

    /**
     * Get transactions by status
     */
    public function findByStatus(string $status): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `status` = :status ORDER BY `created_at` DESC",
            ['status' => $status]
        );
    }

    /**
     * Get searchable columns for LIKE search
     */
    protected function getSearchableColumns(): array
    {
        return ['order_id', 'customer_name', 'customer_email'];
    }
}
