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
    public function __construct()
    {
        parent::__construct('fee_rules.json');
    }

    /**
     * Get active global rules by type, sorted by priority desc
     */
    public function getActiveGlobalRules(string $ruleType = 'transaction'): array
    {
        $records = $this->readAll();
        $filtered = array_filter($records, fn($r) =>
            ($r['rule_type'] ?? '') === $ruleType &&
            ($r['status'] ?? '') === 'active' &&
            empty($r['merchant_id'])
        );
        usort($filtered, fn($a, $b) => ($b['priority'] ?? 0) - ($a['priority'] ?? 0));
        return array_values($filtered);
    }

    /**
     * Get active merchant-specific rules
     */
    public function getActiveMerchantRules(string $merchantId, string $ruleType = 'transaction'): array
    {
        $records = $this->readAll();
        $filtered = array_filter($records, fn($r) =>
            ($r['rule_type'] ?? '') === $ruleType &&
            ($r['status'] ?? '') === 'active' &&
            ($r['merchant_id'] ?? '') === $merchantId
        );
        usort($filtered, fn($a, $b) => ($b['priority'] ?? 0) - ($a['priority'] ?? 0));
        return array_values($filtered);
    }

    /**
     * Get all rules (admin view)
     */
    public function getAllByType(string $ruleType = 'transaction'): array
    {
        $records = $this->readAll();
        $filtered = array_filter($records, fn($r) => ($r['rule_type'] ?? '') === $ruleType);
        usort($filtered, fn($a, $b) => ($b['priority'] ?? 0) - ($a['priority'] ?? 0));
        return array_values($filtered);
    }

    /**
     * Get all rules for a specific merchant
     */
    public function getMerchantRules(string $merchantId): array
    {
        $records = $this->readAll();
        $filtered = array_filter($records, fn($r) => ($r['merchant_id'] ?? '') === $merchantId);
        usort($filtered, fn($a, $b) => ($b['priority'] ?? 0) - ($a['priority'] ?? 0));
        return array_values($filtered);
    }

    /**
     * Check if merchant has custom fee rules
     */
    public function merchantHasCustomRules(string $merchantId, string $ruleType = 'transaction'): bool
    {
        $rules = $this->getActiveMerchantRules($merchantId, $ruleType);
        return !empty($rules);
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
        $records = $this->readAll();
        $filtered = array_filter($records, function($r) use ($ruleType, $merchantId) {
            if (($r['rule_type'] ?? '') !== $ruleType) return false;
            if ($merchantId === null) return empty($r['merchant_id']);
            return ($r['merchant_id'] ?? '') === $merchantId;
        });
        if (empty($filtered)) return 0;
        return max(array_map(fn($r) => $r['priority'] ?? 0, $filtered));
    }

    /**
     * Get fee statistics
     */
    public function getStats(): array
    {
        $records = $this->readAll();
        return [
            'total_rules' => count($records),
            'active_rules' => count(array_filter($records, fn($r) => ($r['status'] ?? '') === 'active')),
            'transaction_rules' => count(array_filter($records, fn($r) => ($r['rule_type'] ?? '') === 'transaction')),
            'withdrawal_rules' => count(array_filter($records, fn($r) => ($r['rule_type'] ?? '') === 'withdrawal')),
            'settlement_rules' => count(array_filter($records, fn($r) => ($r['rule_type'] ?? '') === 'settlement')),
            'merchant_rules' => count(array_filter($records, fn($r) => !empty($r['merchant_id']))),
            'global_rules' => count(array_filter($records, fn($r) => empty($r['merchant_id']))),
        ];
    }
}
