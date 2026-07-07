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
    public function __construct()
    {
        parent::__construct('config_changes.json');
    }

    /**
     * Find pending changes for a merchant
     */
    public function findPendingByMerchant(string $merchantId): array
    {
        $records = $this->readAll();
        $filtered = array_values(array_filter($records, fn($r) =>
            ($r['merchant_id'] ?? '') === $merchantId && ($r['status'] ?? '') === 'pending'
        ));
        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $filtered;
    }

    /**
     * Find all changes for a merchant (history)
     */
    public function findByMerchant(string $merchantId): array
    {
        $records = $this->readAll();
        $filtered = array_values(array_filter($records, fn($r) =>
            ($r['merchant_id'] ?? '') === $merchantId
        ));
        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $filtered;
    }

    /**
     * Find all pending changes (for admin review)
     */
    public function findAllPending(): array
    {
        $records = $this->readAll();
        $filtered = array_values(array_filter($records, fn($r) =>
            ($r['status'] ?? '') === 'pending'
        ));
        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $filtered;
    }

    /**
     * Check if merchant has a pending change for a specific field
     */
    public function hasPendingForField(string $merchantId, string $changeType): bool
    {
        $records = $this->readAll();
        foreach ($records as $r) {
            if (($r['merchant_id'] ?? '') === $merchantId &&
                ($r['change_type'] ?? '') === $changeType &&
                ($r['status'] ?? '') === 'pending') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get version history for a specific field of a merchant
     */
    public function getVersionHistory(string $merchantId, string $changeType): array
    {
        $records = $this->readAll();
        $filtered = array_values(array_filter($records, fn($r) =>
            ($r['merchant_id'] ?? '') === $merchantId &&
            ($r['change_type'] ?? '') === $changeType &&
            in_array($r['status'] ?? '', ['approved', 'rolled_back'])
        ));
        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $filtered;
    }

    /**
     * Count pending changes
     */
    public function countPending(): int
    {
        return count($this->findAllPending());
    }
}
