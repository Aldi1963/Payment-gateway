<?php
/**
 * In-memory FeeRuleRepository for unit tests.
 *
 * Extends the real repository so it satisfies FeeService's type hint, but
 * deliberately does NOT call parent::__construct() — that avoids opening a
 * database connection. All read methods used by the fee engine are overridden
 * to serve rules from arrays supplied by the test.
 */

require_once dirname(__DIR__, 2) . '/app/Repositories/FeeRuleRepository.php';

class FakeFeeRuleRepository extends FeeRuleRepository
{
    /** @var array<string, array<int, array>> keyed by rule_type */
    private array $globalRules;

    /** @var array<string, array<string, array<int, array>>> keyed by merchantId then rule_type */
    private array $merchantRules;

    /**
     * @param array<string, array<int, array>> $globalRules   e.g. ['transaction' => [ ...rules ]]
     * @param array<string, array<string, array<int, array>>> $merchantRules e.g. ['m1' => ['transaction' => [...]]]
     */
    public function __construct(array $globalRules = [], array $merchantRules = [])
    {
        // NOTE: parent constructor intentionally not called (no DB connection).
        $this->globalRules   = $globalRules;
        $this->merchantRules = $merchantRules;
    }

    public function getActiveGlobalRules(string $ruleType = 'transaction'): array
    {
        return $this->sortByPriority($this->globalRules[$ruleType] ?? []);
    }

    public function getActiveMerchantRules(string $merchantId, string $ruleType = 'transaction'): array
    {
        return $this->sortByPriority($this->merchantRules[$merchantId][$ruleType] ?? []);
    }

    /** Mirror the real repository: highest priority first. */
    private function sortByPriority(array $rules): array
    {
        usort($rules, fn($a, $b) => ((int)($b['priority'] ?? 0)) <=> ((int)($a['priority'] ?? 0)));
        return $rules;
    }
}

/**
 * Convenience builder for a fee rule row (shape matches what FeeService expects
 * after the repository has decoded JSON columns, i.e. `config` is an array).
 *
 * @param array<string,mixed> $overrides
 * @return array<string,mixed>
 */
function make_fee_rule(array $overrides = []): array
{
    static $seq = 0;
    $seq++;
    return array_merge([
        'id'          => 'rule-' . $seq,
        'name'        => 'Test rule ' . $seq,
        'rule_type'   => 'transaction',
        'fee_type'    => 'flat',
        'min_amount'  => 0,
        'max_amount'  => 0,
        'config'      => [],
        'merchant_id' => null,
        'priority'    => 0,
        'status'      => 'active',
    ], $overrides);
}
