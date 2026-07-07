<?php
/**
 * Disbursement Service
 * Auto-transfer withdrawal funds to merchant bank accounts via API
 * 
 * Flow: Withdrawal APPROVED → Disbursement created → API call to bank → 
 *       Callback received → Withdrawal marked SUCCESS
 */

require_once base_path('app/Database.php');
require_once base_path('app/Services/AuditLogService.php');

class DisbursementService
{
    private PDO $db;
    private AuditLogService $audit;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->audit = new AuditLogService();
    }

    /**
     * Create disbursement for approved withdrawal
     */
    public function create(array $withdrawal): array
    {
        $apiUrl = setting('disbursement_api_url', '');
        $apiKey = setting('disbursement_api_key', '');
        $enabled = setting('disbursement_enabled', '0') === '1';

        if (!$enabled || empty($apiUrl)) {
            return ['success' => false, 'message' => 'Disbursement not enabled/configured', 'auto' => false];
        }

        $disbId = generate_uuid();
        $payload = [
            'external_id' => $disbId,
            'amount' => (int)$withdrawal['amount'],
            'bank_code' => strtoupper($withdrawal['bank_name'] ?? ''),
            'account_number' => $withdrawal['account_number'] ?? '',
            'account_name' => $withdrawal['account_name'] ?? '',
            'description' => "Withdrawal #{$withdrawal['id']}",
        ];


        // Call disbursement API
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $responseData = json_decode($response, true) ?: [];
        $success = ($httpCode >= 200 && $httpCode < 300);

        // Save disbursement record
        $record = [
            'id' => $disbId,
            'withdrawal_id' => $withdrawal['id'],
            'merchant_id' => $withdrawal['merchant_id'],
            'amount' => (int)$withdrawal['amount'],
            'bank_code' => $payload['bank_code'],
            'account_number' => $payload['account_number'],
            'account_name' => $payload['account_name'],
            'status' => $success ? 'processing' : 'failed',
            'provider_reference' => $responseData['id'] ?? $responseData['reference_id'] ?? null,
            'provider_status' => $responseData['status'] ?? null,
            'request_payload' => json_encode($payload),
            'response_payload' => $response,
            'http_code' => $httpCode,
            'error_message' => $error ?: ($responseData['message'] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $cols = implode(',', array_map(fn($k) => "`{$k}`", array_keys($record)));
        $vals = implode(',', array_map(fn($k) => ":{$k}", array_keys($record)));
        $this->db->prepare("INSERT INTO `disbursements` ({$cols}) VALUES ({$vals})")->execute($record);

        $this->audit->log('system', 'system', $withdrawal['merchant_id'], 'disbursement_created',
            "Disbursement created: " . format_currency($withdrawal['amount']) . " to {$payload['bank_code']} {$payload['account_number']}",
            ['disbursement_id' => $disbId, 'http_code' => $httpCode, 'success' => $success]);

        return ['success' => $success, 'message' => $success ? 'Disbursement submitted' : ($error ?: 'API error'), 'auto' => true, 'disbursement_id' => $disbId];
    }

    /**
     * Handle disbursement callback
     */
    public function handleCallback(array $data): bool
    {
        $externalId = $data['external_id'] ?? $data['disbursement_id'] ?? '';
        $status = strtolower($data['status'] ?? '');

        if (empty($externalId)) return false;

        $this->db->prepare("UPDATE `disbursements` SET `status`=:status, `provider_status`=:ps, `updated_at`=:now WHERE `id`=:id")
            ->execute(['status' => $status, 'ps' => $data['status'] ?? '', 'now' => now(), 'id' => $externalId]);

        // If completed, mark withdrawal as SUCCESS
        if (in_array($status, ['completed', 'success', 'settled'])) {
            $stmt = $this->db->prepare("SELECT withdrawal_id, merchant_id FROM `disbursements` WHERE `id`=:id");
            $stmt->execute(['id' => $externalId]);
            $disb = $stmt->fetch();
            if ($disb) {
                require_once base_path('app/Services/WithdrawalService.php');
                $wdService = new WithdrawalService();
                $wdService->markSuccess($disb['withdrawal_id'], 'system');
            }
        }
        return true;
    }

    /**
     * Get disbursements by merchant
     */
    public function getByMerchant(string $merchantId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM `disbursements` WHERE `merchant_id`=:mid ORDER BY `created_at` DESC");
        $stmt->execute(['mid' => $merchantId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get all disbursements (admin)
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM `disbursements` ORDER BY `created_at` DESC LIMIT 200");
        return $stmt->fetchAll() ?: [];
    }
}
