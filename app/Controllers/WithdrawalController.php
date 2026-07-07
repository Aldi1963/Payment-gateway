<?php
/**
 * Withdrawal Controller
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/Auth.php');
require_once base_path('app/Services/WithdrawalService.php');

class WithdrawalController
{
    private WithdrawalService $withdrawalService;

    public function __construct()
    {
        $this->withdrawalService = new WithdrawalService();
    }

    /**
     * Request withdrawal (merchant)
     */
    public function request(): void
    {
        Auth::requireMerchant();
        Auth::verifyCsrf();

        $data = [
            'amount' => (int)($_POST['amount'] ?? 0),
            'bank_name' => sanitize($_POST['bank_name'] ?? ''),
            'account_number' => sanitize($_POST['account_number'] ?? ''),
            'account_name' => sanitize($_POST['account_name'] ?? ''),
            'note' => sanitize($_POST['note'] ?? ''),
        ];

        $result = $this->withdrawalService->request($data, Auth::merchantId());

        if (is_ajax()) {
            json_response($result, $result['success'] ? 200 : 400);
        }

        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('/merchant/withdraw-history.php');
    }

    /**
     * Cancel withdrawal (merchant)
     */
    public function cancel(): void
    {
        Auth::requireMerchant();
        Auth::verifyCsrf();

        $withdrawalId = $_POST['withdrawal_id'] ?? '';
        $result = $this->withdrawalService->cancel($withdrawalId, Auth::merchantId());

        if (is_ajax()) {
            json_response($result, $result['success'] ? 200 : 400);
        }

        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('/merchant/withdraw-history.php');
    }

    /**
     * Process withdrawal action (admin)
     */
    public function process(): void
    {
        Auth::requireRole(['super_admin', 'admin', 'finance']);
        Auth::verifyCsrf();

        $withdrawalId = $_POST['withdrawal_id'] ?? '';
        $action = $_POST['action'] ?? '';
        $note = sanitize($_POST['admin_note'] ?? '');

        $result = match($action) {
            'approve' => $this->withdrawalService->approve($withdrawalId, Auth::id(), $note),
            'reject' => $this->withdrawalService->reject($withdrawalId, Auth::id(), $note),
            'success' => $this->withdrawalService->markSuccess($withdrawalId, Auth::id()),
            default => ['success' => false, 'message' => 'Aksi tidak valid.'],
        };

        if (is_ajax()) {
            json_response($result, $result['success'] ? 200 : 400);
        }

        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('/admin/withdrawals.php');
    }
}
