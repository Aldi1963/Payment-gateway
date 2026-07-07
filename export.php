<?php
/**
 * Export Handler
 * Generates CSV exports for transactions, withdrawals, settlements
 * 
 * Usage:
 *   /export.php?type=transactions&format=csv
 *   /export.php?type=withdrawals&format=csv
 *   /export.php?type=settlements&format=csv
 *   &merchant_id=xxx (optional, admin only)
 *   &date_from=2026-01-01&date_to=2026-12-31 (optional)
 */

require_once __DIR__ . '/includes/init.php';
Auth::requireLogin();

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$merchantFilter = $_GET['merchant_id'] ?? '';

// Determine merchant scope
$merchantId = null;
if (Auth::isMerchant()) {
    $merchantId = Auth::merchantId();
} elseif (!empty($merchantFilter) && Auth::isAdmin()) {
    $merchantId = $merchantFilter;
}

$data = [];
$filename = '';
$headers = [];

switch ($type) {
    case 'transactions':
        require_once base_path('app/Repositories/TransactionRepository.php');
        $repo = new TransactionRepository();
        $records = $merchantId ? $repo->findByMerchant($merchantId) : $repo->findAll();
        
        // Date filter
        if ($dateFrom || $dateTo) {
            $records = array_filter($records, function($r) use ($dateFrom, $dateTo) {
                $d = substr($r['created_at'] ?? '', 0, 10);
                if ($dateFrom && $d < $dateFrom) return false;
                if ($dateTo && $d > $dateTo) return false;
                return true;
            });
        }

        $headers = ['Order ID','Amount','Fee','Fee Type','Net Amount','Status','Customer Name','Customer Email','Customer WA','Created At','Paid At'];
        foreach ($records as $r) {
            $data[] = [
                $r['order_id'] ?? '',
                $r['amount'] ?? 0,
                $r['fee'] ?? 0,
                $r['fee_type'] ?? '',
                $r['net_amount'] ?? 0,
                $r['status'] ?? '',
                $r['customer_name'] ?? '',
                $r['customer_email'] ?? '',
                $r['customer_wa'] ?? '',
                $r['created_at'] ?? '',
                $r['paid_at'] ?? '',
            ];
        }
        $filename = 'transactions_' . date('Ymd_His');
        break;

    case 'withdrawals':
        require_once base_path('app/Repositories/WithdrawalRepository.php');
        $repo = new WithdrawalRepository();
        $records = $merchantId ? $repo->findByMerchant($merchantId) : $repo->findAll();

        if ($dateFrom || $dateTo) {
            $records = array_filter($records, function($r) use ($dateFrom, $dateTo) {
                $d = substr($r['created_at'] ?? '', 0, 10);
                if ($dateFrom && $d < $dateFrom) return false;
                if ($dateTo && $d > $dateTo) return false;
                return true;
            });
        }

        $headers = ['ID','Amount','Fee','Net Amount','Bank','Account Number','Account Name','Status','Admin Note','Created At','Processed At'];
        foreach ($records as $r) {
            $data[] = [
                substr($r['id'] ?? '', 0, 8),
                $r['amount'] ?? 0,
                $r['fee'] ?? 0,
                $r['net_amount'] ?? ($r['amount'] ?? 0),
                $r['bank_name'] ?? '',
                $r['account_number'] ?? '',
                $r['account_name'] ?? '',
                $r['status'] ?? '',
                $r['admin_note'] ?? '',
                $r['created_at'] ?? '',
                $r['processed_at'] ?? '',
            ];
        }
        $filename = 'withdrawals_' . date('Ymd_His');
        break;

    case 'settlements':
        if (!Auth::isAdmin() && !Auth::isFinance()) {
            json_response(['error' => 'Forbidden'], 403);
        }
        require_once base_path('app/Repositories/SettlementRepository.php');
        $repo = new SettlementRepository();
        $records = $merchantId ? $repo->findByMerchant($merchantId) : $repo->findAll();

        $headers = ['ID','Merchant ID','Period','Total Transactions','Total Gross','Total Fee','Total Net','Status','Created At'];
        foreach ($records as $r) {
            $data[] = [
                substr($r['id'] ?? '', 0, 8),
                $r['merchant_id'] ?? '',
                $r['period'] ?? '',
                $r['total_transactions'] ?? 0,
                $r['total_gross'] ?? 0,
                $r['total_fee'] ?? 0,
                $r['total_net'] ?? 0,
                $r['status'] ?? '',
                $r['created_at'] ?? '',
            ];
        }
        $filename = 'settlements_' . date('Ymd_His');
        break;

    case 'audit_logs':
        if (!Auth::isAdmin()) {
            json_response(['error' => 'Forbidden'], 403);
        }
        require_once base_path('app/Repositories/AuditLogRepository.php');
        $repo = new AuditLogRepository();
        $records = $repo->findAll();

        $headers = ['Action','Description','Actor Role','Merchant ID','IP','Created At'];
        foreach (array_slice($records, 0, 5000) as $r) {
            $data[] = [
                $r['action'] ?? '',
                $r['description'] ?? '',
                $r['actor_role'] ?? '',
                $r['merchant_id'] ?? '',
                $r['ip'] ?? '',
                $r['created_at'] ?? '',
            ];
        }
        $filename = 'audit_logs_' . date('Ymd_His');
        break;

    default:
        flash('error', 'Tipe export tidak valid.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

// Generate CSV
if ($format === 'csv' && !empty($data)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

flash('error', 'Tidak ada data untuk diexport.');
redirect($_SERVER['HTTP_REFERER'] ?? '/');
