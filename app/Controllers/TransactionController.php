<?php
/**
 * Transaction Controller
 * Handles transaction-related actions
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/Auth.php');
require_once base_path('app/Services/TransactionService.php');

class TransactionController
{
    private TransactionService $transactionService;

    public function __construct()
    {
        $this->transactionService = new TransactionService();
    }

    /**
     * Create transaction (from form)
     */
    public function create(): void
    {
        Auth::requireMerchant();
        Auth::verifyCsrf();

        $data = [
            'order_id' => sanitize($_POST['order_id'] ?? ''),
            'amount' => (int)($_POST['amount'] ?? 0),
            'link_name' => sanitize($_POST['link_name'] ?? ''),
            'customer_name' => sanitize($_POST['customer_name'] ?? ''),
            'customer_wa' => sanitize($_POST['customer_wa'] ?? ''),
            'customer_email' => sanitize($_POST['customer_email'] ?? ''),
            'webhook_url' => sanitize($_POST['webhook_url'] ?? ''),
            'redirect_url' => sanitize($_POST['redirect_url'] ?? ''),
            'note' => sanitize($_POST['note'] ?? ''),
        ];

        $result = $this->transactionService->create($data, Auth::merchantId());

        if ($result['success']) {
            flash('success', $result['message']);
            if (is_ajax()) {
                json_response($result);
            }
            redirect('/merchant/transactions.php');
        } else {
            flash('error', $result['message']);
            if (is_ajax()) {
                json_response($result, 400);
            }
            redirect('/merchant/create-payment.php');
        }
    }

    /**
     * Get transaction detail (AJAX)
     */
    public function detail(string $id): void
    {
        $tx = $this->transactionService->find($id);
        
        if (!$tx) {
            json_response(['error' => 'Transaction not found'], 404);
        }

        // Access control
        if (Auth::isMerchant() && $tx['merchant_id'] !== Auth::merchantId()) {
            json_response(['error' => 'Forbidden'], 403);
        }

        json_response(['success' => true, 'transaction' => $tx]);
    }
}
