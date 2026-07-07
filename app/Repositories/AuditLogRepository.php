<?php
/**
 * Audit Log Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class AuditLogRepository extends BaseRepository
{
    protected array $jsonColumns = ['metadata'];

    public function __construct()
    {
        parent::__construct('audit_logs');
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
     * Find by actor
     */
    public function findByActor(string $actorId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `actor_id` = :aid ORDER BY `created_at` DESC",
            ['aid' => $actorId]
        );
    }

    /**
     * Find by action type
     */
    public function findByAction(string $action): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `action` = :action ORDER BY `created_at` DESC",
            ['action' => $action]
        );
    }

    /**
     * Get recent logs
     */
    public function getRecent(int $limit = 100): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` ORDER BY `created_at` DESC LIMIT " . (int)$limit
        );
    }

    /**
     * Get searchable columns for LIKE search
     */
    protected function getSearchableColumns(): array
    {
        return ['action', 'description'];
    }
}
