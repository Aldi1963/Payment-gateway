<?php
/**
 * Report & Analytics Service
 * Provides business intelligence data for dashboards
 */

require_once base_path('app/Database.php');

class ReportService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get revenue analytics for period
     */
    public function getRevenueReport(string $period = '30d', ?string $merchantId = null): array
    {
        $days = match($period) { '7d'=>7, '30d'=>30, '90d'=>90, '1y'=>365, default=>30 };
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $where = "t.status = 'PAID' AND t.created_at >= :start";
        $params = ['start' => $startDate];
        if ($merchantId) { $where .= " AND t.merchant_id = :mid"; $params['mid'] = $merchantId; }

        // Daily revenue
        $stmt = $this->db->prepare("SELECT DATE(t.created_at) as date, SUM(t.amount) as revenue, SUM(t.fee) as fee, COUNT(*) as count FROM transactions t WHERE {$where} GROUP BY DATE(t.created_at) ORDER BY date");
        $stmt->execute($params);
        $daily = $stmt->fetchAll() ?: [];

        // Totals
        $stmt2 = $this->db->prepare("SELECT SUM(t.amount) as total_revenue, SUM(t.fee) as total_fee, SUM(t.net_amount) as total_net, COUNT(*) as total_tx, AVG(t.amount) as avg_amount FROM transactions t WHERE {$where}");
        $stmt2->execute($params);
        $totals = $stmt2->fetch() ?: [];

        return ['daily' => $daily, 'totals' => $totals, 'period' => $period, 'start_date' => $startDate];
    }

    /**
     * Get conversion rate (paid / total)
     */
    public function getConversionRate(?string $merchantId = null): array
    {
        $where = $merchantId ? "WHERE merchant_id = :mid" : "";
        $params = $merchantId ? ['mid' => $merchantId] : [];

        $stmt = $this->db->prepare("SELECT status, COUNT(*) as cnt FROM transactions {$where} GROUP BY status");
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        $total = array_sum(array_column($rows, 'cnt'));
        $paid = 0;
        foreach ($rows as $r) { if ($r['status'] === 'PAID') $paid = (int)$r['cnt']; }

        return [
            'total' => $total,
            'paid' => $paid,
            'rate' => $total > 0 ? round(($paid / $total) * 100, 2) : 0,
            'breakdown' => $rows,
        ];
    }


    /**
     * Get peak transaction hours
     */
    public function getPeakHours(?string $merchantId = null): array
    {
        $where = $merchantId ? "WHERE merchant_id = :mid" : "";
        $params = $merchantId ? ['mid' => $merchantId] : [];

        $stmt = $this->db->prepare("SELECT HOUR(created_at) as hour, COUNT(*) as count FROM transactions {$where} GROUP BY HOUR(created_at) ORDER BY hour");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get top merchants by revenue
     */
    public function getTopMerchants(int $limit = 10): array
    {
        $stmt = $this->db->prepare("SELECT t.merchant_id, m.business_name, SUM(t.amount) as total_revenue, SUM(t.fee) as total_fee, COUNT(*) as tx_count FROM transactions t JOIN merchants m ON t.merchant_id = m.id WHERE t.status = 'PAID' GROUP BY t.merchant_id, m.business_name ORDER BY total_revenue DESC LIMIT " . (int)$limit);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get payment method distribution
     */
    public function getPaymentMethodStats(?string $merchantId = null): array
    {
        $where = "WHERE status = 'PAID'";
        $params = [];
        if ($merchantId) { $where .= " AND merchant_id = :mid"; $params['mid'] = $merchantId; }

        $stmt = $this->db->prepare("SELECT fee_type, COUNT(*) as count, SUM(amount) as total FROM transactions {$where} GROUP BY fee_type");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get daily transaction trends (for charts)
     */
    public function getDailyTrend(int $days = 30, ?string $merchantId = null): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $where = "created_at >= :start";
        $params = ['start' => $startDate];
        if ($merchantId) { $where .= " AND merchant_id = :mid"; $params['mid'] = $merchantId; }

        $stmt = $this->db->prepare("SELECT DATE(created_at) as date, COUNT(*) as total, SUM(CASE WHEN status='PAID' THEN 1 ELSE 0 END) as paid, SUM(CASE WHEN status='PAID' THEN amount ELSE 0 END) as revenue FROM transactions WHERE {$where} GROUP BY DATE(created_at) ORDER BY date");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get withdrawal stats
     */
    public function getWithdrawalStats(?string $merchantId = null): array
    {
        $where = $merchantId ? "WHERE merchant_id = :mid" : "";
        $params = $merchantId ? ['mid' => $merchantId] : [];

        $stmt = $this->db->prepare("SELECT status, COUNT(*) as count, SUM(amount) as total FROM withdrawals {$where} GROUP BY status");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get monthly summary
     */
    public function getMonthlySummary(int $months = 12, ?string $merchantId = null): array
    {
        $where = "status = 'PAID'";
        $params = [];
        if ($merchantId) { $where .= " AND merchant_id = :mid"; $params['mid'] = $merchantId; }

        $stmt = $this->db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as tx_count, SUM(amount) as revenue, SUM(fee) as fee FROM transactions WHERE {$where} GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month DESC LIMIT " . (int)$months);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}
