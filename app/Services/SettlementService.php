<?php
/**
 * Settlement Service
 * Handles batch settlement processing
 */

require_once base_path('app/Repositories/SettlementRepository.php');
require_once base_path('app/Repositories/TransactionRepository.php');
require_once base_path('app/Services/AuditLogService.php');

class SettlementService
{
    private SettlementRepository $settlementRepo;
    private TransactionRepository $transactionRepo;
    private AuditLogService $auditService;

    public function __construct()
    {
        $this->settlementRepo = new SettlementRepository();
        $this->transactionRepo = new TransactionRepository();
        $this->auditService = new AuditLogService();
    }

    /**
     * Create settlement for merchant
     */
    public function create(string $merchantId, string $adminId, string $period = ''): array
    {
        if (empty($period)) {
            $period = date('Y-m');
        }

        // Get paid transactions for this merchant in the period
        $transactions = $this->transactionRepo->findByMerchant($merchantId, [
            'status' => 'PAID',
        ]);

        // Filter by period
        $filtered = array_filter($transactions, function($tx) use ($period) {
            return str_starts_with($tx['created_at'], $period);
        });

        if (empty($filtered)) {
            return ['success' => false, 'message' => 'Tidak ada transaksi PAID pada periode ini.'];
        }

        $totalGross = 0;
        $totalFee = 0;
        $totalNet = 0;
        $transactionIds = [];

        foreach ($filtered as $tx) {
            $totalGross += $tx['amount'];
            $totalFee += $tx['fee'];
            $totalNet += $tx['net_amount'];
            $transactionIds[] = $tx['id'];
        }

        $settlementId = generate_uuid();
        $settlement = [
            'id' => $settlementId,
            'merchant_id' => $merchantId,
            'period' => $period,
            'total_transactions' => count($transactionIds),
            'total_gross' => $totalGross,
            'total_fee' => $totalFee,
            'total_net' => $totalNet,
            'transaction_ids' => $transactionIds,
            'status' => 'PENDING',
            'created_by' => $adminId,
            'approved_by' => null,
            'approved_at' => null,
            'note' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->settlementRepo->create($settlement);

        $this->auditService->log(
            $adminId, Auth::role(), $merchantId,
            'settlement_created',
            "Settlement #{$settlementId} created for period {$period}, total " . format_currency($totalNet),
            ['settlement_id' => $settlementId, 'period' => $period, 'total_net' => $totalNet]
        );

        return ['success' => true, 'message' => 'Settlement berhasil dibuat.', 'settlement' => $settlement];
    }

    /**
     * Approve settlement
     */
    public function approve(string $settlementId, string $adminId): array
    {
        $settlement = $this->settlementRepo->find($settlementId);
        if (!$settlement) {
            return ['success' => false, 'message' => 'Settlement tidak ditemukan.'];
        }
        if ($settlement['status'] !== 'PENDING') {
            return ['success' => false, 'message' => 'Settlement tidak dalam status PENDING.'];
        }

        $this->settlementRepo->update($settlementId, [
            'status' => 'APPROVED',
            'approved_by' => $adminId,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        $this->auditService->log(
            $adminId, Auth::role(), $settlement['merchant_id'],
            'settlement_approved',
            "Settlement #{$settlementId} approved",
            ['settlement_id' => $settlementId]
        );

        return ['success' => true, 'message' => 'Settlement disetujui.'];
    }

    /**
     * Mark settlement as transferred
     */
    public function markTransferred(string $settlementId, string $adminId): array
    {
        $settlement = $this->settlementRepo->find($settlementId);
        if (!$settlement || $settlement['status'] !== 'APPROVED') {
            return ['success' => false, 'message' => 'Settlement tidak valid untuk transfer.'];
        }

        $this->settlementRepo->update($settlementId, [
            'status' => 'TRANSFERRED',
            'updated_at' => now(),
        ]);

        return ['success' => true, 'message' => 'Settlement ditandai sudah ditransfer.'];
    }

    /**
     * Mark settlement as completed
     */
    public function markCompleted(string $settlementId, string $adminId): array
    {
        $settlement = $this->settlementRepo->find($settlementId);
        if (!$settlement || !in_array($settlement['status'], ['APPROVED', 'TRANSFERRED'])) {
            return ['success' => false, 'message' => 'Settlement tidak valid untuk diselesaikan.'];
        }

        $this->settlementRepo->update($settlementId, [
            'status' => 'COMPLETED',
            'updated_at' => now(),
        ]);

        return ['success' => true, 'message' => 'Settlement selesai.'];
    }

    /**
     * Get all settlements
     */
    public function getAll(array $filters = []): array
    {
        return $this->settlementRepo->findAll($filters);
    }

    /**
     * Get settlements by merchant
     */
    public function getByMerchant(string $merchantId): array
    {
        return $this->settlementRepo->findByMerchant($merchantId);
    }

    /**
     * Find settlement
     */
    public function find(string $id): ?array
    {
        return $this->settlementRepo->find($id);
    }
}
