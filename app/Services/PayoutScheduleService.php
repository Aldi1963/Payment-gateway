<?php
/**
 * Payout Scheduling Service
 * Automatic settlement & disbursement based on configurable schedules
 * 
 * Modes: T+0 (instant), T+1, T+3, T+7, weekly, monthly, manual
 */

require_once base_path('app/Database.php');

class PayoutScheduleService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Process scheduled payouts (called by cron)
     */
    public function processScheduledPayouts(): int
    {
        $processed = 0;
        $globalSchedule = setting('payout_schedule', 'manual');
        if ($globalSchedule === 'manual') return 0;

        // Get merchants with scheduled payout
        $stmt = $this->db->query("SELECT m.id, m.business_name FROM merchants m WHERE m.status = 'active'");
        $merchants = $stmt->fetchAll() ?: [];

        foreach ($merchants as $merchant) {
            $schedule = $this->getMerchantSchedule($merchant['id']);
            if ($schedule === 'manual') continue;

            $dueDate = $this->calculateDueDate($schedule);
            if ($dueDate > now()) continue; // Not due yet

            // Check available balance
            $walletStmt = $this->db->prepare("SELECT available_balance FROM wallets WHERE merchant_id = :mid");
            $walletStmt->execute(['mid' => $merchant['id']]);
            $wallet = $walletStmt->fetch();
            $balance = (int)($wallet['available_balance'] ?? 0);
            $minPayout = (int)setting('min_auto_payout', 50000);

            if ($balance >= $minPayout) {
                // Auto-create settlement
                $this->createAutoSettlement($merchant['id'], $balance);
                $processed++;
            }
        }
        return $processed;
    }


    /**
     * Get merchant's payout schedule (per-merchant or global)
     */
    public function getMerchantSchedule(string $merchantId): string
    {
        $stmt = $this->db->prepare("SELECT payout_schedule FROM merchants WHERE id = :mid");
        $stmt->execute(['mid' => $merchantId]);
        $row = $stmt->fetch();
        $merchantSchedule = $row['payout_schedule'] ?? '';
        return $merchantSchedule ?: setting('payout_schedule', 'manual');
    }

    /**
     * Set merchant payout schedule
     */
    public function setMerchantSchedule(string $merchantId, string $schedule): bool
    {
        $valid = ['manual', 'instant', 't1', 't3', 't7', 'weekly', 'monthly'];
        if (!in_array($schedule, $valid)) return false;
        $this->db->prepare("UPDATE merchants SET payout_schedule = :schedule, updated_at = :now WHERE id = :mid")
            ->execute(['schedule' => $schedule, 'now' => now(), 'mid' => $merchantId]);
        return true;
    }

    /**
     * Calculate when next payout is due
     */
    private function calculateDueDate(string $schedule): string
    {
        return match($schedule) {
            'instant', 't0' => now(),
            't1' => date('Y-m-d H:i:s', strtotime('-1 day')),
            't3' => date('Y-m-d H:i:s', strtotime('-3 days')),
            't7' => date('Y-m-d H:i:s', strtotime('-7 days')),
            'weekly' => date('Y-m-d', strtotime('last monday')),
            'monthly' => date('Y-m-01'),
            default => '9999-12-31',
        };
    }

    /**
     * Auto-create settlement for scheduled payout
     */
    private function createAutoSettlement(string $merchantId, int $amount): void
    {
        $id = generate_uuid();
        $this->db->prepare("INSERT INTO settlements (id, merchant_id, period, total_transactions, total_gross, total_fee, total_net, status, created_by, note, created_at, updated_at) VALUES (:id, :mid, :period, 0, :gross, 0, :net, 'APPROVED', 'system', 'Auto-scheduled payout', :ca, :ua)")
            ->execute([
                'id' => $id, 'mid' => $merchantId, 'period' => date('Y-m'),
                'gross' => $amount, 'net' => $amount,
                'ca' => now(), 'ua' => now(),
            ]);

        app_log("Auto-payout settlement created for merchant {$merchantId}: " . format_currency($amount), 'INFO');
    }

    /**
     * Get scheduled payouts info for admin
     */
    public function getScheduleOverview(): array
    {
        $stmt = $this->db->query("SELECT m.id, m.business_name, m.payout_schedule, w.available_balance FROM merchants m LEFT JOIN wallets w ON w.merchant_id = m.id WHERE m.status = 'active' AND m.payout_schedule IS NOT NULL AND m.payout_schedule != 'manual' ORDER BY w.available_balance DESC");
        return $stmt->fetchAll() ?: [];
    }
}
