<?php
/**
 * Audit Log Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class AuditLogRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('audit_logs.json');
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
     * Find by actor
     */
    public function findByActor(string $actorId): array
    {
        $records = $this->readAll();
        $filtered = array_values(array_filter($records, fn($r) => ($r['actor_id'] ?? '') === $actorId));
        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $filtered;
    }

    /**
     * Find by action type
     */
    public function findByAction(string $action): array
    {
        $records = $this->readAll();
        return array_values(array_filter($records, fn($r) => ($r['action'] ?? '') === $action));
    }

    /**
     * Get recent logs
     */
    public function getRecent(int $limit = 100): array
    {
        $records = $this->findAll();
        return array_slice($records, 0, $limit);
    }
}
