<?php
/**
 * Subscription / Recurring Billing Service
 * Manages subscription plans and auto-generates invoices
 * 
 * Features:
 * - Create plans (daily, weekly, monthly, yearly)
 * - Subscribe customers to plans
 * - Auto-generate invoices on billing cycle
 * - Handle subscription lifecycle (active, paused, canceled, expired)
 */

require_once base_path('app/Database.php');

class SubscriptionService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Create subscription plan
     */
    public function createPlan(array $data): array
    {
        $id = generate_uuid();
        $plan = [
            'id' => $id,
            'merchant_id' => $data['merchant_id'],
            'name' => sanitize($data['name'] ?? ''),
            'description' => sanitize($data['description'] ?? ''),
            'amount' => (int)($data['amount'] ?? 0),
            'currency' => 'IDR',
            'interval' => $data['interval'] ?? 'monthly', // daily,weekly,monthly,yearly
            'interval_count' => (int)($data['interval_count'] ?? 1),
            'trial_days' => (int)($data['trial_days'] ?? 0),
            'max_cycles' => (int)($data['max_cycles'] ?? 0), // 0=unlimited
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];


        $cols = implode(',', array_map(fn($k) => "`{$k}`", array_keys($plan)));
        $vals = implode(',', array_map(fn($k) => ":{$k}", array_keys($plan)));
        $this->db->prepare("INSERT INTO `subscription_plans` ({$cols}) VALUES ({$vals})")->execute($plan);
        return ['success' => true, 'plan' => $plan, 'message' => 'Plan berhasil dibuat.'];
    }

    /**
     * Subscribe a customer to a plan
     */
    public function subscribe(array $data): array
    {
        $plan = $this->findPlan($data['plan_id'] ?? '');
        if (!$plan) return ['success' => false, 'message' => 'Plan tidak ditemukan.'];

        $startDate = $data['start_date'] ?? date('Y-m-d');
        $trialEnd = $plan['trial_days'] > 0 ? date('Y-m-d', strtotime("+{$plan['trial_days']} days", strtotime($startDate))) : null;
        $nextBilling = $trialEnd ?: $this->calculateNextBilling($startDate, $plan['interval'], $plan['interval_count']);

        $id = generate_uuid();
        $sub = [
            'id' => $id,
            'merchant_id' => $plan['merchant_id'],
            'plan_id' => $plan['id'],
            'customer_name' => sanitize($data['customer_name'] ?? ''),
            'customer_email' => sanitize($data['customer_email'] ?? ''),
            'customer_phone' => sanitize($data['customer_phone'] ?? ''),
            'status' => $trialEnd ? 'trialing' : 'active',
            'current_cycle' => 0,
            'max_cycles' => $plan['max_cycles'],
            'trial_ends_at' => $trialEnd,
            'next_billing_at' => $nextBilling,
            'last_billed_at' => null,
            'canceled_at' => null,
            'started_at' => $startDate,
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $cols = implode(',', array_map(fn($k) => "`{$k}`", array_keys($sub)));
        $vals = implode(',', array_map(fn($k) => ":{$k}", array_keys($sub)));
        $this->db->prepare("INSERT INTO `subscriptions` ({$cols}) VALUES ({$vals})")->execute($sub);
        return ['success' => true, 'subscription' => $sub, 'message' => 'Berhasil subscribe.'];
    }

    /**
     * Process due subscriptions (called by cron)
     * Generates invoices for subscriptions due for billing
     */
    public function processDueBillings(): int
    {
        $stmt = $this->db->prepare("SELECT s.*, p.name as plan_name, p.amount as plan_amount, p.interval as plan_interval, p.interval_count as plan_interval_count FROM `subscriptions` s JOIN `subscription_plans` p ON s.plan_id = p.id WHERE s.status IN ('active') AND s.next_billing_at <= :now");
        $stmt->execute(['now' => now()]);
        $due = $stmt->fetchAll() ?: [];

        $processed = 0;
        foreach ($due as $sub) {
            // Generate invoice for this cycle
            require_once base_path('app/Services/InvoiceService.php');
            $invoiceService = new InvoiceService();
            $invoiceService->create([
                'merchant_id' => $sub['merchant_id'],
                'customer_name' => $sub['customer_name'],
                'customer_email' => $sub['customer_email'],
                'customer_phone' => $sub['customer_phone'],
                'items' => [['name' => $sub['plan_name'] ?? 'Subscription', 'qty' => 1, 'price' => $sub['plan_amount']]],
                'subtotal' => $sub['plan_amount'],
                'total' => $sub['plan_amount'],
                'notes' => "Auto-generated from subscription #{$sub['id']}",
            ]);

            // Update subscription
            $newCycle = (int)$sub['current_cycle'] + 1;
            $nextBilling = $this->calculateNextBilling(date('Y-m-d'), $sub['plan_interval'], $sub['plan_interval_count']);
            $newStatus = ($sub['max_cycles'] > 0 && $newCycle >= $sub['max_cycles']) ? 'completed' : 'active';

            $this->db->prepare("UPDATE `subscriptions` SET `current_cycle`=:cycle, `next_billing_at`=:next, `last_billed_at`=:now, `status`=:status, `updated_at`=:now2 WHERE `id`=:id")
                ->execute(['cycle' => $newCycle, 'next' => $nextBilling, 'now' => now(), 'status' => $newStatus, 'now2' => now(), 'id' => $sub['id']]);

            $processed++;
        }
        return $processed;
    }

    /**
     * Cancel subscription
     */
    public function cancel(string $subscriptionId): array
    {
        $this->db->prepare("UPDATE `subscriptions` SET `status`='canceled', `canceled_at`=:now, `updated_at`=:now2 WHERE `id`=:id")
            ->execute(['now' => now(), 'now2' => now(), 'id' => $subscriptionId]);
        return ['success' => true, 'message' => 'Subscription dibatalkan.'];
    }

    /**
     * Pause subscription
     */
    public function pause(string $subscriptionId): array
    {
        $this->db->prepare("UPDATE `subscriptions` SET `status`='paused', `updated_at`=:now WHERE `id`=:id")
            ->execute(['now' => now(), 'id' => $subscriptionId]);
        return ['success' => true, 'message' => 'Subscription di-pause.'];
    }

    /**
     * Resume subscription
     */
    public function resume(string $subscriptionId): array
    {
        $this->db->prepare("UPDATE `subscriptions` SET `status`='active', `updated_at`=:now WHERE `id`=:id")
            ->execute(['now' => now(), 'id' => $subscriptionId]);
        return ['success' => true, 'message' => 'Subscription dilanjutkan.'];
    }

    private function calculateNextBilling(string $fromDate, string $interval, int $count): string
    {
        $modifier = match($interval) {
            'daily' => "+{$count} days",
            'weekly' => "+{$count} weeks",
            'monthly' => "+{$count} months",
            'yearly' => "+{$count} years",
            default => "+1 month",
        };
        return date('Y-m-d H:i:s', strtotime($modifier, strtotime($fromDate)));
    }

    public function findPlan(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM `subscription_plans` WHERE `id`=:id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getPlansByMerchant(string $merchantId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `subscription_plans` WHERE `merchant_id`=:mid ORDER BY `created_at` DESC");
        $stmt->execute(['mid' => $merchantId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getSubscriptionsByMerchant(string $merchantId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `subscriptions` WHERE `merchant_id`=:mid ORDER BY `created_at` DESC");
        $stmt->execute(['mid' => $merchantId]);
        return $stmt->fetchAll() ?: [];
    }
}
