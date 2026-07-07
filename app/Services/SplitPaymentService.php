<?php
/**
 * Split Payment Service
 * Splits a single transaction's net amount across multiple recipients
 * Use case: Marketplace model where platform takes a cut
 * 
 * Example: Customer pays Rp100.000
 *   - Platform fee: Rp5.000
 *   - Seller A: Rp60.000  
 *   - Seller B: Rp35.000
 */

require_once base_path('app/Database.php');

class SplitPaymentService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Create split payment rules for a transaction
     */
    public function createSplit(string $transactionId, array $recipients): array
    {
        if (empty($recipients)) {
            return ['success' => false, 'message' => 'Recipients wajib diisi.'];
        }

        // Validate total percentages or amounts
        $totalPct = array_sum(array_column($recipients, 'percentage'));
        $totalFixed = array_sum(array_column($recipients, 'fixed_amount'));

        if ($totalPct > 100) {
            return ['success' => false, 'message' => 'Total persentase tidak boleh lebih dari 100%.'];
        }

        foreach ($recipients as $recipient) {
            $id = generate_uuid();
            $this->db->prepare("INSERT INTO `split_payments` (`id`,`transaction_id`,`recipient_merchant_id`,`recipient_name`,`split_type`,`percentage`,`fixed_amount`,`calculated_amount`,`status`,`created_at`) VALUES (:id,:tid,:rmid,:rn,:st,:pct,:fa,:ca,:status,:ca2)")
                ->execute([
                    'id' => $id,
                    'tid' => $transactionId,
                    'rmid' => $recipient['merchant_id'] ?? null,
                    'rn' => sanitize($recipient['name'] ?? ''),
                    'st' => $recipient['split_type'] ?? 'percentage', // percentage or fixed
                    'pct' => (float)($recipient['percentage'] ?? 0),
                    'fa' => (int)($recipient['fixed_amount'] ?? 0),
                    'ca' => 0, // calculated later when transaction is paid
                    'status' => 'pending',
                    'ca2' => now(),
                ]);
        }

        return ['success' => true, 'message' => 'Split payment rules created.'];
    }

    /**
     * Process split when transaction is paid
     * Distributes net_amount to recipients' wallets
     */
    public function processSplit(string $transactionId, int $netAmount): void
    {
        $stmt = $this->db->prepare("SELECT * FROM `split_payments` WHERE `transaction_id`=:tid AND `status`='pending'");
        $stmt->execute(['tid' => $transactionId]);
        $splits = $stmt->fetchAll() ?: [];

        foreach ($splits as $split) {
            $amount = 0;
            if ($split['split_type'] === 'percentage') {
                $amount = (int)round($netAmount * (float)$split['percentage'] / 100);
            } else {
                $amount = (int)$split['fixed_amount'];
            }

            // Credit recipient wallet if merchant_id is set
            if (!empty($split['recipient_merchant_id']) && $amount > 0) {
                require_once base_path('app/Services/WalletService.php');
                $walletService = new WalletService();
                // Direct credit using repository
                require_once base_path('app/Repositories/WalletRepository.php');
                $walletRepo = new WalletRepository();
                $wallet = $walletRepo->findByMerchant($split['recipient_merchant_id']);
                if ($wallet) {
                    $walletRepo->update($wallet['id'], [
                        'available_balance' => (int)$wallet['available_balance'] + $amount,
                        'total_received' => (int)$wallet['total_received'] + $amount,
                        'updated_at' => now(),
                    ]);
                    $walletRepo->addLedgerEntry([
                        'id' => generate_uuid(),
                        'merchant_id' => $split['recipient_merchant_id'],
                        'transaction_id' => $transactionId,
                        'type' => 'credit',
                        'amount' => $amount,
                        'balance_before' => (int)$wallet['available_balance'],
                        'balance_after' => (int)$wallet['available_balance'] + $amount,
                        'description' => "Split payment from tx #{$transactionId}",
                        'created_at' => now(),
                    ]);
                }
            }

            // Update split record
            $this->db->prepare("UPDATE `split_payments` SET `calculated_amount`=:amt, `status`='completed', `processed_at`=:now WHERE `id`=:id")
                ->execute(['amt' => $amount, 'now' => now(), 'id' => $split['id']]);
        }
    }

    /**
     * Get splits for a transaction
     */
    public function getByTransaction(string $transactionId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `split_payments` WHERE `transaction_id`=:tid");
        $stmt->execute(['tid' => $transactionId]);
        return $stmt->fetchAll() ?: [];
    }
}
