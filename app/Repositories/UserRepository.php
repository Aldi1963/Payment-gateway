<?php
/**
 * User Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class UserRepository extends BaseRepository
{
    protected array $jsonColumns = ['permissions'];

    public function __construct()
    {
        parent::__construct('users');
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne("SELECT * FROM `{$this->table}` WHERE `email` = :email LIMIT 1", [
            'email' => $email
        ]);
    }

    /**
     * Find users by merchant ID
     */
    public function findByMerchant(string $merchantId): array
    {
        return $this->query("SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid ORDER BY `created_at` DESC", [
            'mid' => $merchantId
        ]);
    }

    /**
     * Find users by role
     */
    public function findByRole(string $role): array
    {
        return $this->query("SELECT * FROM `{$this->table}` WHERE `role` = :role ORDER BY `created_at` DESC", [
            'role' => $role
        ]);
    }

    /**
     * Find a user (account) by API key.
     * NOTE: Not timing-safe. Use findByApiKeySecure() for authentication.
     */
    public function findByApiKey(string $apiKey): ?array
    {
        return $this->fetchOne("SELECT * FROM `{$this->table}` WHERE `api_key` = :key LIMIT 1", [
            'key' => $apiKey
        ]);
    }

    /**
     * Find a user (account) by API key (timing-safe).
     * SECURITY: Uses hash_equals to prevent timing attacks and iterates ALL
     * candidate records to avoid early-exit timing leaks.
     */
    public function findByApiKeySecure(string $apiKey): ?array
    {
        $records = $this->query("SELECT * FROM `{$this->table}` WHERE `api_key` IS NOT NULL AND `api_key` != ''");
        $found = null;

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
     * Regenerate the account-level API key for a user.
     * Returns the new key.
     */
    public function regenerateApiKey(string $userId): string
    {
        $newKey = generate_api_key();
        $this->update($userId, ['api_key' => $newKey, 'updated_at' => now()]);
        return $newKey;
    }

    /**
     * Get searchable columns for LIKE search
     */
    protected function getSearchableColumns(): array
    {
        return ['name', 'email'];
    }
}
