<?php
/**
 * Bulk Operation Service
 * Admin can perform batch actions on withdrawals and settlements
 */

require_once base_path('app/Database.php');
require_once base_path('app/Services/WithdrawalService.php');
require_once base_path('app/Services/AuditLogService.php');

class BulkOperationService
{
    private PDO $db;
    private WithdrawalService $wdService;
    private AuditLogService $audit;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->wdService = new WithdrawalService();
        $this->audit = new AuditLogService();
    }

    /**
     * Bulk approve withdrawals
     */
    public function bulkApproveWithdrawals(array $ids, string $adminId, string $note = ''): array
    {
        $success = 0; $failed = 0;
        foreach ($ids as $id) {
            $result = $this->wdService->approve($id, $adminId, $note);
            if ($result['success']) $success++;
            else $failed++;
        }
        $this->audit->log($adminId, Auth::role() ?? 'admin', null, 'bulk_approve_withdrawal',
            "Bulk approved {$success} withdrawals", ['total' => count($ids), 'success' => $success, 'failed' => $failed]);
        return ['success' => true, 'message' => "Berhasil approve {$success} dari " . count($ids) . " withdrawal.", 'approved' => $success, 'failed' => $failed];
    }

    /**
     * Bulk reject withdrawals
     */
    public function bulkRejectWithdrawals(array $ids, string $adminId, string $reason = ''): array
    {
        $success = 0; $failed = 0;
        foreach ($ids as $id) {
            $result = $this->wdService->reject($id, $adminId, $reason);
            if ($result['success']) $success++;
            else $failed++;
        }
        $this->audit->log($adminId, Auth::role() ?? 'admin', null, 'bulk_reject_withdrawal',
            "Bulk rejected {$success} withdrawals", ['total' => count($ids), 'success' => $success, 'failed' => $failed]);
        return ['success' => true, 'message' => "Berhasil reject {$success} dari " . count($ids) . " withdrawal.", 'rejected' => $success, 'failed' => $failed];
    }


    /**
     * Bulk mark success withdrawals
     */
    public function bulkSuccessWithdrawals(array $ids, string $adminId): array
    {
        $success = 0; $failed = 0;
        foreach ($ids as $id) {
            $result = $this->wdService->markSuccess($id, $adminId);
            if ($result['success']) $success++;
            else $failed++;
        }
        return ['success' => true, 'message' => "Berhasil mark success {$success} withdrawal.", 'completed' => $success, 'failed' => $failed];
    }

    /**
     * Bulk create settlements for multiple merchants
     */
    public function bulkCreateSettlements(array $merchantIds, string $adminId, string $period = ''): array
    {
        require_once base_path('app/Services/SettlementService.php');
        $settlementService = new SettlementService();
        $success = 0; $failed = 0; $messages = [];

        foreach ($merchantIds as $mid) {
            $result = $settlementService->create($mid, $adminId, $period);
            if ($result['success']) { $success++; }
            else { $failed++; $messages[] = $result['message']; }
        }

        $this->audit->log($adminId, Auth::role() ?? 'admin', null, 'bulk_settlement',
            "Bulk settlement: {$success} created", ['total' => count($merchantIds), 'success' => $success, 'failed' => $failed]);

        return ['success' => true, 'message' => "Settlement dibuat untuk {$success} merchant.", 'created' => $success, 'failed' => $failed, 'errors' => $messages];
    }

    /**
     * Bulk activate merchants
     */
    public function bulkActivateMerchants(array $merchantIds, string $adminId): array
    {
        $success = 0;
        foreach ($merchantIds as $mid) {
            $this->db->prepare("UPDATE merchants SET status='active', updated_at=:now WHERE id=:id AND status='pending'")
                ->execute(['now' => now(), 'id' => $mid]);
            if ($this->db->prepare("SELECT ROW_COUNT()")->fetchColumn() > 0) $success++;
        }
        $this->audit->log($adminId, 'admin', null, 'bulk_activate_merchant', "Bulk activated {$success} merchants", ['ids' => $merchantIds]);
        return ['success' => true, 'message' => "{$success} merchant diaktifkan.", 'activated' => $success];
    }

    /**
     * Bulk export (used by export page for large datasets)
     */
    public function getFilteredTransactions(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['merchant_id'])) { $where[] = 'merchant_id = :mid'; $params['mid'] = $filters['merchant_id']; }
        if (!empty($filters['status'])) { $where[] = 'status = :status'; $params['status'] = $filters['status']; }
        if (!empty($filters['date_from'])) { $where[] = 'DATE(created_at) >= :df'; $params['df'] = $filters['date_from']; }
        if (!empty($filters['date_to'])) { $where[] = 'DATE(created_at) <= :dt'; $params['dt'] = $filters['date_to']; }

        $sql = "SELECT * FROM transactions WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 10000";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}
