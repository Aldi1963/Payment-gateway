<?php
/**
 * Wallet Controller
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/Auth.php');
require_once base_path('app/Services/WalletService.php');

class WalletController
{
    private WalletService $walletService;

    public function __construct()
    {
        $this->walletService = new WalletService();
    }

    /**
     * Get wallet info (AJAX)
     */
    public function info(): void
    {
        Auth::requireMerchant();
        $wallet = $this->walletService->getByMerchant(Auth::merchantId());
        json_response(['success' => true, 'wallet' => $wallet]);
    }

    /**
     * Get ledger (AJAX)
     */
    public function ledger(): void
    {
        Auth::requireMerchant();
        $ledger = $this->walletService->getLedger(Auth::merchantId());
        json_response(['success' => true, 'ledger' => $ledger]);
    }
}
