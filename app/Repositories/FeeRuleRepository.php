<?php
/**
 * Fee Rule Repository
 * Stores configurable fee rules with versioning
 *
 * Rule types: transaction, withdrawal, settlement
 * Fee types: flat, percentage, random, hybrid, tier
 *
 * Each rule has:
 * - id, name, type (transaction|withdrawal|settlement)
 * - fee_type (flat|percentage|random|hybrid|tier)
 * - min_amount, max_amount (range this rule applies to)
 * - config (type-specific configuration)
 * - merchant_id (null = global, set = per-merchant)
 * - priority (higher = checked first)
 * - status (active|inactive)
 * - version, created_at, updated_at
 */

require_once __DIR__ . '/BaseRepository.php';

class FeeRuleRepository extends BaseRepository
{
    protected array $jsonColumns = ['config'];

    public function __construct()
    {
        parent::__construct('fee_rules');
    }

    /**
     * Get active global rules by type, sorted by priority desc
     */
    public function getActiveGlobalRules(string $ruleType = 'transaction'): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `rule_type` = :rt AND `status` = :status AND `merchant_id` IS NULL ORDER BY `priority` DESC",
            ['rt' => $ruleType, 'status' => 'active']
        );
    }

    /**
     * Get active merchant-specific rules
     */
    public function getActiveMerchantRules(string $merchantId, string $ruleType = 'transaction'): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `rule_type` = :rt AND `status` = :status AND `merchant_id` = :mid ORDER BY `priority` DESC",
            ['rt' => $ruleType, 'status' => 'active', 'mid' => $merchantId]
        );
    }

    /**
     * Get all rules (admin view)
     */
    public function getAllByType(string $ruleType = 'transaction'): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `rule_type` = :rt ORDER BY `priority` DESC",
            ['rt' => $ruleType]
        );
    }

    /**
     * Get all rules for a specific merchant
     */
    public function getMerchantRules(string $merchantId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `merchant_id` = :mid ORDER BY `priority` DESC",
            ['mid' => $merchantId]
        );
    }

    /**
     * Check if merchant has custom fee rules
     */
    public function merchantHasCustomRules(string $merchantId, string $ruleType = 'transaction'): bool
    {
        $count = $this->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE `merchant_id` = :mid AND `rule_type` = :rt AND `status` = :status",
            ['mid' => $merchantId, 'rt' => $ruleType, 'status' => 'active']
        );
        return (int)$count > 0;
    }

    /**
     * Get next version number for a rule
     */
    public function getNextVersion(string $ruleId): int
    {
        $rule = $this->find($ruleId);
        return ($rule['version'] ?? 0) + 1;
    }

    /**
     * Get highest priority number for a type
     */
    public function getMaxPriority(string $ruleType = 'transaction', ?string $merchantId = null): int
    {
        if ($merchantId === null) {
            $result = $this->fetchColumn(
                "SELECT MAX(`priority`) FROM `{$this->table}` WHERE `rule_type` = :rt AND `merchant_id` IS NULL",
                ['rt' => $ruleType]
            );
        } else {
            $result = $this->fetchColumn(
                "SELECT MAX(`priority`) FROM `{$this->table}` WHERE `rule_type` = :rt AND `merchant_id` = :mid",
                ['rt' => $ruleType, 'mid' => $merchantId]
            );
        }
        return (int)($result ?? 0);
    }

    /**
     * Get fee statistics
     */
    public function getStats(): array
    {
        $total = (int)$this->fetchColumn("SELECT COUNT(*) FROM `{$this->table}`");
        $active = (int)$this->fetchColumn("SELECT COUNT(*) FROM `{$this->table}` WHERE `status` = :s", ['s' => 'active']);
        $transaction = (int)$this->fetchColumn("SELECT COUNT(*) FROM `{$this->table}` WHERE `rule_type` = :rt", ['rt' => 'transaction']);
        $withdrawal = (int)$this->fetchColumn("SELECT COUNT(*) FROM `{$this->table}` WHERE `rule_type` = :rt", ['rt' => 'withdrawal']);
        $settlement = (int)$this->fetchColumn("SELECT COUNT(*) FROM `{$this->table}` WHERE `rule_type` = :rt", ['rt' => 'settlement']);
        $merchantRules = (int)$this->fetchColumn("SELECT COUNT(*) FROM `{$this->table}` WHERE `merchant_id` IS NOT NULL");
        $globalRules = (int)$this->fetchColumn("SELECT COUNT(*) FROM `{$this->table}` WHERE `merchant_id` IS NULL");

        return [
            'total_rules' => $total,
            'active_rules' => $active,
            'transaction_rules' => $transaction,
            'withdrawal_rules' => $withdrawal,
            'settlement_rules' => $settlement,
            'merchant_rules' => $merchantRules,
            'global_rules' => $globalRules,
        ];
    }
}
