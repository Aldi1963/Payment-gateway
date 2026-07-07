<?php
/**
 * Wallet Service
 * Manages merchant wallet balances and ledger entries
 */

require_once base_path('app/Repositories/WalletRepository.php');

class WalletService
{
    private WalletRepository $walletRepo;

    public function __construct()
    {
        $this->walletRepo = new WalletRepository();
    }

    /**
     * Credit wallet when transaction is paid
     */
    public function creditTransaction(array $transaction): bool
    {
        $wallet = $this->walletRepo->findByMerchant($transaction['merchant_id']);
        if (!$wallet) {
            app_log("Wallet not found for merchant: {$transaction['merchant_id']}", 'ERROR');
            return false;
        }

        $amount = $transaction['net_amount'] ?? ($transaction['amount'] - ($transaction['fee'] ?? 0));
        $fee = $transaction['fee'] ?? 0;

        $balanceBefore = $wallet['available_balance'];
        $balanceAfter = $balanceBefore + $amount;

        // Update wallet balances
        $this->walletRepo->update($wallet['id'], [
            'available_balance' => $balanceAfter,
            'total_received' => $wallet['total_received'] + $transaction['amount'],
            'total_fee' => $wallet['total_fee'] + $fee,
            'updated_at' => now(),
        ]);

        // Create ledger entry
        $this->walletRepo->addLedgerEntry([
            'id' => generate_uuid(),
            'merchant_id' => $transaction['merchant_id'],
            'transaction_id' => $transaction['id'],
            'type' => 'credit',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => "Payment received for order {$transaction['order_id']}",
            'created_at' => now(),
        ]);

        // Fee ledger entry
        if ($fee > 0) {
            $this->walletRepo->addLedgerEntry([
                'id' => generate_uuid(),
                'merchant_id' => $transaction['merchant_id'],
                'transaction_id' => $transaction['id'],
                'type' => 'fee',
                'amount' => -$fee,
                'balance_before' => $balanceBefore + $transaction['amount'],
                'balance_after' => $balanceAfter,
                'description' => "Fee deducted for order {$transaction['order_id']}",
                'created_at' => now(),
            ]);
        }

        app_log("Wallet credited for merchant {$transaction['merchant_id']}: +{$amount} (fee: {$fee})", 'INFO');
        return true;
    }

    /**
     * Hold balance for withdrawal
     */
    public function holdForWithdrawal(string $merchantId, float $amount, string $withdrawalId): bool
    {
        $wallet = $this->walletRepo->findByMerchant($merchantId);
        if (!$wallet) return false;

        if ($wallet['available_balance'] < $amount) {
            return false;
        }

        $balanceBefore = $wallet['available_balance'];
        $this->walletRepo->update($wallet['id'], [
            'available_balance' => $wallet['available_balance'] - $amount,
            'hold_balance' => $wallet['hold_balance'] + $amount,
            'updated_at' => now(),
        ]);

        $this->walletRepo->addLedgerEntry([
            'id' => generate_uuid(),
            'merchant_id' => $merchantId,
            'transaction_id' => $withdrawalId,
            'type' => 'hold',
            'amount' => -$amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore - $amount,
            'description' => "Hold for withdrawal #{$withdrawalId}",
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Release hold (withdrawal success)
     */
    public function releaseHoldSuccess(string $merchantId, float $amount, string $withdrawalId): bool
    {
        $wallet = $this->walletRepo->findByMerchant($merchantId);
        if (!$wallet) return false;

        $this->walletRepo->update($wallet['id'], [
            'hold_balance' => $wallet['hold_balance'] - $amount,
            'withdrawn_balance' => $wallet['withdrawn_balance'] + $amount,
            'updated_at' => now(),
        ]);

        $this->walletRepo->addLedgerEntry([
            'id' => generate_uuid(),
            'merchant_id' => $merchantId,
            'transaction_id' => $withdrawalId,
            'type' => 'withdrawal',
            'amount' => -$amount,
            'balance_before' => $wallet['hold_balance'],
            'balance_after' => $wallet['hold_balance'] - $amount,
            'description' => "Withdrawal completed #{$withdrawalId}",
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Release hold back to available (withdrawal rejected/failed)
     */
    public function releaseHoldBack(string $merchantId, float $amount, string $withdrawalId): bool
    {
        $wallet = $this->walletRepo->findByMerchant($merchantId);
        if (!$wallet) return false;

        $balanceBefore = $wallet['available_balance'];
        $this->walletRepo->update($wallet['id'], [
            'available_balance' => $wallet['available_balance'] + $amount,
            'hold_balance' => $wallet['hold_balance'] - $amount,
            'updated_at' => now(),
        ]);

        $this->walletRepo->addLedgerEntry([
            'id' => generate_uuid(),
            'merchant_id' => $merchantId,
            'transaction_id' => $withdrawalId,
            'type' => 'release',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore + $amount,
            'description' => "Hold released back for withdrawal #{$withdrawalId}",
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Get wallet by merchant
     */
    public function getByMerchant(string $merchantId): ?array
    {
        return $this->walletRepo->findByMerchant($merchantId);
    }

    /**
     * Get wallet ledger entries
     */
    public function getLedger(string $merchantId, int $limit = 50): array
    {
        return $this->walletRepo->getLedger($merchantId, $limit);
    }
}
