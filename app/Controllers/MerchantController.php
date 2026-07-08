<?php
/**
 * Merchant Controller
 * Handles merchant panel operations
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/Auth.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Repositories/WaConfigRepository.php');
require_once base_path('app/Services/TransactionService.php');
require_once base_path('app/Services/WalletService.php');
require_once base_path('app/Services/WithdrawalService.php');
require_once base_path('app/Services/AuditLogService.php');
require_once base_path('app/Services/ProjectService.php');
require_once base_path('app/Services/WhatsAppService.php');

class MerchantController
{
    private MerchantRepository $merchantRepo;
    private TransactionService $transactionService;
    private WalletService $walletService;
    private WithdrawalService $withdrawalService;
    private AuditLogService $auditService;
    private ProjectService $projectService;
    private WhatsAppService $waService;
    private WaConfigRepository $waConfigRepo;

    public function __construct()
    {
        $this->merchantRepo = new MerchantRepository();
        $this->transactionService = new TransactionService();
        $this->walletService = new WalletService();
        $this->withdrawalService = new WithdrawalService();
        $this->auditService = new AuditLogService();
        $this->projectService = new ProjectService();
        $this->waService = new WhatsAppService();
        $this->waConfigRepo = new WaConfigRepository();
    }

    // ==============================
    // PROJECTS (MULTI-STORE)
    // ==============================

    /**
     * List all projects owned by the current user.
     */
    public function listProjects(): array
    {
        return $this->projectService->listByUser(Auth::id());
    }

    /**
     * Create a new project (store).
     * Input: name (nama toko), webhook_url (opsional), ip_whitelist (opsional).
     */
    public function createProject(array $data): array
    {
        return $this->projectService->create(Auth::id(), $data);
    }

    /**
     * Switch the active project (no logout required).
     */
    public function switchProject(string $merchantId): array
    {
        return $this->projectService->switchActive(Auth::id(), $merchantId);
    }

    /**
     * Update a project's basic settings.
     */
    public function updateProject(string $merchantId, array $data): array
    {
        return $this->projectService->update(Auth::id(), $merchantId, $data);
    }

    /**
     * Get the currently active project.
     */
    public function getActiveProject(): ?array
    {
        return $this->projectService->getActive(Auth::id());
    }

    // ==============================
    // WHATSAPP INTEGRATION (per project)
    // ==============================

    /**
     * Get WA config for the active project.
     */
    public function getWaConfig(): ?array
    {
        return $this->waConfigRepo->findByMerchant(Auth::merchantId());
    }

    /**
     * Save (create/update) WA config for the active project.
     */
    public function saveWaConfig(array $data): array
    {
        $merchantId = Auth::merchantId();
        if (!$merchantId || !$this->projectService->userOwns(Auth::id(), $merchantId)) {
            return ['success' => false, 'message' => 'Proyek aktif tidak valid.'];
        }

        $provider = $data['provider'] ?? 'fonnte';
        $validProviders = ['fonnte', 'wablas', 'zenziva', 'custom'];
        if (!in_array($provider, $validProviders)) {
            return ['success' => false, 'message' => 'Provider WA tidak valid.'];
        }

        $apiUrl = trim($data['api_url'] ?? '');
        if ($apiUrl === '' || !filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'API URL WA tidak valid.'];
        }
        if (trim($data['api_key'] ?? '') === '') {
            return ['success' => false, 'message' => 'API Key WA wajib diisi.'];
        }

        $payload = [
            'provider' => $provider,
            'api_url' => $apiUrl,
            'api_key' => trim($data['api_key']),
            'api_secret' => trim($data['api_secret'] ?? ''),
            'sender_number' => trim($data['sender_number'] ?? ''),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'notify_on_payment' => isset($data['notify_on_payment']) ? 1 : 0,
            'notify_on_withdrawal' => isset($data['notify_on_withdrawal']) ? 1 : 0,
            'notify_on_expiry' => isset($data['notify_on_expiry']) ? 1 : 0,
            'notify_admin_number' => trim($data['notify_admin_number'] ?? ''),
            'message_template_payment' => trim($data['message_template_payment'] ?? ''),
            'message_template_withdrawal' => trim($data['message_template_withdrawal'] ?? ''),
        ];

        $this->waConfigRepo->upsert($merchantId, $payload);

        $this->auditService->log(
            Auth::id(), Auth::role(), $merchantId,
            'wa_config_updated', "Konfigurasi WhatsApp ({$provider}) diperbarui", ['provider' => $provider]
        );

        return ['success' => true, 'message' => 'Konfigurasi WhatsApp berhasil disimpan.'];
    }

    /**
     * Send a test WA message for the active project.
     */
    public function testWa(string $testPhone): array
    {
        $merchantId = Auth::merchantId();
        if (!$merchantId || !$this->projectService->userOwns(Auth::id(), $merchantId)) {
            return ['success' => false, 'message' => 'Proyek aktif tidak valid.'];
        }
        if (trim($testPhone) === '') {
            return ['success' => false, 'message' => 'Nomor tujuan test wajib diisi.'];
        }

        $result = $this->waService->testConnection($merchantId, $testPhone);
        if ($result['success'] ?? false) {
            return ['success' => true, 'message' => 'Test WA berhasil dikirim!'];
        }
        return ['success' => false, 'message' => 'Gagal kirim WA: ' . ($result['error'] ?? 'Unknown error')];
    }

    /**
     * Dashboard data
     */
    public function dashboard(): array
    {
        $merchantId = Auth::merchantId();
        $merchant = $this->merchantRepo->find($merchantId);
        $stats = $this->transactionService->getMerchantStats($merchantId);
        $wallet = $this->walletService->getByMerchant($merchantId);
        $recentTx = (new \TransactionRepository())->getRecent(5, $merchantId);

        return [
            'merchant' => $merchant,
            'stats' => $stats,
            'wallet' => $wallet,
            'recent_transactions' => $recentTx,
        ];
    }

    /**
     * Create payment transaction
     */
    public function createPayment(array $data): array
    {
        $merchantId = Auth::merchantId();
        return $this->transactionService->create($data, $merchantId);
    }

    /**
     * Get merchant transactions
     */
    public function transactions(array $filters = []): array
    {
        return $this->transactionService->getByMerchant(Auth::merchantId(), $filters);
    }

    /**
     * Get transaction detail
     */
    public function transactionDetail(string $id): ?array
    {
        $tx = $this->transactionService->find($id);
        if ($tx && $tx['merchant_id'] === Auth::merchantId()) {
            return $tx;
        }
        return null;
    }

    /**
     * Get wallet info
     */
    public function wallet(): array
    {
        $wallet = $this->walletService->getByMerchant(Auth::merchantId());
        $ledger = $this->walletService->getLedger(Auth::merchantId(), 50);
        return ['wallet' => $wallet, 'ledger' => $ledger];
    }

    /**
     * Request withdrawal
     */
    public function requestWithdrawal(array $data): array
    {
        return $this->withdrawalService->request($data, Auth::merchantId());
    }

    /**
     * Cancel withdrawal
     */
    public function cancelWithdrawal(string $withdrawalId): array
    {
        return $this->withdrawalService->cancel($withdrawalId, Auth::merchantId());
    }

    /**
     * Get withdrawal history
     */
    public function withdrawalHistory(): array
    {
        return $this->withdrawalService->getByMerchant(Auth::merchantId());
    }

    /**
     * Get/regenerate API keys
     */
    public function getApiKey(): ?string
    {
        $merchant = $this->merchantRepo->find(Auth::merchantId());
        return $merchant['api_key'] ?? null;
    }

    /**
     * Regenerate API key
     */
    public function regenerateApiKey(): array
    {
        $merchantId = Auth::merchantId();
        $newKey = $this->merchantRepo->regenerateApiKey($merchantId);

        $this->auditService->log(
            Auth::id(), Auth::role(), $merchantId,
            'api_key_regenerated', 'API key regenerated', []
        );

        return ['success' => true, 'message' => 'API key berhasil dibuat ulang.', 'api_key' => $newKey];
    }

    /**
     * Update webhook settings
     */
    public function updateWebhookSettings(array $data): array
    {
        $merchantId = Auth::merchantId();
        $updates = [
            'webhook_url' => sanitize($data['webhook_url'] ?? ''),
            'redirect_url' => sanitize($data['redirect_url'] ?? ''),
            'updated_at' => now(),
        ];

        $this->merchantRepo->update($merchantId, $updates);

        $this->auditService->log(
            Auth::id(), Auth::role(), $merchantId,
            'settings_changed', 'Webhook settings updated',
            $updates
        );

        return ['success' => true, 'message' => 'Pengaturan webhook berhasil disimpan.'];
    }

    /**
     * Update profile
     */
    public function updateProfile(array $data): array
    {
        $merchantId = Auth::merchantId();
        $userId = Auth::id();

        // Update merchant info
        $merchantUpdates = array_filter([
            'business_name' => sanitize($data['business_name'] ?? ''),
            'phone' => sanitize($data['phone'] ?? ''),
            'updated_at' => now(),
        ]);
        $this->merchantRepo->update($merchantId, $merchantUpdates);

        // Update user name
        require_once base_path('app/Repositories/UserRepository.php');
        $userRepo = new UserRepository();
        $userUpdates = ['name' => sanitize($data['name'] ?? ''), 'updated_at' => now()];
        
        // Password change
        if (!empty($data['new_password'])) {
            if (strlen($data['new_password']) < 8) {
                return ['success' => false, 'message' => 'Password baru minimal 8 karakter.'];
            }
            if ($data['new_password'] !== ($data['password_confirm'] ?? '')) {
                return ['success' => false, 'message' => 'Konfirmasi password tidak cocok.'];
            }
            // Verify current password
            $user = $userRepo->find($userId);
            if (!password_verify($data['current_password'] ?? '', $user['password_hash'])) {
                return ['success' => false, 'message' => 'Password saat ini salah.'];
            }
            $userUpdates['password_hash'] = password_hash($data['new_password'], PASSWORD_DEFAULT);
        }

        $userRepo->update($userId, $userUpdates);
        $_SESSION['user_name'] = $userUpdates['name'];

        return ['success' => true, 'message' => 'Profil berhasil diperbarui.'];
    }

    /**
     * Get merchant info
     */
    public function getMerchant(): ?array
    {
        return $this->merchantRepo->find(Auth::merchantId());
    }
}
