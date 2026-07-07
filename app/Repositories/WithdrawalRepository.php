<?php
/**
 * Withdrawal Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class WithdrawalRepository extends BaseRepository
{
    protected array $jsonColumns = ['fee_snapshot'];

    public function __construct()
    {
        parent::__construct('withdrawals');
    }

    /**
     * Find withdrawals by merchant
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
     * Get pending withdrawals count
     */
    public function countPending(): int
    {
        return (int)$this->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = :status",
            ['status' => 'PENDING']
        );
    }
}
