<?php
/**
 * Fee Calculation Service
 * Handles fee computation with support for flat, percentage, and hybrid models
 */

class FeeService
{
    /**
     * Calculate fee for a transaction
     *
     * @param int|float $amount Transaction amount
     * @param array $merchant Merchant data with fee settings
     * @return int|float Calculated fee amount
     */
    public function calculate(int|float $amount, array $merchant): int
    {
        $feeType = $merchant['fee_type'] ?? setting('default_fee_type', config('app.default_fee_type', 'percentage'));
        $feeValue = (float)($merchant['fee_value'] ?? setting('default_fee_value', config('app.default_fee_value', 0.7)));
        $feeFlat = (float)($merchant['fee_flat'] ?? setting('default_fee_flat', config('app.default_fee_flat', 0)));

        return (int)match($feeType) {
            'flat' => $this->calculateFlat($feeValue),
            'percentage' => $this->calculatePercentage($amount, $feeValue),
            'hybrid' => $this->calculateHybrid($amount, $feeValue, $feeFlat),
            default => 0,
        };
    }

    /**
     * Calculate flat fee
     */
    private function calculateFlat(float $flatAmount): float
    {
        return max(0, $flatAmount);
    }

    /**
     * Calculate percentage fee
     */
    private function calculatePercentage(float $amount, float $percentage): float
    {
        return max(0, round($amount * ($percentage / 100)));
    }

    /**
     * Calculate hybrid fee (percentage + flat)
     */
    private function calculateHybrid(float $amount, float $percentage, float $flatAmount): float
    {
        $percentageFee = $amount * ($percentage / 100);
        return max(0, round($percentageFee + $flatAmount));
    }

    /**
     * Get fee breakdown for display
     */
    public function getBreakdown(int|float $amount, array $merchant): array
    {
        $fee = $this->calculate($amount, $merchant);
        $feeType = $merchant['fee_type'] ?? config('app.default_fee_type', 'percentage');
        $feeValue = $merchant['fee_value'] ?? config('app.default_fee_value', 0.7);
        $feeFlat = $merchant['fee_flat'] ?? config('app.default_fee_flat', 0);

        $description = match($feeType) {
            'flat' => format_currency($feeValue),
            'percentage' => "{$feeValue}%",
            'hybrid' => "{$feeValue}% + " . format_currency($feeFlat),
            default => '-',
        };

        return [
            'fee_type' => $feeType,
            'fee_value' => $feeValue,
            'fee_flat' => $feeFlat,
            'fee_amount' => $fee,
            'net_amount' => $amount - $fee,
            'description' => $description,
        ];
    }
}
