<?php
/**
 * Activity Timeline Service
 * Aggregates events into a visual timeline per merchant
 */

require_once base_path('app/Database.php');

class ActivityService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get timeline feed for a merchant
     */
    public function getMerchantTimeline(string $merchantId, int $limit = 50): array
    {
        // Combine multiple event sources into one timeline
        $events = [];

        // Transactions
        $stmt = $this->db->prepare("SELECT 'transaction' as event_type, id, order_id as title, status, amount as value, created_at FROM transactions WHERE merchant_id = :mid ORDER BY created_at DESC LIMIT 20");
        $stmt->execute(['mid' => $merchantId]);
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $events[] = [
                'type' => 'transaction',
                'icon' => $this->getIcon('transaction', $r['status']),
                'title' => "Transaksi {$r['title']}",
                'description' => format_currency((int)$r['value']) . " - {$r['status']}",
                'status' => $r['status'],
                'timestamp' => $r['created_at'],
                'id' => $r['id'],
            ];
        }


        // Withdrawals
        $stmt = $this->db->prepare("SELECT 'withdrawal' as event_type, id, CONCAT('Withdraw ', status) as title, status, amount as value, created_at FROM withdrawals WHERE merchant_id = :mid ORDER BY created_at DESC LIMIT 10");
        $stmt->execute(['mid' => $merchantId]);
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $events[] = [
                'type' => 'withdrawal',
                'icon' => $this->getIcon('withdrawal', $r['status']),
                'title' => "Withdrawal " . format_currency((int)$r['value']),
                'description' => $r['status'],
                'status' => $r['status'],
                'timestamp' => $r['created_at'],
                'id' => $r['id'],
            ];
        }

        // Audit logs (merchant-related actions)
        $stmt = $this->db->prepare("SELECT action, description, created_at FROM audit_logs WHERE merchant_id = :mid ORDER BY created_at DESC LIMIT 15");
        $stmt->execute(['mid' => $merchantId]);
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $events[] = [
                'type' => 'activity',
                'icon' => $this->getIcon('activity', $r['action']),
                'title' => ucfirst(str_replace('_', ' ', $r['action'])),
                'description' => truncate($r['description'] ?? '', 80),
                'status' => $r['action'],
                'timestamp' => $r['created_at'],
                'id' => null,
            ];
        }

        // Sort by timestamp descending
        usort($events, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
        return array_slice($events, 0, $limit);
    }

    /**
     * Get admin global activity feed
     */
    public function getGlobalFeed(int $limit = 50): array
    {
        $events = [];

        // Recent paid transactions
        $stmt = $this->db->prepare("SELECT t.id, t.order_id, t.amount, t.status, t.merchant_id, m.business_name, t.created_at FROM transactions t LEFT JOIN merchants m ON t.merchant_id = m.id WHERE t.status = 'PAID' ORDER BY t.created_at DESC LIMIT 20");
        $stmt->execute();
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $events[] = [
                'type' => 'payment',
                'icon' => '💰',
                'title' => "Payment " . format_currency((int)$r['amount']),
                'description' => ($r['business_name'] ?? 'Unknown') . " - {$r['order_id']}",
                'timestamp' => $r['created_at'],
            ];
        }

        // New merchants
        $stmt = $this->db->prepare("SELECT id, business_name, created_at FROM merchants ORDER BY created_at DESC LIMIT 5");
        $stmt->execute();
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $events[] = [
                'type' => 'merchant',
                'icon' => '🏪',
                'title' => "Merchant baru: {$r['business_name']}",
                'description' => 'Menunggu aktivasi',
                'timestamp' => $r['created_at'],
            ];
        }

        // Withdrawal requests
        $stmt = $this->db->prepare("SELECT w.id, w.amount, w.status, m.business_name, w.created_at FROM withdrawals w LEFT JOIN merchants m ON w.merchant_id = m.id ORDER BY w.created_at DESC LIMIT 10");
        $stmt->execute();
        foreach ($stmt->fetchAll() ?: [] as $r) {
            $events[] = [
                'type' => 'withdrawal',
                'icon' => '🏦',
                'title' => "Withdrawal " . format_currency((int)$r['amount']),
                'description' => ($r['business_name'] ?? '') . " - {$r['status']}",
                'timestamp' => $r['created_at'],
            ];
        }

        usort($events, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
        return array_slice($events, 0, $limit);
    }

    /**
     * Get icon for event type
     */
    private function getIcon(string $type, string $status): string
    {
        return match($type) {
            'transaction' => match($status) { 'PAID' => '✅', 'PENDING' => '⏳', 'FAILED' => '❌', 'EXPIRED' => '⏰', default => '📋' },
            'withdrawal' => match($status) { 'SUCCESS' => '✅', 'PENDING' => '⏳', 'REJECTED' => '❌', default => '🏦' },
            'activity' => '📝',
            default => '📌',
        };
    }
}
