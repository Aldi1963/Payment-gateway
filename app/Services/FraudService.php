<?php
/**
 * Fraud Detection Service
 * IP-based risk scoring, velocity checks, pattern detection
 * 
 * Risk levels: low (0-30), medium (31-60), high (61-80), critical (81-100)
 */

require_once base_path('app/Database.php');

class FraudService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Calculate risk score for a transaction
     * Returns: ['score' => 0-100, 'level' => string, 'factors' => array, 'action' => string]
     */
    public function assessRisk(array $transaction, string $ip): array
    {
        $score = 0;
        $factors = [];
        $merchantId = $transaction['merchant_id'] ?? '';

        // 1. Velocity check: too many transactions from same IP in short time
        $velocityScore = $this->checkVelocity($ip, $merchantId);
        if ($velocityScore > 0) {
            $score += $velocityScore;
            $factors[] = ['name' => 'High velocity', 'score' => $velocityScore, 'detail' => 'Multiple transactions from same IP'];
        }

        // 2. Amount anomaly: unusually high amount for this merchant
        $amountScore = $this->checkAmountAnomaly((int)($transaction['amount'] ?? 0), $merchantId);
        if ($amountScore > 0) {
            $score += $amountScore;
            $factors[] = ['name' => 'Amount anomaly', 'score' => $amountScore, 'detail' => 'Amount significantly above average'];
        }

        // 3. IP reputation: known bad IPs, VPN/proxy, different country
        $ipScore = $this->checkIpReputation($ip);
        if ($ipScore > 0) {
            $score += $ipScore;
            $factors[] = ['name' => 'IP risk', 'score' => $ipScore, 'detail' => 'Suspicious IP address'];
        }

        // 4. Time pattern: unusual hours
        $timeScore = $this->checkTimePattern();
        if ($timeScore > 0) {
            $score += $timeScore;
            $factors[] = ['name' => 'Unusual time', 'score' => $timeScore, 'detail' => 'Transaction at unusual hour'];
        }

        // 5. Duplicate order detection
        $dupScore = $this->checkDuplicate($transaction);
        if ($dupScore > 0) {
            $score += $dupScore;
            $factors[] = ['name' => 'Possible duplicate', 'score' => $dupScore, 'detail' => 'Similar transaction recently'];
        }

        $score = min(100, $score);
        $level = match(true) {
            $score >= 81 => 'critical',
            $score >= 61 => 'high',
            $score >= 31 => 'medium',
            default => 'low',
        };

        $action = match($level) {
            'critical' => 'block',
            'high' => 'review',
            'medium' => 'flag',
            default => 'allow',
        };

        // Log fraud assessment
        $this->logAssessment($transaction, $ip, $score, $level, $factors);

        return ['score' => $score, 'level' => $level, 'factors' => $factors, 'action' => $action];
    }


    /**
     * Velocity check: transactions per IP in last 10 minutes
     */
    private function checkVelocity(string $ip, string $merchantId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `fraud_logs` WHERE `ip` = :ip AND `created_at` >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $stmt->execute(['ip' => $ip]);
        $count = (int)$stmt->fetchColumn();

        $threshold = (int)setting('fraud_velocity_threshold', 10);
        if ($count >= $threshold * 2) return 40;
        if ($count >= $threshold) return 20;
        return 0;
    }

    /**
     * Amount anomaly: compare to merchant's average
     */
    private function checkAmountAnomaly(int $amount, string $merchantId): int
    {
        if ($amount <= 0 || empty($merchantId)) return 0;

        $stmt = $this->db->prepare("SELECT AVG(amount) as avg_amt, STDDEV(amount) as std_amt FROM `transactions` WHERE `merchant_id` = :mid AND `status` = 'PAID' AND `created_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute(['mid' => $merchantId]);
        $stats = $stmt->fetch();

        if (!$stats || !$stats['avg_amt']) return 0;

        $avg = (float)$stats['avg_amt'];
        $std = (float)($stats['std_amt'] ?: $avg * 0.5);
        $deviation = abs($amount - $avg) / max($std, 1);

        if ($deviation > 5) return 30; // Very unusual
        if ($deviation > 3) return 15; // Somewhat unusual
        return 0;
    }

    /**
     * IP reputation check (basic: private ranges, known patterns)
     */
    private function checkIpReputation(string $ip): int
    {
        // Check if IP is in blocklist (stored in settings or DB)
        $blocklist = setting('fraud_ip_blocklist', '');
        if (!empty($blocklist)) {
            $blocked = array_map('trim', explode("\n", $blocklist));
            if (in_array($ip, $blocked)) return 50;
        }

        // Check previous fraud from this IP
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `fraud_logs` WHERE `ip` = :ip AND `level` IN ('high','critical') AND `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute(['ip' => $ip]);
        $badHistory = (int)$stmt->fetchColumn();
        if ($badHistory >= 3) return 30;
        if ($badHistory >= 1) return 10;

        return 0;
    }

    /**
     * Time pattern: transactions at 2-5 AM (unusual for most merchants)
     */
    private function checkTimePattern(): int
    {
        $hour = (int)date('H');
        if ($hour >= 2 && $hour <= 5) return 10;
        return 0;
    }

    /**
     * Duplicate transaction detection
     */
    private function checkDuplicate(array $tx): int
    {
        $merchantId = $tx['merchant_id'] ?? '';
        $amount = (int)($tx['amount'] ?? 0);
        if (!$merchantId || !$amount) return 0;

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `transactions` WHERE `merchant_id` = :mid AND `amount` = :amt AND `customer_email` = :email AND `created_at` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->execute(['mid' => $merchantId, 'amt' => $amount, 'email' => $tx['customer_email'] ?? '']);
        $count = (int)$stmt->fetchColumn();

        if ($count >= 3) return 25;
        if ($count >= 2) return 10;
        return 0;
    }

    /**
     * Log fraud assessment
     */
    private function logAssessment(array $tx, string $ip, int $score, string $level, array $factors): void
    {
        $this->db->prepare("INSERT INTO `fraud_logs` (`id`,`transaction_id`,`merchant_id`,`ip`,`score`,`level`,`factors`,`action`,`created_at`) VALUES (:id,:tid,:mid,:ip,:score,:level,:factors,:action,:created_at)")
            ->execute([
                'id' => generate_uuid(),
                'tid' => $tx['id'] ?? null,
                'mid' => $tx['merchant_id'] ?? null,
                'ip' => $ip,
                'score' => $score,
                'level' => $level,
                'factors' => json_encode($factors),
                'action' => $level === 'critical' ? 'blocked' : ($level === 'high' ? 'flagged' : 'allowed'),
                'created_at' => now(),
            ]);
    }

    /**
     * Get fraud logs (admin)
     */
    public function getLogs(int $limit = 100): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `fraud_logs` ORDER BY `created_at` DESC LIMIT " . (int)$limit);
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$r) {
            if (isset($r['factors']) && is_string($r['factors'])) {
                $r['factors'] = json_decode($r['factors'], true) ?: [];
            }
        }
        return $rows;
    }

    /**
     * Get risk stats
     */
    public function getStats(): array
    {
        $stmt = $this->db->query("SELECT level, COUNT(*) as count FROM `fraud_logs` WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY level");
        return $stmt->fetchAll() ?: [];
    }
}
