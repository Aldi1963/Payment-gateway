<?php
/**
 * User Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('users.json');
    }

    /**
     * Find user by email
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
     * Find users by merchant ID
     */
    public function findByMerchant(string $merchantId): array
    {
        $records = $this->readAll();
        return array_values(array_filter($records, fn($r) => ($r['merchant_id'] ?? '') === $merchantId));
    }

    /**
     * Find users by role
     */
    public function findByRole(string $role): array
    {
        $records = $this->readAll();
        return array_values(array_filter($records, fn($r) => ($r['role'] ?? '') === $role));
    }
}
