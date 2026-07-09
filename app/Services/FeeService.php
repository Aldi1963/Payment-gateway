<?php
/**
 * Fee Engine Service
 * 
 * Fully configurable fee calculation without code changes.
 * Supports: Flat, Percentage, Random, Hybrid, Tier
 * Priority: Merchant Custom > Global Rule > Default
 * 
 * Fee is calculated at transaction time and stored as snapshot.
 */

require_once base_path('app/Repositories/FeeRuleRepository.php');

class FeeService
{
    private FeeRuleRepository $ruleRepo;

    public function __construct()
    {
        $this->ruleRepo = new FeeRuleRepository();
    }

    // =====================================================
    // MAIN CALCULATION METHODS
    // =====================================================

    /**
     * Calculate transaction fee
     * Priority: Merchant Rules > Global Rules > Default
     * 
     * @return array ['fee' => int, 'rule_id' => string|null, 'fee_type' => string, 'snapshot' => array]
     */
    public function calculateTransaction(int $amount, string $merchantId): array
    {
        // 1. Check merchant custom rules first
        $merchantRules = $this->ruleRepo->getActiveMerchantRules($merchantId, 'transaction');
        if (!empty($merchantRules)) {
            $result = $this->matchAndCalculate($amount, $merchantRules);
            if ($result !== null) {
                return $result;
            }
        }

        // 2. Check global rules
        $globalRules = $this->ruleRepo->getActiveGlobalRules('transaction');
        if (!empty($globalRules)) {
            $result = $this->matchAndCalculate($amount, $globalRules);
            if ($result !== null) {
                return $result;
            }
        }

        // 3. Fallback to default fee from settings
        return $this->calculateDefault($amount, $merchantId);
    }

    /**
     * Calculate withdrawal fee
     */
    public function calculateWithdrawal(int $amount, string $merchantId): array
    {
        // Merchant rules
        $merchantRules = $this->ruleRepo->getActiveMerchantRules($merchantId, 'withdrawal');
        if (!empty($merchantRules)) {
            $result = $this->matchAndCalculate($amount, $merchantRules);
            if ($result !== null) return $result;
        }

        // Global rules
        $globalRules = $this->ruleRepo->getActiveGlobalRules('withdrawal');
        if (!empty($globalRules)) {
            $result = $this->matchAndCalculate($amount, $globalRules);
            if ($result !== null) return $result;
        }

        // Default: use setting
        $feeType = setting('withdrawal_fee_type', 'none');
        $feeValue = (float)setting('withdrawal_fee_value', 0);
        if ($feeType === 'none' || $feeValue <= 0) {
            return ['fee' => 0, 'rule_id' => null, 'fee_type' => 'none', 'snapshot' => ['type' => 'none']];
        }

        $fee = match($feeType) {
            'flat' => (int)$feeValue,
            'percentage' => (int)round($amount * $feeValue / 100),
            'hybrid' => (int)round($amount * $feeValue / 100) + (int)setting('withdrawal_fee_flat', 0),
            default => 0,
        };

        return [
            'fee' => max(0, $fee),
            'rule_id' => null,
            'fee_type' => $feeType,
            'snapshot' => ['type' => $feeType, 'value' => $feeValue, 'source' => 'default_setting'],
        ];
    }

    /**
     * Calculate settlement fee
     */
    public function calculateSettlement(int $amount, string $merchantId): array
    {
        $merchantRules = $this->ruleRepo->getActiveMerchantRules($merchantId, 'settlement');
        if (!empty($merchantRules)) {
            $result = $this->matchAndCalculate($amount, $merchantRules);
            if ($result !== null) return $result;
        }

        $globalRules = $this->ruleRepo->getActiveGlobalRules('settlement');
        if (!empty($globalRules)) {
            $result = $this->matchAndCalculate($amount, $globalRules);
            if ($result !== null) return $result;
        }

        // Default: free
        return ['fee' => 0, 'rule_id' => null, 'fee_type' => 'free', 'snapshot' => ['type' => 'free']];
    }

    /**
     * Legacy compatibility: simple calculate method
     * Used by TransactionService (will be updated)
     */
    public function calculate(int|float $amount, array $merchant): int
    {
        $merchantId = $merchant['id'] ?? '';
        $result = $this->calculateTransaction((int)$amount, $merchantId);
        return $result['fee'];
    }

    // =====================================================
    // PER-CHANNEL FEE (biaya layanan per channel) + MIDTRANS PROVIDER FEE
    // =====================================================
    //
    // Total fee for a transaction =
    //     biaya layanan per channel (QRIS / VA / E-Wallet)   [platform / "biaya admin"]
    //   + biaya provider Midtrans per metode (khusus Midtrans)
    //
    // Example VA: provider Midtrans Rp4.000 + biaya channel VA Rp500 = Rp4.500.

    /**
     * Internal Midtrans method codes that have a per-method provider fee.
     */
    public const MIDTRANS_FEE_METHODS = [
        'bca_va', 'bni_va', 'bri_va', 'permata_va', 'cimb_va',
        'mandiri_bill', 'gopay', 'shopeepay', 'qris',
    ];

    /** Valid fee channel groups. */
    public const FEE_CHANNEL_GROUPS = ['qris', 'va', 'ewallet'];

    /**
     * Determine the fee channel group for a transaction.
     * @return string 'qris' | 'va' | 'ewallet' | '' (unknown)
     */
    public static function channelGroupForMethod(?string $channelCode, ?string $publicMethod): string
    {
        $channelCode = strtolower((string) $channelCode);
        if ($channelCode === 'qris') return 'qris';
        if ($channelCode === 'va') return 'va';
        if ($channelCode === 'ewallet') return 'ewallet';
        if ($channelCode === 'midtrans') {
            return match (self::mapMidtransPublicToInternal($publicMethod)) {
                'bca_va', 'bni_va', 'bri_va', 'permata_va', 'cimb_va', 'mandiri_bill' => 'va',
                'gopay', 'shopeepay' => 'ewallet',
                'qris' => 'qris',
                default => '',
            };
        }
        return '';
    }

    /**
     * Platform ("biaya admin") fee for a channel group (qris|va|ewallet).
     * Uses per-channel settings fee_{group}_type/value/flat. When the channel
     * fee is not configured, falls back to the default fee engine
     * (merchant override / global rules / default settings) — backward compatible.
     */
    public function channelPlatformFee(int $amount, string $group, string $merchantId): array
    {
        $group = strtolower($group);
        $type = in_array($group, self::FEE_CHANNEL_GROUPS, true) ? setting("fee_{$group}_type", '') : '';

        if ($type === '') {
            // Not configured for this channel → use the default fee engine
            return $this->calculateTransaction($amount, $merchantId);
        }

        $value = (float) setting("fee_{$group}_value", 0);
        $flat  = (float) setting("fee_{$group}_flat", 0);
        $fee = (int) match ($type) {
            'flat' => max(0, round($value)),
            'percentage' => max(0, round($amount * $value / 100)),
            'hybrid' => max(0, round($amount * $value / 100) + $flat),
            default => 0,
        };

        return [
            'fee' => $fee,
            'rule_id' => null,
            'fee_type' => $type,
            'snapshot' => [
                'type' => $type,
                'value' => $value,
                'flat' => $flat,
                'source' => "channel_fee_{$group}",
            ],
        ];
    }

    /**
     * Midtrans provider fee (Midtrans cost) for a method. Flat + percentage.
     * Returns 0 when not configured.
     *
     * @param string $internalMethod bca_va, gopay, qris, ...
     */
    public function midtransProviderFee(int $amount, string $internalMethod): int
    {
        $m = strtolower(trim($internalMethod));
        if ($m === '' || !in_array($m, self::MIDTRANS_FEE_METHODS, true)) {
            return 0;
        }
        $flat = (float) setting("mtfee_{$m}_prov_flat", 0);
        $pct  = (float) setting("mtfee_{$m}_prov_pct", 0);
        return max(0, (int) round($amount * $pct / 100) + (int) round($flat));
    }

    /**
     * Calculate the total fee for a transaction given its channel/method:
     *   total = per-channel platform fee + Midtrans provider fee (if Midtrans).
     *
     * When the channel/method is unknown (e.g. customer hasn't picked a method
     * yet), falls back to the default fee engine.
     *
     * @param string|null $channelCode  'qris' | 'midtrans' | 'va' | 'ewallet' | null
     * @param string|null $publicMethod public method code (BCAVA, GOPAY, MTQRIS, ...) or null
     */
    public function calculateForContext(int $amount, string $merchantId, ?string $channelCode, ?string $publicMethod): array
    {
        $group = self::channelGroupForMethod($channelCode, $publicMethod);
        if ($group === '') {
            return $this->calculateTransaction($amount, $merchantId);
        }

        $platform = $this->channelPlatformFee($amount, $group, $merchantId);
        $providerFee = (strtolower((string) $channelCode) === 'midtrans')
            ? $this->midtransProviderFee($amount, self::mapMidtransPublicToInternal($publicMethod) ?? '')
            : 0;
        $total = max(0, (int) $platform['fee'] + $providerFee);

        return [
            'fee' => $total,
            'rule_id' => $platform['rule_id'] ?? null,
            'fee_type' => 'channel_' . $group,
            'snapshot' => [
                'type' => 'channel_' . $group,
                'group' => $group,
                'channel' => $channelCode,
                'method' => $publicMethod,
                'platform_fee' => (int) $platform['fee'],
                'platform_detail' => $platform['snapshot'] ?? [],
                'provider_fee' => $providerFee,
                'source' => 'channel_fee',
                'calculated_at' => now(),
            ],
        ];
    }

    /**
     * Map a public Midtrans method code (BCAVA, GOPAY, MTQRIS, ...) to the
     * internal method key used for per-method fee settings
     * (bca_va, gopay, qris, ...).
     */
    public static function mapMidtransPublicToInternal(?string $code): ?string
    {
        if (!$code) return null;
        return match (strtoupper($code)) {
            'BCAVA' => 'bca_va',
            'BNIVA' => 'bni_va',
            'BRIVA' => 'bri_va',
            'PERMATAVA' => 'permata_va',
            'CIMBVA' => 'cimb_va',
            'MANDIRI' => 'mandiri_bill',
            'GOPAY' => 'gopay',
            'SHOPEEPAY' => 'shopeepay',
            'MTQRIS' => 'qris',
            default => strtolower(str_replace('midtrans_', '', $code)),
        };
    }

    // =====================================================
    // RULE MATCHING ENGINE
    // =====================================================

    /**
     * Match amount against rules and calculate fee
     * Rules are already sorted by priority (highest first)
     */
    private function matchAndCalculate(int $amount, array $rules): ?array
    {
        foreach ($rules as $rule) {
            if ($this->ruleMatchesAmount($rule, $amount)) {
                $fee = $this->calculateByRule($amount, $rule);
                return [
                    'fee' => $fee,
                    'rule_id' => $rule['id'],
                    'fee_type' => $rule['fee_type'],
                    'snapshot' => $this->buildSnapshot($rule, $amount, $fee),
                ];
            }
        }
        return null;
    }

    /**
     * Check if a rule matches the transaction amount
     */
    private function ruleMatchesAmount(array $rule, int $amount): bool
    {
        $min = (int)($rule['min_amount'] ?? 0);
        $max = (int)($rule['max_amount'] ?? 0);

        if ($min > 0 && $amount < $min) return false;
        if ($max > 0 && $amount > $max) return false;

        return true;
    }

    /**
     * Calculate fee based on rule type
     */
    private function calculateByRule(int $amount, array $rule): int
    {
        $config = $rule['config'] ?? [];
        $feeType = $rule['fee_type'] ?? 'flat';

        return match($feeType) {
            'flat' => $this->calcFlat($config),
            'percentage' => $this->calcPercentage($amount, $config),
            'random' => $this->calcRandom($config),
            'hybrid' => $this->calcHybrid($amount, $config),
            'tier' => $this->calcTier($amount, $config),
            default => 0,
        };
    }

    // =====================================================
    // FEE TYPE CALCULATORS
    // =====================================================

    /**
     * Flat fee: fixed amount
     */
    private function calcFlat(array $config): int
    {
        return max(0, (int)($config['amount'] ?? 0));
    }

    /**
     * Percentage fee
     */
    private function calcPercentage(int $amount, array $config): int
    {
        $pct = (float)($config['percentage'] ?? 0);
        $min = (int)($config['min_fee'] ?? 0);
        $max = (int)($config['max_fee'] ?? 0);

        $fee = (int)round($amount * $pct / 100);
        if ($min > 0) $fee = max($fee, $min);
        if ($max > 0) $fee = min($fee, $max);

        return max(0, $fee);
    }

    /**
     * Random fee: cryptographically secure random within range
     * Supports step/kelipatan
     */
    private function calcRandom(array $config): int
    {
        $min = (int)($config['min_fee'] ?? 0);
        $max = (int)($config['max_fee'] ?? $min);
        $step = (int)($config['step'] ?? 1);

        if ($min >= $max) return $min;
        if ($step <= 0) $step = 1;

        // Calculate possible values: min, min+step, min+2*step, ..., <= max
        $range = $max - $min;
        $steps = (int)floor($range / $step);

        if ($steps <= 0) return $min;

        // Use cryptographically secure random
        $randomStep = random_int(0, $steps);
        return $min + ($randomStep * $step);
    }

    /**
     * Hybrid fee: percentage + flat
     */
    private function calcHybrid(int $amount, array $config): int
    {
        $pct = (float)($config['percentage'] ?? 0);
        $flat = (int)($config['flat_amount'] ?? 0);
        $min = (int)($config['min_fee'] ?? 0);
        $max = (int)($config['max_fee'] ?? 0);

        $fee = (int)round($amount * $pct / 100) + $flat;
        if ($min > 0) $fee = max($fee, $min);
        if ($max > 0) $fee = min($fee, $max);

        return max(0, $fee);
    }

    /**
     * Tier fee: different rates for different amount ranges
     * Config contains 'tiers' array sorted by min_amount
     */
    private function calcTier(int $amount, array $config): int
    {
        $tiers = $config['tiers'] ?? [];
        if (empty($tiers)) return 0;

        // Sort tiers by min_amount descending to find the matching tier
        usort($tiers, fn($a, $b) => ($b['min_amount'] ?? 0) - ($a['min_amount'] ?? 0));

        foreach ($tiers as $tier) {
            $tierMin = (int)($tier['min_amount'] ?? 0);
            if ($amount >= $tierMin) {
                // This tier matches
                $tierType = $tier['type'] ?? 'percentage';
                return match($tierType) {
                    'flat' => max(0, (int)($tier['amount'] ?? 0)),
                    'percentage' => max(0, (int)round($amount * (float)($tier['percentage'] ?? 0) / 100)),
                    'random' => $this->calcRandom($tier),
                    default => 0,
                };
            }
        }

        return 0;
    }

    // =====================================================
    // DEFAULT / LEGACY FALLBACK
    // =====================================================

    /**
     * Calculate using legacy default settings (fallback)
     */
    private function calculateDefault(int $amount, string $merchantId): array
    {
        // Check merchant-level override (legacy fields)
        require_once base_path('app/Repositories/MerchantRepository.php');
        $merchantRepo = new MerchantRepository();
        $merchant = $merchantRepo->find($merchantId);

        $feeType = $merchant['fee_type'] ?? setting('default_fee_type', 'percentage');
        $feeValue = (float)($merchant['fee_value'] ?? setting('default_fee_value', 0.7));
        $feeFlat = (float)($merchant['fee_flat'] ?? setting('default_fee_flat', 0));

        $fee = (int)match($feeType) {
            'flat' => max(0, $feeValue),
            'percentage' => max(0, round($amount * $feeValue / 100)),
            'hybrid' => max(0, round($amount * $feeValue / 100) + $feeFlat),
            default => 0,
        };

        return [
            'fee' => $fee,
            'rule_id' => null,
            'fee_type' => $feeType,
            'snapshot' => [
                'type' => $feeType,
                'value' => $feeValue,
                'flat' => $feeFlat,
                'source' => 'default',
            ],
        ];
    }

    // =====================================================
    // SNAPSHOT & HELPERS
    // =====================================================

    /**
     * Build fee snapshot for transaction storage
     */
    private function buildSnapshot(array $rule, int $amount, int $fee): array
    {
        return [
            'rule_id' => $rule['id'],
            'rule_name' => $rule['name'] ?? '',
            'fee_type' => $rule['fee_type'],
            'config' => $rule['config'] ?? [],
            'min_amount' => $rule['min_amount'] ?? 0,
            'max_amount' => $rule['max_amount'] ?? 0,
            'priority' => $rule['priority'] ?? 0,
            'source' => !empty($rule['merchant_id']) ? 'merchant_rule' : 'global_rule',
            'calculated_at' => now(),
        ];
    }

    /**
     * Simulate fee calculation (for admin preview)
     * Returns detailed breakdown for display
     */
    public function simulate(int $amount, string $ruleType = 'transaction', ?string $merchantId = null): array
    {
        if ($ruleType === 'transaction') {
            if ($merchantId) {
                $result = $this->calculateTransaction($amount, $merchantId);
            } else {
                // Global only
                $rules = $this->ruleRepo->getActiveGlobalRules('transaction');
                $result = $this->matchAndCalculate($amount, $rules);
                if (!$result) {
                    $result = ['fee' => 0, 'rule_id' => null, 'fee_type' => 'none', 'snapshot' => []];
                }
            }
        } elseif ($ruleType === 'withdrawal') {
            $result = $this->calculateWithdrawal($amount, $merchantId ?? '');
        } else {
            $result = $this->calculateSettlement($amount, $merchantId ?? '');
        }

        return [
            'amount' => $amount,
            'fee' => $result['fee'],
            'net_amount' => $amount - $result['fee'],
            'fee_type' => $result['fee_type'],
            'rule_id' => $result['rule_id'],
            'fee_percentage' => $amount > 0 ? round(($result['fee'] / $amount) * 100, 4) : 0,
            'snapshot' => $result['snapshot'],
        ];
    }

    /**
     * Simulate multiple amounts for preview
     */
    public function simulateMultiple(array $amounts, string $ruleType = 'transaction', ?string $merchantId = null): array
    {
        return array_map(fn($amt) => $this->simulate((int)$amt, $ruleType, $merchantId), $amounts);
    }

    /**
     * Get fee breakdown for display (legacy compatibility)
     */
    public function getBreakdown(int|float $amount, array $merchant): array
    {
        $result = $this->calculateTransaction((int)$amount, $merchant['id'] ?? '');
        return [
            'fee_type' => $result['fee_type'],
            'fee_amount' => $result['fee'],
            'net_amount' => (int)$amount - $result['fee'],
            'rule_id' => $result['rule_id'],
            'description' => $this->describeRule($result),
        ];
    }

    /**
     * Describe a fee result in human-readable format
     */
    private function describeRule(array $result): string
    {
        $snapshot = $result['snapshot'] ?? [];
        return match($result['fee_type']) {
            'flat' => 'Flat ' . format_currency($result['fee']),
            'percentage' => ($snapshot['config']['percentage'] ?? '?') . '%',
            'random' => 'Random ' . format_currency($snapshot['config']['min_fee'] ?? 0) . ' - ' . format_currency($snapshot['config']['max_fee'] ?? 0),
            'hybrid' => ($snapshot['config']['percentage'] ?? '?') . '% + ' . format_currency($snapshot['config']['flat_amount'] ?? 0),
            'tier' => 'Tier-based',
            default => '-',
        };
    }

    // =====================================================
    // RULE MANAGEMENT (ADMIN)
    // =====================================================

    /**
     * Create a new fee rule
     */
    public function createRule(array $data): array
    {
        $rule = [
            'id' => generate_uuid(),
            'name' => sanitize($data['name'] ?? ''),
            'rule_type' => $data['rule_type'] ?? 'transaction',
            'fee_type' => $data['fee_type'] ?? 'flat',
            'min_amount' => (int)($data['min_amount'] ?? 0),
            'max_amount' => (int)($data['max_amount'] ?? 0),
            'config' => $data['config'] ?? [],
            'merchant_id' => $data['merchant_id'] ?? null,
            'priority' => (int)($data['priority'] ?? ($this->ruleRepo->getMaxPriority($data['rule_type'] ?? 'transaction', $data['merchant_id'] ?? null) + 10)),
            'status' => $data['status'] ?? 'active',
            'description' => sanitize($data['description'] ?? ''),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (empty($rule['name'])) {
            return ['success' => false, 'message' => 'Nama rule wajib diisi.'];
        }

        $this->ruleRepo->create($rule);
        return ['success' => true, 'message' => 'Rule berhasil dibuat.', 'rule' => $rule];
    }

    /**
     * Update a fee rule
     */
    public function updateRule(string $ruleId, array $data): array
    {
        $rule = $this->ruleRepo->find($ruleId);
        if (!$rule) {
            return ['success' => false, 'message' => 'Rule tidak ditemukan.'];
        }

        $updates = [
            'name' => sanitize($data['name'] ?? $rule['name']),
            'fee_type' => $data['fee_type'] ?? $rule['fee_type'],
            'min_amount' => (int)($data['min_amount'] ?? $rule['min_amount']),
            'max_amount' => (int)($data['max_amount'] ?? $rule['max_amount']),
            'config' => $data['config'] ?? $rule['config'],
            'priority' => (int)($data['priority'] ?? $rule['priority']),
            'status' => $data['status'] ?? $rule['status'],
            'description' => sanitize($data['description'] ?? $rule['description'] ?? ''),
            'version' => ($rule['version'] ?? 1) + 1,
            'updated_at' => now(),
        ];

        $this->ruleRepo->update($ruleId, $updates);
        return ['success' => true, 'message' => 'Rule berhasil diperbarui.'];
    }

    /**
     * Delete a fee rule
     */
    public function deleteRule(string $ruleId): array
    {
        $rule = $this->ruleRepo->find($ruleId);
        if (!$rule) {
            return ['success' => false, 'message' => 'Rule tidak ditemukan.'];
        }
        $this->ruleRepo->delete($ruleId);
        return ['success' => true, 'message' => 'Rule berhasil dihapus.'];
    }

    /**
     * Toggle rule status
     */
    public function toggleRule(string $ruleId): array
    {
        $rule = $this->ruleRepo->find($ruleId);
        if (!$rule) return ['success' => false, 'message' => 'Rule tidak ditemukan.'];
        $newStatus = ($rule['status'] === 'active') ? 'inactive' : 'active';
        $this->ruleRepo->update($ruleId, ['status' => $newStatus, 'updated_at' => now()]);
        return ['success' => true, 'message' => "Rule " . ($newStatus === 'active' ? 'diaktifkan' : 'dinonaktifkan') . "."];
    }

    /**
     * Get all rules (admin)
     */
    public function getRules(string $ruleType = 'transaction', ?string $merchantId = null): array
    {
        if ($merchantId) {
            return $this->ruleRepo->getActiveMerchantRules($merchantId, $ruleType);
        }
        return $this->ruleRepo->getAllByType($ruleType);
    }

    /**
     * Get rule by ID
     */
    public function getRule(string $id): ?array
    {
        return $this->ruleRepo->find($id);
    }

    /**
     * Get fee statistics from repository
     */
    public function getStats(): array
    {
        return $this->ruleRepo->getStats();
    }
}
