<?php
/**
 * Merchant Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class MerchantRepository extends BaseRepository
{
    protected array $jsonColumns = [];

    public function __construct()
    {
        parent::__construct('merchants');
    }

    /**
     * Find merchant by API key
     * NOTE: Not timing-safe. Use findByApiKeySecure() for authentication.
     */
    public function findByApiKey(string $apiKey): ?array
    {
        return $this->fetchOne("SELECT * FROM `{$this->table}` WHERE `api_key` = :key LIMIT 1", [
            'key' => $apiKey
        ]);
    }

    /**
     * Find merchant by API key (timing-safe)
     * SECURITY: Uses hash_equals to prevent timing attacks.
     * Iterates ALL records to prevent early-exit timing leak.
     */
    public function findByApiKeySecure(string $apiKey): ?array
    {
        $records = $this->query("SELECT * FROM `{$this->table}`");
        $found = null;

        // Always iterate ALL records (constant-time relative to dataset size)
        foreach ($records as $record) {
            $storedKey = $record['api_key'] ?? '';
            if (!empty($storedKey) && hash_equals($storedKey, $apiKey)) {
                $found = $record;
                // Don't break - continue iterating to prevent timing leak
            }
        }

        return $found;
    }

    /**
     * Find merchant by email (case insensitive)
     */
    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne("SELECT * FROM `{$this->table}` WHERE LOWER(`email`) = LOWER(:email) LIMIT 1", [
            'email' => $email
        ]);
    }

    /**
     * Find merchant/project by slug
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->fetchOne("SELECT * FROM `{$this->table}` WHERE `slug` = :slug LIMIT 1", [
            'slug' => $slug
        ]);
    }

    /**
     * Find projects owned by a specific user
     */
    public function findByOwner(string $ownerId): array
    {
        return $this->query("SELECT * FROM `{$this->table}` WHERE `owner_id` = :oid ORDER BY `created_at` ASC", [
            'oid' => $ownerId
        ]);
    }

    /**
     * Get active merchants
     */
    public function findActive(): array
    {
        return $this->query("SELECT * FROM `{$this->table}` WHERE `status` = :status ORDER BY `created_at` DESC", [
            'status' => 'active'
        ]);
    }

    /**
     * Count merchants by status
     */
    public function countByStatus(): array
    {
        $stmt = $this->db->prepare("SELECT `status`, COUNT(*) as cnt FROM `{$this->table}` GROUP BY `status`");
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $counts = ['pending' => 0, 'active' => 0, 'suspended' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            $status = $row['status'];
            if (isset($counts[$status])) {
                $counts[$status] = (int)$row['cnt'];
            }
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

    /**
     * Get searchable columns for LIKE search
     */
    protected function getSearchableColumns(): array
    {
        return ['business_name', 'owner_name', 'email'];
    }
}
