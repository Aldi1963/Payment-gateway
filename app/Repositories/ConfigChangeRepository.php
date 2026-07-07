<?php
/**
 * Config Change Repository
 * Stores merchant configuration change requests with versioning
 *
 * Statuses: pending, approved, rejected, canceled, rolled_back
 */

require_once __DIR__ . '/BaseRepository.php';

class ConfigChangeRepository extends BaseRepository
{
    protected array $jsonColumns = [];

    public function __construct()
    {
        parent::__construct('config_changes');
    }

    /**
     * Find pending changes for a merchant
     */
    public function findPendingByMerchant(string $merchantId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid AND `status` = :status ORDER BY `created_at` DESC",
            ['mid' => $merchantId, 'status' => 'pending']
        );
    }

    /**
     * Find all changes for a merchant (history)
     */
    public function findByMerchant(string $merchantId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid ORDER BY `created_at` DESC",
            ['mid' => $merchantId]
        );
    }

    /**
     * Find all pending changes (for admin review)
     */
    public function findAllPending(): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `status` = :status ORDER BY `created_at` DESC",
            ['status' => 'pending']
        );
    }

    /**
     * Check if merchant has a pending change for a specific field
     */
    public function hasPendingForField(string $merchantId, string $changeType): bool
    {
        $count = $this->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE `merchant_id` = :mid AND `change_type` = :ct AND `status` = :status",
            ['mid' => $merchantId, 'ct' => $changeType, 'status' => 'pending']
        );
        return (int)$count > 0;
    }

    /**
     * Get version history for a specific field of a merchant
     */
    public function getVersionHistory(string $merchantId, string $changeType): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid AND `change_type` = :ct AND `status` IN ('approved', 'rolled_back') ORDER BY `created_at` DESC",
            ['mid' => $merchantId, 'ct' => $changeType]
        );
    }

    /**
     * Count pending changes
     */
    public function countPending(): int
    {
        return (int)$this->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = :status",
            ['status' => 'pending']
        );
    }
}
