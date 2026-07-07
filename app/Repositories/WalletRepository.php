<?php
/**
 * Wallet Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class WalletRepository extends BaseRepository
{
    private string $ledgerFile;

    public function __construct()
    {
        parent::__construct('wallets.json');
        $this->ledgerFile = $this->storageDir . '/wallet_ledger.json';
        if (!file_exists($this->ledgerFile)) {
            file_put_contents($this->ledgerFile, '[]', LOCK_EX);
        }
    }

    /**
     * Find wallet by merchant ID
     */
    public function findByMerchant(string $merchantId): ?array
    {
        $records = $this->readAll();
        foreach ($records as $record) {
            if (($record['merchant_id'] ?? '') === $merchantId) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Add ledger entry
     */
    public function addLedgerEntry(array $entry): bool
    {
        $content = file_get_contents($this->ledgerFile);
        $ledger = json_decode($content, true) ?: [];
        $ledger[] = $entry;
        
        $json = json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->ledgerFile, $json, LOCK_EX) !== false;
    }

    /**
     * Get ledger entries for merchant
     */
    public function getLedger(string $merchantId, int $limit = 50): array
    {
        $content = file_get_contents($this->ledgerFile);
        $ledger = json_decode($content, true) ?: [];
        
        $filtered = array_values(array_filter($ledger, fn($e) => ($e['merchant_id'] ?? '') === $merchantId));
        usort($filtered, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        
        return array_slice($filtered, 0, $limit);
    }
}
