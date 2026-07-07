<?php
/**
 * Settlement Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class SettlementRepository extends BaseRepository
{
    protected array $jsonColumns = ['transaction_ids'];

    public function __construct()
    {
        parent::__construct('settlements');
    }

    /**
     * Find settlements by merchant
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
}
