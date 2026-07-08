<?php
/**
 * User-Merchant Repository
 * Manages the pivot between users and their projects (merchants).
 * One user can own/access multiple projects.
 */

require_once __DIR__ . '/BaseRepository.php';

class UserMerchantRepository extends BaseRepository
{
    protected array $jsonColumns = ['permissions'];

    public function __construct()
    {
        parent::__construct('user_merchants');
    }

    /**
     * Get all projects (merchants) a user has access to, with merchant data joined.
     * Ordered so the default project comes first.
     */
    public function getProjectsForUser(string $userId): array
    {
        return $this->query(
            "SELECT m.*, um.role AS access_role, um.is_default, um.id AS pivot_id
             FROM `user_merchants` um
             JOIN `merchants` m ON m.`id` = um.`merchant_id`
             WHERE um.`user_id` = :uid
             ORDER BY um.`is_default` DESC, m.`created_at` ASC",
            ['uid' => $userId]
        );
    }

    /**
     * Get a single link row by user + merchant.
     */
    public function findLink(string $userId, string $merchantId): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `user_id` = :uid AND `merchant_id` = :mid LIMIT 1",
            ['uid' => $userId, 'mid' => $merchantId]
        );
    }

    /**
     * Check whether a user has access to a given project.
     */
    public function userHasAccess(string $userId, string $merchantId): bool
    {
        $count = $this->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE `user_id` = :uid AND `merchant_id` = :mid",
            ['uid' => $userId, 'mid' => $merchantId]
        );
        return (int)$count > 0;
    }

    /**
     * Count projects owned by a user.
     */
    public function countForUser(string $userId): int
    {
        return (int)$this->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE `user_id` = :uid",
            ['uid' => $userId]
        );
    }

    /**
     * Link a user to a merchant/project.
     */
    public function link(string $userId, string $merchantId, string $role = 'owner', bool $isDefault = false): bool
    {
        return $this->create([
            'id' => generate_uuid(),
            'user_id' => $userId,
            'merchant_id' => $merchantId,
            'role' => $role,
            'is_default' => $isDefault ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Mark a project as the user's default (unset others).
     */
    public function setDefault(string $userId, string $merchantId): void
    {
        // Clear existing defaults
        $this->execute(
            "UPDATE `{$this->table}` SET `is_default` = 0, `updated_at` = :now WHERE `user_id` = :uid",
            ['now' => now(), 'uid' => $userId]
        );
        // Set new default
        $this->execute(
            "UPDATE `{$this->table}` SET `is_default` = 1, `updated_at` = :now WHERE `user_id` = :uid AND `merchant_id` = :mid",
            ['now' => now(), 'uid' => $userId, 'mid' => $merchantId]
        );
    }

    /**
     * Get the default project id for a user (falls back to first project).
     */
    public function getDefaultMerchantId(string $userId): ?string
    {
        $row = $this->fetchOne(
            "SELECT `merchant_id` FROM `{$this->table}`
             WHERE `user_id` = :uid
             ORDER BY `is_default` DESC, `created_at` ASC LIMIT 1",
            ['uid' => $userId]
        );
        return $row['merchant_id'] ?? null;
    }

    /**
     * Get all owners/users of a given merchant (for admin views).
     */
    public function getUsersForMerchant(string $merchantId): array
    {
        return $this->query(
            "SELECT u.*, um.role AS access_role
             FROM `user_merchants` um
             JOIN `users` u ON u.`id` = um.`user_id`
             WHERE um.`merchant_id` = :mid",
            ['mid' => $merchantId]
        );
    }

    /**
     * Remove a user's access to a project.
     */
    public function unlink(string $userId, string $merchantId): bool
    {
        return $this->execute(
            "DELETE FROM `{$this->table}` WHERE `user_id` = :uid AND `merchant_id` = :mid",
            ['uid' => $userId, 'mid' => $merchantId]
        );
    }
}
