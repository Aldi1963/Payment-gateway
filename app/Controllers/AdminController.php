<?php
/**
 * Admin Controller
 * Handles admin panel operations
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/Auth.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Repositories/UserRepository.php');
require_once base_path('app/Services/TransactionService.php');
require_once base_path('app/Services/WithdrawalService.php');
require_once base_path('app/Services/SettlementService.php');
require_once base_path('app/Services/AuditLogService.php');
require_once base_path('app/Services/WebhookService.php');

class AdminController
{
    private MerchantRepository $merchantRepo;
    private UserRepository $userRepo;
    private TransactionService $transactionService;
    private WithdrawalService $withdrawalService;
    private SettlementService $settlementService;
    private AuditLogService $auditService;
    private WebhookService $webhookService;

    public function __construct()
    {
        $this->merchantRepo = new MerchantRepository();
        $this->userRepo = new UserRepository();
        $this->transactionService = new TransactionService();
        $this->withdrawalService = new WithdrawalService();
        $this->settlementService = new SettlementService();
        $this->auditService = new AuditLogService();
        $this->webhookService = new WebhookService();
    }

    /**
     * Dashboard data
     */
    public function dashboard(): array
    {
        $stats = $this->transactionService->getAdminStats();
        $merchantCounts = $this->merchantRepo->countByStatus();
        
        require_once base_path('app/Repositories/TransactionRepository.php');
        $txRepo = new TransactionRepository();
        $recentTransactions = $txRepo->getRecent(10);
        $pendingWithdrawals = $this->withdrawalService->getAll(['status' => 'PENDING']);

        return [
            'stats' => $stats,
            'merchant_counts' => $merchantCounts,
            'recent_transactions' => $recentTransactions,
            'pending_withdrawals' => $pendingWithdrawals,
        ];
    }

    /**
     * Manage merchants
     */
    public function merchants(array $filters = []): array
    {
        return $this->merchantRepo->findAll($filters);
    }

    /**
     * Update merchant status
     */
    public function updateMerchantStatus(string $merchantId, string $status): array
    {
        $merchant = $this->merchantRepo->find($merchantId);
        if (!$merchant) {
            return ['success' => false, 'message' => 'Merchant tidak ditemukan.'];
        }

        $validStatuses = ['pending', 'active', 'suspended', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'message' => 'Status tidak valid.'];
        }

        $this->merchantRepo->update($merchantId, ['status' => $status, 'updated_at' => now()]);

        $this->auditService->log(
            Auth::id(), Auth::role(), $merchantId,
            'merchant_status_changed',
            "Merchant {$merchant['business_name']} status changed to {$status}",
            ['old_status' => $merchant['status'], 'new_status' => $status]
        );

        return ['success' => true, 'message' => 'Status merchant berhasil diperbarui.'];
    }

    /**
     * Update merchant fee settings
     */
    public function updateMerchantFee(string $merchantId, array $data): array
    {
        $merchant = $this->merchantRepo->find($merchantId);
        if (!$merchant) {
            return ['success' => false, 'message' => 'Merchant tidak ditemukan.'];
        }

        $validFeeTypes = ['flat', 'percentage', 'hybrid'];
        $feeType = $data['fee_type'] ?? 'percentage';
        if (!in_array($feeType, $validFeeTypes)) {
            return ['success' => false, 'message' => 'Tipe fee tidak valid.'];
        }

        $this->merchantRepo->update($merchantId, [
            'fee_type' => $feeType,
            'fee_value' => (float)($data['fee_value'] ?? 0),
            'fee_flat' => (float)($data['fee_flat'] ?? 0),
            'updated_at' => now(),
        ]);

        return ['success' => true, 'message' => 'Fee merchant berhasil diperbarui.'];
    }

    /**
     * Get all transactions
     */
    public function transactions(array $filters = []): array
    {
        return $this->transactionService->getAll($filters);
    }

    /**
     * Get all withdrawals
     */
    public function withdrawals(array $filters = []): array
    {
        return $this->withdrawalService->getAll($filters);
    }

    /**
     * Process withdrawal action
     */
    public function processWithdrawal(string $withdrawalId, string $action, string $note = ''): array
    {
        return match($action) {
            'approve' => $this->withdrawalService->approve($withdrawalId, Auth::id(), $note),
            'reject' => $this->withdrawalService->reject($withdrawalId, Auth::id(), $note),
            'success' => $this->withdrawalService->markSuccess($withdrawalId, Auth::id()),
            default => ['success' => false, 'message' => 'Aksi tidak valid.'],
        };
    }

    /**
     * Get settlements
     */
    public function settlements(array $filters = []): array
    {
        return $this->settlementService->getAll($filters);
    }

    /**
     * Create settlement
     */
    public function createSettlement(string $merchantId, string $period = ''): array
    {
        return $this->settlementService->create($merchantId, Auth::id(), $period);
    }

    /**
     * Process settlement action
     */
    public function processSettlement(string $settlementId, string $action): array
    {
        return match($action) {
            'approve' => $this->settlementService->approve($settlementId, Auth::id()),
            'transfer' => $this->settlementService->markTransferred($settlementId, Auth::id()),
            'complete' => $this->settlementService->markCompleted($settlementId, Auth::id()),
            default => ['success' => false, 'message' => 'Aksi tidak valid.'],
        };
    }

    /**
     * Get webhook logs
     */
    public function webhookLogs(array $filters = []): array
    {
        return $this->webhookService->getAll($filters);
    }

    /**
     * Get audit logs
     */
    public function auditLogs(array $filters = []): array
    {
        return $this->auditService->getAll($filters);
    }
}
