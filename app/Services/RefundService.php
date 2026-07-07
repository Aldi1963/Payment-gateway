<?php
/**
 * Refund Service
 * Handles partial and full refunds for paid transactions
 * 
 * Flow:
 * 1. Merchant/Admin initiates refund
 * 2. Validate: transaction is PAID, amount <= remaining refundable
 * 3. Create refund record
 * 4. Debit from merchant wallet
 * 5. Update transaction refund status
 */

require_once base_path('app/Repositories/TransactionRepository.php');
require_once base_path('app/Services/WalletService.php');
require_once base_path('app/Services/AuditLogService.php');
require_once base_path('app/Database.php');

class RefundService
{
    private TransactionRepository $txRepo;
    private WalletService $walletService;
    private AuditLogService $auditService;
    private PDO $db;

    public function __construct()
    {
        $this->txRepo = new TransactionRepository();
        $this->walletService = new WalletService();
        $this->auditService = new AuditLogService();
        $this->db = Database::getConnection();
    }

    /**
     * Initiate a refund
     */
    public function create(array $data): array
    {
        $txId = $data['transaction_id'] ?? '';
        $amount = (int)($data['amount'] ?? 0);
        $reason = sanitize($data['reason'] ?? '');
        $initiatedBy = $data['initiated_by'] ?? '';
        $initiatedByRole = $data['initiated_by_role'] ?? '';

        $tx = $this->txRepo->find($txId);
        if (!$tx) {
            return ['success' => false, 'message' => 'Transaksi tidak ditemukan.'];
        }
        if ($tx['status'] !== 'PAID') {
            return ['success' => false, 'message' => 'Hanya transaksi PAID yang bisa di-refund.'];
        }
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Jumlah refund harus lebih dari 0.'];
        }

        // Calculate total already refunded
        $existingRefunds = $this->getByTransaction($txId);
        $totalRefunded = array_sum(array_map(fn($r) => ($r['status'] === 'completed') ? (int)$r['amount'] : 0, $existingRefunds));
        $maxRefundable = (int)$tx['net_amount'] - $totalRefunded;

        if ($amount > $maxRefundable) {
            return ['success' => false, 'message' => 'Jumlah refund melebihi sisa yang bisa di-refund (' . format_currency($maxRefundable) . ').'];
        }

        // Check merchant wallet has enough balance
        $wallet = $this->walletService->getByMerchant($tx['merchant_id']);
        if (!$wallet || (int)$wallet['available_balance'] < $amount) {
            return ['success' => false, 'message' => 'Saldo merchant tidak cukup untuk refund.'];
        }

        // Create refund record
        $refundId = generate_uuid();
        $refund = [
            'id' => $refundId,
            'transaction_id' => $txId,
            'order_id' => $tx['order_id'],
            'merchant_id' => $tx['merchant_id'],
            'amount' => $amount,
            'reason' => $reason,
            'type' => ($amount >= $maxRefundable) ? 'full' : 'partial',
            'status' => 'completed',
            'initiated_by' => $initiatedBy,
            'initiated_by_role' => $initiatedByRole,
            'created_at' => now(),
        ];

        // Debit from merchant wallet
        require_once base_path('app/Repositories/WalletRepository.php');
        $walletRepo = new WalletRepository();
        $walletData = $walletRepo->findByMerchant($tx['merchant_id']);

        $balanceBefore = (int)$walletData['available_balance'];
        $walletRepo->update($walletData['id'], [
            'available_balance' => $balanceBefore - $amount,
            'updated_at' => now(),
        ]);

        // Ledger entry
        $walletRepo->addLedgerEntry([
            'id' => generate_uuid(),
            'merchant_id' => $tx['merchant_id'],
            'transaction_id' => $refundId,
            'type' => 'debit',
            'amount' => -$amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore - $amount,
            'description' => "Refund for order {$tx['order_id']}: " . format_currency($amount),
            'created_at' => now(),
        ]);

        // Save refund to DB
        $stmt = $this->db->prepare("INSERT INTO `refunds` (`id`,`transaction_id`,`order_id`,`merchant_id`,`amount`,`reason`,`type`,`status`,`initiated_by`,`initiated_by_role`,`created_at`) VALUES (:id,:transaction_id,:order_id,:merchant_id,:amount,:reason,:type,:status,:initiated_by,:initiated_by_role,:created_at)");
        $stmt->execute($refund);

        // Update transaction refund info
        $totalRefundedNow = $totalRefunded + $amount;
        $refundStatus = ($totalRefundedNow >= (int)$tx['net_amount']) ? 'REFUNDED' : 'PARTIAL_REFUND';
        $this->txRepo->update($txId, [
            'refund_amount' => $totalRefundedNow,
            'refund_status' => $refundStatus,
            'status' => ($refundStatus === 'REFUNDED') ? 'REFUNDED' : $tx['status'],
            'updated_at' => now(),
        ]);

        // Audit
        $this->auditService->log(
            $initiatedBy, $initiatedByRole, $tx['merchant_id'],
            'refund_created',
            "Refund {$refund['type']} " . format_currency($amount) . " for order {$tx['order_id']}",
            ['refund_id' => $refundId, 'transaction_id' => $txId, 'amount' => $amount, 'reason' => $reason]
        );

        return [
            'success' => true,
            'message' => 'Refund berhasil diproses. ' . format_currency($amount) . ' telah dikembalikan.',
            'refund' => $refund,
        ];
    }

    /**
     * Get refunds for a transaction
     */
    public function getByTransaction(string $transactionId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `refunds` WHERE `transaction_id` = :tid ORDER BY `created_at` DESC");
        $stmt->execute(['tid' => $transactionId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get refunds for a merchant
     */
    public function getByMerchant(string $merchantId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `refunds` WHERE `merchant_id` = :mid ORDER BY `created_at` DESC");
        $stmt->execute(['mid' => $merchantId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get all refunds (admin)
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM `refunds` ORDER BY `created_at` DESC");
        return $stmt->fetchAll() ?: [];
    }
}
