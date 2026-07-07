<?php
/**
 * Merchant Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class MerchantRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('merchants.json');
    }

    /**
     * Find merchant by API key
     */
    public function findByApiKey(string $apiKey): ?array
    {
        $records = $this->readAll();
        foreach ($records as $record) {
            if (($record['api_key'] ?? '') === $apiKey) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Find merchant by email
     */
    public function findByEmail(string $email): ?array
    {
        $records = $this->readAll();
        foreach ($records as $record) {
            if (strtolower($record['email'] ?? '') === strtolower($email)) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Get active merchants
     */
    public function findActive(): array
    {
        $records = $this->readAll();
        return array_values(array_filter($records, fn($r) => ($r['status'] ?? '') === 'active'));
    }

    /**
     * Count merchants by status
     */
    public function countByStatus(): array
    {
        $records = $this->readAll();
        $counts = ['pending' => 0, 'active' => 0, 'suspended' => 0, 'rejected' => 0];
        foreach ($records as $r) {
            $status = $r['status'] ?? 'pending';
            if (isset($counts[$status])) $counts[$status]++;
        }
        return $counts;
    }

    /**
     * Regenerate API key for a merchant
     */
    public function regenerateApiKey(string $merchantId): ?string
    {
        $newKey = generate_api_key();
        $this->update($merchantId, ['api_key' => $newKey, 'updated_at' => now()]);
        return $newKey;
    }
}
