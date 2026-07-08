<?php
/**
 * Analytics Service
 * Provides comprehensive analytics and reporting for dashboard
 * 
 * Features:
 * - Revenue analytics (daily, weekly, monthly, yearly)
 * - Conversion rates (PENDING → PAID)
 * - Top merchants by volume
 * - Payment channel distribution
 * - Fee revenue breakdown
 * - Trend analysis
 */

require_once base_path('app/Database.php');

class AnalyticsService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get revenue analytics for a period
     * 
     * @param string $period daily|weekly|monthly|yearly
     * @param string|null $merchantId Filter by merchant (null = all)
     * @param int $limit Number of periods to return
     * @return array
     */
    public function getRevenueAnalytics(string $period = 'daily', ?string $merchantId = null, int $limit = 30): array
    {
        $dateFormat = match($period) {
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            'yearly' => '%Y',
            default => '%Y-%m-%d',
        };

        $params = [];
        $whereClause = "WHERE t.status = 'PAID'";
        
        if ($merchantId) {
            $whereClause .= " AND t.merchant_id = :merchant_id";
            $params['merchant_id'] = $merchantId;
        }

        $sql = "SELECT 
                    DATE_FORMAT(t.paid_at, '{$dateFormat}') as period,
                    COUNT(*) as transaction_count,
                    SUM(t.amount) as gross_revenue,
                    SUM(t.fee) as total_fees,
                    SUM(t.net_amount) as net_revenue,
                    AVG(t.amount) as avg_transaction_amount,
                    MIN(t.amount) as min_amount,
                    MAX(t.amount) as max_amount
                FROM transactions t
                {$whereClause}
                GROUP BY DATE_FORMAT(t.paid_at, '{$dateFormat}')
                ORDER BY period DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll() ?: [];

        return array_reverse($results); // Chronological order
    }

    /**
     * Get conversion rate analytics
     * Shows PENDING → PAID conversion rate over time
     */
    public function getConversionRate(?string $merchantId = null, int $days = 30): array
    {
        $params = [];
        $whereClause = "WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        
        if ($merchantId) {
            $whereClause .= " AND t.merchant_id = :merchant_id";
            $params['merchant_id'] = $merchantId;
        }

        $sql = "SELECT 
                    DATE(t.created_at) as date,
                    COUNT(*) as total_created,
                    SUM(CASE WHEN t.status = 'PAID' THEN 1 ELSE 0 END) as total_paid,
                    SUM(CASE WHEN t.status = 'EXPIRED' THEN 1 ELSE 0 END) as total_expired,
                    SUM(CASE WHEN t.status = 'FAILED' THEN 1 ELSE 0 END) as total_failed,
                    ROUND(SUM(CASE WHEN t.status = 'PAID' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as conversion_rate
                FROM transactions t
                {$whereClause}
                GROUP BY DATE(t.created_at)
                ORDER BY date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get top merchants by transaction volume
     */
    public function getTopMerchants(int $limit = 10, int $days = 30): array
    {
        $sql = "SELECT 
                    m.id,
                    m.business_name,
                    COUNT(t.id) as transaction_count,
                    SUM(CASE WHEN t.status = 'PAID' THEN t.amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN t.status = 'PAID' THEN t.fee ELSE 0 END) as total_fees,
                    ROUND(SUM(CASE WHEN t.status = 'PAID' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(t.id), 0), 2) as success_rate
                FROM merchants m
                LEFT JOIN transactions t ON t.merchant_id = m.id 
                    AND t.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                WHERE m.status = 'active'
                GROUP BY m.id, m.business_name
                ORDER BY total_revenue DESC
                LIMIT :limit_val";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit_val', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get payment channel distribution
     */
    public function getChannelDistribution(?string $merchantId = null, int $days = 30): array
    {
        $params = [];
        $whereClause = "WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) AND t.status = 'PAID'";
        
        if ($merchantId) {
            $whereClause .= " AND t.merchant_id = :merchant_id";
            $params['merchant_id'] = $merchantId;
        }

        $sql = "SELECT 
                    COALESCE(t.payment_channel, 'unknown') as channel,
                    COALESCE(t.payment_method, 'default') as method,
                    COUNT(*) as count,
                    SUM(t.amount) as total_amount,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM transactions WHERE status = 'PAID' AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)), 2) as percentage
                FROM transactions t
                {$whereClause}
                GROUP BY t.payment_channel, t.payment_method
                ORDER BY count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get fee revenue breakdown
     */
    public function getFeeBreakdown(?string $merchantId = null, int $days = 30): array
    {
        $params = [];
        $whereClause = "WHERE t.status = 'PAID' AND t.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        
        if ($merchantId) {
            $whereClause .= " AND t.merchant_id = :merchant_id";
            $params['merchant_id'] = $merchantId;
        }

        $sql = "SELECT 
                    COALESCE(t.fee_type, 'percentage') as fee_type,
                    COUNT(*) as transaction_count,
                    SUM(t.fee) as total_fees,
                    AVG(t.fee) as avg_fee,
                    ROUND(AVG(t.fee * 100.0 / NULLIF(t.amount, 0)), 3) as avg_fee_percentage
                FROM transactions t
                {$whereClause}
                GROUP BY t.fee_type
                ORDER BY total_fees DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get hourly transaction distribution (heatmap data)
     */
    public function getHourlyDistribution(?string $merchantId = null, int $days = 30): array
    {
        $params = [];
        $whereClause = "WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)";
        
        if ($merchantId) {
            $whereClause .= " AND t.merchant_id = :merchant_id";
            $params['merchant_id'] = $merchantId;
        }

        $sql = "SELECT 
                    DAYOFWEEK(t.created_at) as day_of_week,
                    HOUR(t.created_at) as hour,
                    COUNT(*) as count,
                    SUM(CASE WHEN t.status = 'PAID' THEN 1 ELSE 0 END) as paid_count
                FROM transactions t
                {$whereClause}
                GROUP BY DAYOFWEEK(t.created_at), HOUR(t.created_at)
                ORDER BY day_of_week, hour";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get real-time dashboard summary
     */
    public function getDashboardSummary(?string $merchantId = null): array
    {
        $params = [];
        $merchantFilter = '';
        if ($merchantId) {
            $merchantFilter = " AND merchant_id = :merchant_id";
            $params['merchant_id'] = $merchantId;
        }

        $today = date('Y-m-d');
        $thisMonth = date('Y-m');

        // Today's stats
        $stmt = $this->db->prepare("SELECT 
            COUNT(*) as today_total,
            SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as today_paid,
            SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END) as today_revenue,
            SUM(CASE WHEN status = 'PAID' THEN fee ELSE 0 END) as today_fees,
            SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as today_pending
            FROM transactions WHERE DATE(created_at) = :today {$merchantFilter}");
        $stmt->execute(array_merge(['today' => $today], $params));
        $todayStats = $stmt->fetch() ?: [];

        // This month's stats
        $stmt = $this->db->prepare("SELECT 
            COUNT(*) as month_total,
            SUM(CASE WHEN status = 'PAID' THEN 1 ELSE 0 END) as month_paid,
            SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END) as month_revenue,
            SUM(CASE WHEN status = 'PAID' THEN fee ELSE 0 END) as month_fees
            FROM transactions WHERE DATE_FORMAT(created_at, '%Y-%m') = :month {$merchantFilter}");
        $stmt->execute(array_merge(['month' => $thisMonth], $params));
        $monthStats = $stmt->fetch() ?: [];

        // All-time stats
        $stmt = $this->db->prepare("SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END) as total_revenue,
            SUM(CASE WHEN status = 'PAID' THEN fee ELSE 0 END) as total_fees
            FROM transactions WHERE 1=1 {$merchantFilter}");
        $stmt->execute($params);
        $allTimeStats = $stmt->fetch() ?: [];

        // Active merchants (admin only)
        $merchantCount = null;
        if (!$merchantId) {
            $stmt = $this->db->query("SELECT COUNT(*) FROM merchants WHERE status = 'active'");
            $merchantCount = (int)$stmt->fetchColumn();
        }

        return [
            'today' => [
                'transactions' => (int)($todayStats['today_total'] ?? 0),
                'paid' => (int)($todayStats['today_paid'] ?? 0),
                'revenue' => (int)($todayStats['today_revenue'] ?? 0),
                'fees' => (int)($todayStats['today_fees'] ?? 0),
                'pending' => (int)($todayStats['today_pending'] ?? 0),
            ],
            'this_month' => [
                'transactions' => (int)($monthStats['month_total'] ?? 0),
                'paid' => (int)($monthStats['month_paid'] ?? 0),
                'revenue' => (int)($monthStats['month_revenue'] ?? 0),
                'fees' => (int)($monthStats['month_fees'] ?? 0),
            ],
            'all_time' => [
                'transactions' => (int)($allTimeStats['total_transactions'] ?? 0),
                'revenue' => (int)($allTimeStats['total_revenue'] ?? 0),
                'fees' => (int)($allTimeStats['total_fees'] ?? 0),
            ],
            'active_merchants' => $merchantCount,
        ];
    }

    /**
     * Get growth comparison (this period vs last period)
     */
    public function getGrowthComparison(?string $merchantId = null): array
    {
        $params = [];
        $merchantFilter = '';
        if ($merchantId) {
            $merchantFilter = " AND merchant_id = :merchant_id";
            $params['merchant_id'] = $merchantId;
        }

        $thisMonthStart = date('Y-m-01');
        $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

        // This month
        $stmt = $this->db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as revenue FROM transactions WHERE status = 'PAID' AND paid_at >= :start {$merchantFilter}");
        $stmt->execute(array_merge(['start' => $thisMonthStart], $params));
        $thisMonth = $stmt->fetch();

        // Last month (same number of days for fair comparison)
        $daysIntoMonth = (int)date('d');
        $compareEnd = date('Y-m-d', strtotime($lastMonthStart . " +{$daysIntoMonth} days"));
        $stmt = $this->db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as revenue FROM transactions WHERE status = 'PAID' AND paid_at >= :start AND paid_at < :end {$merchantFilter}");
        $stmt->execute(array_merge(['start' => $lastMonthStart, 'end' => $compareEnd], $params));
        $lastMonth = $stmt->fetch();

        $lastRevenue = (int)($lastMonth['revenue'] ?? 0);
        $thisRevenue = (int)($thisMonth['revenue'] ?? 0);
        $lastCount = (int)($lastMonth['count'] ?? 0);
        $thisCount = (int)($thisMonth['count'] ?? 0);

        return [
            'revenue' => [
                'current' => $thisRevenue,
                'previous' => $lastRevenue,
                'growth_percent' => $lastRevenue > 0 ? round(($thisRevenue - $lastRevenue) / $lastRevenue * 100, 1) : 0,
            ],
            'transactions' => [
                'current' => $thisCount,
                'previous' => $lastCount,
                'growth_percent' => $lastCount > 0 ? round(($thisCount - $lastCount) / $lastCount * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Export analytics data for CSV/reporting
     */
    public function exportTransactions(?string $merchantId, string $dateFrom, string $dateTo, string $status = ''): array
    {
        $params = ['date_from' => $dateFrom, 'date_to' => $dateTo . ' 23:59:59'];
        $whereClause = "WHERE t.created_at BETWEEN :date_from AND :date_to";
        
        if ($merchantId) {
            $whereClause .= " AND t.merchant_id = :merchant_id";
            $params['merchant_id'] = $merchantId;
        }
        if ($status) {
            $whereClause .= " AND t.status = :status";
            $params['status'] = $status;
        }

        $sql = "SELECT 
                    t.order_id, t.amount, t.fee, t.net_amount, t.status,
                    t.payment_channel, t.payment_method,
                    t.customer_name, t.customer_email, t.customer_wa,
                    t.paid_at, t.created_at,
                    m.business_name as merchant_name
                FROM transactions t
                LEFT JOIN merchants m ON m.id = t.merchant_id
                {$whereClause}
                ORDER BY t.created_at DESC
                LIMIT 10000";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}
