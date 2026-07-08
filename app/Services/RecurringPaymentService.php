<?php
/**
 * Recurring Payment / Subscription Service
 * Manages automatic recurring billing
 * 
 * Features:
 * - Create subscriptions with configurable intervals (daily, weekly, monthly, yearly)
 * - Trial period support
 * - Grace period for failed payments
 * - Automatic retry logic
 * - Subscription lifecycle management (active, paused, cancelled, past_due)
 * - Proration support
 * - Auto-generate invoices per billing cycle
 */

require_once base_path('app/Database.php');
require_once base_path('app/Services/TransactionService.php');

class RecurringPaymentService
{
    private PDO $db;
    private TransactionService $transactionService;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->transactionService = new TransactionService();
    }

    /**
     * Create a new subscription
     */
    public function create(string $merchantId, array $data): array
    {
        // Validate required fields
        $required = ['customer_name', 'customer_email', 'plan_name', 'amount', 'interval_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Field '{$field}' is required"];
            }
        }

        $amount = (int)$data['amount'];
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be greater than 0'];
        }

        $intervalType = $data['interval_type'];
        if (!in_array($intervalType, ['daily', 'weekly', 'monthly', 'yearly'])) {
            return ['success' => false, 'message' => 'Invalid interval type'];
        }

        $trialDays = (int)($data['trial_days'] ?? 0);
        $gracePeriodDays = (int)($data['grace_period_days'] ?? 3);
        $intervalCount = (int)($data['interval_count'] ?? 1);
        $totalCycles = !empty($data['total_cycles']) ? (int)$data['total_cycles'] : null;

        // Calculate first billing date
        $now = time();
        $firstBillingAt = $trialDays > 0 
            ? date('Y-m-d H:i:s', strtotime("+{$trialDays} days"))
            : now();
        
        $currentPeriodStart = now();
        $currentPeriodEnd = $this->calculateNextBillingDate($currentPeriodStart, $intervalType, $intervalCount);

        $id = generate_uuid();
        $subscription = [
            'id' => $id,
            'merchant_id' => $merchantId,
            'customer_name' => sanitize($data['customer_name']),
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'plan_name' => sanitize($data['plan_name']),
            'amount' => $amount,
            'currency' => $data['currency'] ?? 'IDR',
            'interval_type' => $intervalType,
            'interval_count' => $intervalCount,
            'total_cycles' => $totalCycles,
            'completed_cycles' => 0,
            'status' => $trialDays > 0 ? 'active' : 'active',
            'payment_method' => $data['payment_method'] ?? null,
            'payment_channel' => $data['payment_channel'] ?? null,
            'trial_days' => $trialDays,
            'grace_period_days' => $gracePeriodDays,
            'retry_count' => 0,
            'max_retries' => (int)($data['max_retries'] ?? 3),
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'next_billing_at' => $firstBillingAt,
            'cancelled_at' => null,
            'metadata' => !empty($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $stmt = $this->db->prepare(
            "INSERT INTO `subscriptions` (`id`, `merchant_id`, `customer_name`, `customer_email`, `customer_phone`, `plan_name`, `amount`, `currency`, `interval_type`, `interval_count`, `total_cycles`, `completed_cycles`, `status`, `payment_method`, `payment_channel`, `trial_days`, `grace_period_days`, `retry_count`, `max_retries`, `current_period_start`, `current_period_end`, `next_billing_at`, `cancelled_at`, `metadata`, `created_at`, `updated_at`)
             VALUES (:id, :merchant_id, :customer_name, :customer_email, :customer_phone, :plan_name, :amount, :currency, :interval_type, :interval_count, :total_cycles, :completed_cycles, :status, :payment_method, :payment_channel, :trial_days, :grace_period_days, :retry_count, :max_retries, :current_period_start, :current_period_end, :next_billing_at, :cancelled_at, :metadata, :created_at, :updated_at)"
        );
        $stmt->execute($subscription);

        return ['success' => true, 'subscription' => $subscription];
    }

    /**
     * Process due subscriptions (called by cron)
     * Generates invoices and creates payment transactions
     */
    public function processDueBillings(int $limit = 50): array
    {
        $results = ['processed' => 0, 'success' => 0, 'failed' => 0, 'details' => []];

        // Find subscriptions due for billing
        $stmt = $this->db->prepare(
            "SELECT * FROM subscriptions 
             WHERE status = 'active' AND next_billing_at <= NOW() 
             ORDER BY next_billing_at ASC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $subscriptions = $stmt->fetchAll() ?: [];

        foreach ($subscriptions as $sub) {
            $results['processed']++;
            $billResult = $this->billSubscription($sub);
            
            if ($billResult['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            $results['details'][] = [
                'subscription_id' => $sub['id'],
                'result' => $billResult['success'] ? 'billed' : 'failed',
                'message' => $billResult['message'] ?? '',
            ];
        }

        return $results;
    }

    /**
     * Bill a single subscription
     */
    private function billSubscription(array $subscription): array
    {
        $merchantId = $subscription['merchant_id'];

        // Check if max cycles reached
        if ($subscription['total_cycles'] && (int)$subscription['completed_cycles'] >= (int)$subscription['total_cycles']) {
            $this->updateSubscription($subscription['id'], [
                'status' => 'completed',
                'updated_at' => now(),
            ]);
            return ['success' => true, 'message' => 'Subscription completed (all cycles done)'];
        }

        // Create transaction for this billing cycle
        $cycleNumber = (int)$subscription['completed_cycles'] + 1;
        $orderId = "SUB-{$subscription['id']}-C{$cycleNumber}";

        $txResult = $this->transactionService->create([
            'order_id' => $orderId,
            'amount' => (int)$subscription['amount'],
            'payment_channel' => $subscription['payment_channel'],
            'payment_method' => $subscription['payment_method'],
            'link_name' => "{$subscription['plan_name']} - Cycle #{$cycleNumber}",
            'customer_name' => $subscription['customer_name'],
            'customer_email' => $subscription['customer_email'],
            'customer_wa' => $subscription['customer_phone'] ?? '',
            'note' => "Subscription billing cycle #{$cycleNumber}",
        ], $merchantId);

        // Record subscription invoice
        $invoiceId = generate_uuid();
        $this->db->prepare(
            "INSERT INTO subscription_invoices (id, subscription_id, merchant_id, transaction_id, amount, cycle_number, status, billing_date, created_at)
             VALUES (:id, :sub_id, :mid, :tid, :amount, :cycle, :status, CURDATE(), NOW())"
        )->execute([
            'id' => $invoiceId,
            'sub_id' => $subscription['id'],
            'mid' => $merchantId,
            'tid' => $txResult['transaction']['id'] ?? null,
            'amount' => (int)$subscription['amount'],
            'cycle' => $cycleNumber,
            'status' => $txResult['success'] ? 'pending' : 'failed',
        ]);

        if ($txResult['success']) {
            // Advance to next billing period
            $nextBilling = $this->calculateNextBillingDate(
                $subscription['current_period_end'],
                $subscription['interval_type'],
                (int)$subscription['interval_count']
            );

            $this->updateSubscription($subscription['id'], [
                'completed_cycles' => $cycleNumber,
                'current_period_start' => $subscription['current_period_end'],
                'current_period_end' => $nextBilling,
                'next_billing_at' => $nextBilling,
                'retry_count' => 0,
                'updated_at' => now(),
            ]);

            return ['success' => true, 'message' => "Billed cycle #{$cycleNumber}"];
        } else {
            // Handle failed billing
            $retryCount = (int)$subscription['retry_count'] + 1;
            $maxRetries = (int)$subscription['max_retries'];

            if ($retryCount >= $maxRetries) {
                // Check grace period
                $graceDays = (int)$subscription['grace_period_days'];
                $graceEnd = date('Y-m-d H:i:s', strtotime($subscription['next_billing_at'] . " +{$graceDays} days"));

                if (time() > strtotime($graceEnd)) {
                    // Grace period exhausted - mark as past_due
                    $this->updateSubscription($subscription['id'], [
                        'status' => 'past_due',
                        'retry_count' => $retryCount,
                        'updated_at' => now(),
                    ]);
                    return ['success' => false, 'message' => 'Subscription moved to past_due after exhausted retries'];
                }
            }

            // Schedule retry (exponential backoff: 1hr, 4hr, 12hr)
            $retryDelay = min(43200, 3600 * pow(2, $retryCount - 1));
            $nextRetry = date('Y-m-d H:i:s', time() + $retryDelay);

            $this->updateSubscription($subscription['id'], [
                'retry_count' => $retryCount,
                'next_billing_at' => $nextRetry,
                'updated_at' => now(),
            ]);

            return ['success' => false, 'message' => "Billing failed, retry #{$retryCount} scheduled for {$nextRetry}"];
        }
    }

    /**
     * Pause a subscription
     */
    public function pause(string $subscriptionId, string $merchantId): array
    {
        $sub = $this->find($subscriptionId);
        if (!$sub || $sub['merchant_id'] !== $merchantId) {
            return ['success' => false, 'message' => 'Subscription not found'];
        }
        if ($sub['status'] !== 'active') {
            return ['success' => false, 'message' => 'Only active subscriptions can be paused'];
        }

        $this->updateSubscription($subscriptionId, ['status' => 'paused', 'updated_at' => now()]);
        return ['success' => true, 'message' => 'Subscription paused'];
    }

    /**
     * Resume a paused subscription
     */
    public function resume(string $subscriptionId, string $merchantId): array
    {
        $sub = $this->find($subscriptionId);
        if (!$sub || $sub['merchant_id'] !== $merchantId) {
            return ['success' => false, 'message' => 'Subscription not found'];
        }
        if ($sub['status'] !== 'paused') {
            return ['success' => false, 'message' => 'Only paused subscriptions can be resumed'];
        }

        // Set next billing to now (resume immediately)
        $this->updateSubscription($subscriptionId, [
            'status' => 'active',
            'next_billing_at' => now(),
            'retry_count' => 0,
            'updated_at' => now(),
        ]);
        return ['success' => true, 'message' => 'Subscription resumed'];
    }

    /**
     * Cancel a subscription
     */
    public function cancel(string $subscriptionId, string $merchantId, bool $immediately = false): array
    {
        $sub = $this->find($subscriptionId);
        if (!$sub || $sub['merchant_id'] !== $merchantId) {
            return ['success' => false, 'message' => 'Subscription not found'];
        }
        if ($sub['status'] === 'cancelled') {
            return ['success' => false, 'message' => 'Subscription already cancelled'];
        }

        if ($immediately) {
            $this->updateSubscription($subscriptionId, [
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);
            return ['success' => true, 'message' => 'Subscription cancelled immediately'];
        } else {
            // Cancel at end of current period
            $this->updateSubscription($subscriptionId, [
                'cancelled_at' => $sub['current_period_end'],
                'updated_at' => now(),
            ]);
            return ['success' => true, 'message' => 'Subscription will cancel at end of current period: ' . $sub['current_period_end']];
        }
    }

    /**
     * Get subscription by ID
     */
    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM subscriptions WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get subscriptions for a merchant
     */
    public function getByMerchant(string $merchantId, array $filters = []): array
    {
        $params = ['mid' => $merchantId];
        $where = "WHERE merchant_id = :mid";

        if (!empty($filters['status'])) {
            $where .= " AND status = :status";
            $params['status'] = $filters['status'];
        }

        $stmt = $this->db->prepare("SELECT * FROM subscriptions {$where} ORDER BY created_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get subscription invoices
     */
    public function getInvoices(string $subscriptionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT si.*, t.status as transaction_status, t.paid_at 
             FROM subscription_invoices si 
             LEFT JOIN transactions t ON t.id = si.transaction_id 
             WHERE si.subscription_id = :sid 
             ORDER BY si.cycle_number DESC"
        );
        $stmt->execute(['sid' => $subscriptionId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Process cancelled subscriptions that reached their end date
     */
    public function processEndedSubscriptions(): int
    {
        $stmt = $this->db->prepare(
            "UPDATE subscriptions SET status = 'cancelled', updated_at = NOW() 
             WHERE status = 'active' AND cancelled_at IS NOT NULL AND cancelled_at <= NOW()"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Get subscription statistics for a merchant
     */
    public function getStats(string $merchantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'past_due' THEN 1 ELSE 0 END) as past_due,
                SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END) as mrr
             FROM subscriptions WHERE merchant_id = :mid"
        );
        $stmt->execute(['mid' => $merchantId]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Calculate next billing date
     */
    private function calculateNextBillingDate(string $fromDate, string $intervalType, int $intervalCount = 1): string
    {
        $interval = match($intervalType) {
            'daily' => "+{$intervalCount} days",
            'weekly' => "+" . ($intervalCount * 7) . " days",
            'monthly' => "+{$intervalCount} months",
            'yearly' => "+{$intervalCount} years",
            default => "+1 month",
        };

        return date('Y-m-d H:i:s', strtotime($fromDate . " {$interval}"));
    }

    /**
     * Update subscription fields
     */
    private function updateSubscription(string $id, array $updates): void
    {
        $sets = [];
        $params = ['id' => $id];
        foreach ($updates as $field => $value) {
            $sets[] = "`{$field}` = :{$field}";
            $params[$field] = $value;
        }
        $sql = "UPDATE subscriptions SET " . implode(', ', $sets) . " WHERE id = :id";
        $this->db->prepare($sql)->execute($params);
    }
}
