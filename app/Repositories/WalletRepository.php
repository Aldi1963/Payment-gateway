<?php
/**
 * Wallet Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class WalletRepository extends BaseRepository
{
    protected array $jsonColumns = [];

    public function __construct()
    {
        parent::__construct('wallets');
    }

    /**
     * Find wallet by merchant ID
     */
    public function findByMerchant(string $merchantId): ?array
    {
        return $this->fetchOne("SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid LIMIT 1", [
            'mid' => $merchantId
        ]);
    }

    /**
     * Add ledger entry
     */
    public function addLedgerEntry(array $entry): bool
    {
        $columns = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($entry)));
        $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($entry)));
        $sql = "INSERT INTO `wallet_ledger` ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($entry);
    }

    /**
     * Get ledger entries for merchant
     */
    public function getLedger(string $merchantId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `wallet_ledger` WHERE `merchant_id` = :mid ORDER BY `created_at` DESC LIMIT " . (int)$limit
        );
        $stmt->execute(['mid' => $merchantId]);
        return $stmt->fetchAll();
    }
}
