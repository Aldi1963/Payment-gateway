<?php
/**
 * Withdrawal Service
 * Handles merchant withdrawal requests and processing
 */

require_once base_path('app/Repositories/WithdrawalRepository.php');
require_once base_path('app/Services/WalletService.php');
require_once base_path('app/Services/AuditLogService.php');

class WithdrawalService
{
    private WithdrawalRepository $withdrawalRepo;
    private WalletService $walletService;
    private AuditLogService $auditService;

    public function __construct()
    {
        $this->withdrawalRepo = new WithdrawalRepository();
        $this->walletService = new WalletService();
        $this->auditService = new AuditLogService();
    }

    /**
     * Create withdrawal request
     */
    public function request(array $data, string $merchantId): array
    {
        $wallet = $this->walletService->getByMerchant($merchantId);
        if (!$wallet) {
            return ['success' => false, 'message' => 'Wallet tidak ditemukan.'];
        }

        $amount = (int)($data['amount'] ?? 0);
        $minWithdrawal = config('app.min_withdrawal', 10000);

        if ($amount < $minWithdrawal) {
            return ['success' => false, 'message' => 'Minimal penarikan ' . format_currency($minWithdrawal)];
        }

        if ($amount > $wallet['available_balance']) {
            return ['success' => false, 'message' => 'Saldo tidak mencukupi. Saldo tersedia: ' . format_currency($wallet['available_balance'])];
        }

        if (empty($data['bank_name']) || empty($data['account_number']) || empty($data['account_name'])) {
            return ['success' => false, 'message' => 'Data rekening bank wajib diisi lengkap.'];
        }

        $withdrawalId = generate_uuid();
        $withdrawal = [
            'id' => $withdrawalId,
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'bank_name' => sanitize($data['bank_name']),
            'account_number' => sanitize($data['account_number']),
            'account_name' => sanitize($data['account_name']),
            'note' => sanitize($data['note'] ?? ''),
            'status' => 'PENDING',
            'admin_note' => '',
            'processed_by' => null,
            'processed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Hold balance
        $held = $this->walletService->holdForWithdrawal($merchantId, $amount, $withdrawalId);
        if (!$held) {
            return ['success' => false, 'message' => 'Gagal menahan saldo. Coba lagi.'];
        }

        $this->withdrawalRepo->create($withdrawal);

        $this->auditService->log(
            Auth::id(), Auth::role(), $merchantId,
            'withdrawal_requested',
            "Withdrawal request #{$withdrawalId} amount " . format_currency($amount),
            ['withdrawal_id' => $withdrawalId, 'amount' => $amount]
        );

        return ['success' => true, 'message' => 'Permintaan penarikan berhasil diajukan.', 'withdrawal' => $withdrawal];
    }

    /**
     * Approve withdrawal (Admin/Finance)
     */
    public function approve(string $withdrawalId, string $adminId, string $adminNote = ''): array
    {
        $withdrawal = $this->withdrawalRepo->find($withdrawalId);
        if (!$withdrawal) {
            return ['success' => false, 'message' => 'Withdrawal tidak ditemukan.'];
        }
        if (!in_array($withdrawal['status'], ['PENDING', 'REVIEWING'])) {
            return ['success' => false, 'message' => 'Status withdrawal tidak dapat diubah.'];
        }

        $this->withdrawalRepo->update($withdrawalId, [
            'status' => 'APPROVED',
            'admin_note' => $adminNote,
            'processed_by' => $adminId,
            'updated_at' => now(),
        ]);

        $this->auditService->log(
            $adminId, Auth::role(), $withdrawal['merchant_id'],
            'withdrawal_approved',
            "Withdrawal #{$withdrawalId} approved",
            ['withdrawal_id' => $withdrawalId]
        );

        return ['success' => true, 'message' => 'Withdrawal disetujui.'];
    }

    /**
     * Mark withdrawal as success (transfer completed)
     */
    public function markSuccess(string $withdrawalId, string $adminId): array
    {
        $withdrawal = $this->withdrawalRepo->find($withdrawalId);
        if (!$withdrawal) {
            return ['success' => false, 'message' => 'Withdrawal tidak ditemukan.'];
        }
        if (!in_array($withdrawal['status'], ['APPROVED', 'PROCESSING'])) {
            return ['success' => false, 'message' => 'Status withdrawal tidak valid untuk diselesaikan.'];
        }

        $this->walletService->releaseHoldSuccess(
            $withdrawal['merchant_id'], $withdrawal['amount'], $withdrawalId
        );

        $this->withdrawalRepo->update($withdrawalId, [
            'status' => 'SUCCESS',
            'processed_by' => $adminId,
            'processed_at' => now(),
            'updated_at' => now(),
        ]);

        $this->auditService->log(
            $adminId, Auth::role(), $withdrawal['merchant_id'],
            'withdrawal_success',
            "Withdrawal #{$withdrawalId} marked as success",
            ['withdrawal_id' => $withdrawalId, 'amount' => $withdrawal['amount']]
        );

        return ['success' => true, 'message' => 'Withdrawal berhasil diproses.'];
    }

    /**
     * Reject withdrawal
     */
    public function reject(string $withdrawalId, string $adminId, string $reason = ''): array
    {
        $withdrawal = $this->withdrawalRepo->find($withdrawalId);
        if (!$withdrawal) {
            return ['success' => false, 'message' => 'Withdrawal tidak ditemukan.'];
        }
        if (!in_array($withdrawal['status'], ['PENDING', 'REVIEWING', 'APPROVED', 'PROCESSING'])) {
            return ['success' => false, 'message' => 'Status tidak dapat di-reject.'];
        }

        // Release hold back to available
        $this->walletService->releaseHoldBack(
            $withdrawal['merchant_id'], $withdrawal['amount'], $withdrawalId
        );

        $this->withdrawalRepo->update($withdrawalId, [
            'status' => 'REJECTED',
            'admin_note' => $reason,
            'processed_by' => $adminId,
            'processed_at' => now(),
            'updated_at' => now(),
        ]);

        $this->auditService->log(
            $adminId, Auth::role(), $withdrawal['merchant_id'],
            'withdrawal_rejected',
            "Withdrawal #{$withdrawalId} rejected: {$reason}",
            ['withdrawal_id' => $withdrawalId, 'reason' => $reason]
        );

        return ['success' => true, 'message' => 'Withdrawal ditolak dan saldo dikembalikan.'];
    }

    /**
     * Cancel withdrawal by merchant
     */
    public function cancel(string $withdrawalId, string $merchantId): array
    {
        $withdrawal = $this->withdrawalRepo->find($withdrawalId);
        if (!$withdrawal || $withdrawal['merchant_id'] !== $merchantId) {
            return ['success' => false, 'message' => 'Withdrawal tidak ditemukan.'];
        }
        if ($withdrawal['status'] !== 'PENDING') {
            return ['success' => false, 'message' => 'Hanya withdrawal PENDING yang bisa dibatalkan.'];
        }

        $this->walletService->releaseHoldBack($merchantId, $withdrawal['amount'], $withdrawalId);

        $this->withdrawalRepo->update($withdrawalId, [
            'status' => 'CANCELED',
            'updated_at' => now(),
        ]);

        return ['success' => true, 'message' => 'Withdrawal dibatalkan.'];
    }

    /**
     * Get withdrawals by merchant
     */
    public function getByMerchant(string $merchantId): array
    {
        return $this->withdrawalRepo->findByMerchant($merchantId);
    }

    /**
     * Get all withdrawals (admin)
     */
    public function getAll(array $filters = []): array
    {
        return $this->withdrawalRepo->findAll($filters);
    }

    /**
     * Find withdrawal by ID
     */
    public function find(string $id): ?array
    {
        return $this->withdrawalRepo->find($id);
    }
}
