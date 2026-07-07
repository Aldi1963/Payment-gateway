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
     * Get searchable columns for LIKE search
     */
    protected function getSearchableColumns(): array
    {
        return ['name', 'email'];
    }
}
