<?php
/**
 * Project Service
 * Manages multiple projects (stores/merchants) per user.
 *
 * A "project" is a merchant record owned by a user. One user can own many.
 * New projects start as 'pending' and must be verified by an admin before
 * they can process real payments.
 *
 * Flow:
 *   - listByUser()     -> table of Nama | Slug | Status
 *   - create()         -> asks for name, webhook_url, ip_whitelist (status=pending)
 *   - switchActive()   -> change active project in session (no logout)
 *   - getActive()      -> currently selected project
 */

require_once base_path('app/Schema.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Repositories/UserMerchantRepository.php');
require_once base_path('app/Repositories/WalletRepository.php');
require_once base_path('app/Repositories/UserRepository.php');
require_once base_path('app/Services/AuditLogService.php');

class ProjectService
{
    private MerchantRepository $merchantRepo;
    private UserMerchantRepository $userMerchantRepo;
    private WalletRepository $walletRepo;
    private AuditLogService $auditService;

    public function __construct()
    {
        $this->merchantRepo = new MerchantRepository();
        $this->userMerchantRepo = new UserMerchantRepository();
        $this->walletRepo = new WalletRepository();
        $this->auditService = new AuditLogService();
    }

    /**
     * Whether the multi-project database schema has been migrated.
     * When false, the app runs in legacy single-merchant mode.
     */
    public function isMigrated(): bool
    {
        return Schema::multiProjectReady();
    }

    /**
     * Standard error payload for when the DB hasn't been migrated yet.
     */
    private function notMigratedError(): array
    {
        return [
            'success' => false,
            'not_migrated' => true,
            'message' => 'Database belum dimigrasi untuk fitur multi-proyek. Jalankan: php scripts/migrate.php',
        ];
    }

    /**
     * Legacy fallback: return the user's single merchant (users.merchant_id)
     * as a one-item project list when the pivot table is not available.
     */
    private function legacyProjects(string $userId): array
    {
        try {
            $user = (new UserRepository())->find($userId);
            $merchantId = $user['merchant_id'] ?? null;
            if (!$merchantId) return [];
            $merchant = $this->merchantRepo->find($merchantId);
            if (!$merchant) return [];
            $merchant['access_role'] = 'owner';
            $merchant['is_default'] = 1;
            $merchant['slug'] = $merchant['slug'] ?? '';
            return [$merchant];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * List all projects owned/accessible by a user.
     * Returns merchant rows with access_role and is_default.
     * Falls back to legacy single-merchant mode if not migrated.
     */
    public function listByUser(string $userId): array
    {
        if (!$this->isMigrated()) {
            return $this->legacyProjects($userId);
        }
        return $this->userMerchantRepo->getProjectsForUser($userId);
    }

    /**
     * Count projects owned by a user.
     */
    public function countByUser(string $userId): int
    {
        if (!$this->isMigrated()) {
            return count($this->legacyProjects($userId));
        }
        return $this->userMerchantRepo->countForUser($userId);
    }

    /**
     * Create a new project for a user.
     *
     * @param string $userId Owner user id
     * @param array  $data   ['name' => required, 'webhook_url' => optional, 'ip_whitelist' => optional, 'phone' => optional]
     * @return array ['success' => bool, 'message' => string, 'project' => ?array]
     */
    public function create(string $userId, array $data): array
    {
        // Guard: multi-project schema must be migrated
        if (!$this->isMigrated()) {
            return $this->notMigratedError();
        }

        $name = trim($data['name'] ?? $data['business_name'] ?? '');

        // 1. Validate name
        if ($name === '') {
            return ['success' => false, 'message' => 'Nama toko wajib diisi.'];
        }
        if (mb_strlen($name) > 255) {
            return ['success' => false, 'message' => 'Nama toko terlalu panjang (maks 255 karakter).'];
        }

        // 2. Enforce per-user project limit
        $maxProjects = (int)setting('max_projects_per_user', 20);
        if ($this->countByUser($userId) >= $maxProjects) {
            return ['success' => false, 'message' => "Batas maksimal {$maxProjects} proyek per akun telah tercapai."];
        }

        // 3. Validate webhook URL if provided
        $webhookUrl = trim($data['webhook_url'] ?? '');
        if ($webhookUrl !== '' && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'Format Webhook URL tidak valid.'];
        }

        // 4. Validate & normalize IP whitelist (newline or comma separated)
        $ipWhitelist = $this->normalizeIpWhitelist($data['ip_whitelist'] ?? '');
        if ($ipWhitelist === false) {
            return ['success' => false, 'message' => 'Terdapat IP address yang tidak valid pada whitelist.'];
        }

        // 5. Generate unique slug from name
        $slug = $this->generateUniqueSlug($name);

        // 6. Fetch owner info for merchant record
        require_once base_path('app/Repositories/UserRepository.php');
        $userRepo = new UserRepository();
        $owner = $userRepo->find($userId);
        if (!$owner) {
            return ['success' => false, 'message' => 'User tidak ditemukan.'];
        }

        // 7. Determine initial status (admin verification requirement)
        $requireVerification = setting('require_admin_verification', '1') === '1';
        $status = $requireVerification ? 'pending' : 'active';

        // 8. Create merchant (project)
        $merchantId = generate_uuid();
        $merchant = [
            'id' => $merchantId,
            'business_name' => $name,
            'slug' => $slug,
            'owner_id' => $userId,
            'owner_name' => $owner['name'] ?? $name,
            'email' => $owner['email'] ?? '',
            'phone' => trim($data['phone'] ?? ''),
            'status' => $status,
            'mode' => 'sandbox',
            'api_key' => generate_api_key(),
            'webhook_url' => $webhookUrl,
            'redirect_url' => '',
            'ip_whitelist' => $ipWhitelist,
            'fee_type' => config('app.default_fee_type', 'percentage'),
            'fee_value' => config('app.default_fee_value', 0.7),
            'fee_flat' => config('app.default_fee_flat', 0),
            'payment_expiry_minutes' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $this->merchantRepo->create($merchant);

        // 9. Create wallet for this project
        $this->walletRepo->create([
            'id' => generate_uuid(),
            'merchant_id' => $merchantId,
            'pending_balance' => 0,
            'available_balance' => 0,
            'hold_balance' => 0,
            'withdrawn_balance' => 0,
            'total_received' => 0,
            'total_fee' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 10. Link user to project. First project becomes default.
        $isFirst = $this->countByUser($userId) === 0;
        $this->userMerchantRepo->link($userId, $merchantId, 'owner', $isFirst);

        // 11. If first project, activate it in session immediately
        if ($isFirst) {
            $_SESSION['active_merchant_id'] = $merchantId;
            $_SESSION['merchant_id'] = $merchantId;
        }

        // 12. Audit log
        $this->auditService->log(
            $userId, Auth::role() ?? 'merchant', $merchantId,
            'create_project',
            "Proyek '{$name}' (slug: {$slug}) dibuat, status: {$status}",
            ['slug' => $slug, 'status' => $status, 'webhook_url' => $webhookUrl]
        );

        $message = $requireVerification
            ? 'Proyek berhasil dibuat. Menunggu verifikasi admin sebelum bisa menerima pembayaran.'
            : 'Proyek berhasil dibuat dan langsung aktif.';

        return ['success' => true, 'message' => $message, 'project' => $merchant];
    }

    /**
     * Switch the active project in session.
     */
    public function switchActive(string $userId, string $merchantId): array
    {
        if (!$this->isMigrated()) {
            return $this->notMigratedError();
        }
        if (!$this->userMerchantRepo->userHasAccess($userId, $merchantId)) {
            return ['success' => false, 'message' => 'Anda tidak memiliki akses ke proyek ini.'];
        }

        $_SESSION['active_merchant_id'] = $merchantId;
        $_SESSION['merchant_id'] = $merchantId; // keep legacy pointer in sync

        // Remember as default for next login
        $this->userMerchantRepo->setDefault($userId, $merchantId);

        return ['success' => true, 'message' => 'Proyek aktif berhasil diganti.'];
    }

    /**
     * Get the currently active project for a user.
     * Falls back to default project if session not set.
     */
    public function getActive(string $userId): ?array
    {
        // Legacy mode: use users.merchant_id directly
        if (!$this->isMigrated()) {
            $legacy = $this->legacyProjects($userId);
            return $legacy[0] ?? null;
        }

        $activeId = $_SESSION['active_merchant_id'] ?? $_SESSION['merchant_id'] ?? null;

        // Verify the active project still belongs to the user
        if ($activeId && $this->userMerchantRepo->userHasAccess($userId, $activeId)) {
            return $this->merchantRepo->find($activeId);
        }

        // Fallback: default project
        $defaultId = $this->userMerchantRepo->getDefaultMerchantId($userId);
        if ($defaultId) {
            $_SESSION['active_merchant_id'] = $defaultId;
            $_SESSION['merchant_id'] = $defaultId;
            return $this->merchantRepo->find($defaultId);
        }

        return null; // user has no projects yet
    }

    /**
     * Set the active project on login (default or first).
     */
    public function initActiveForUser(string $userId): ?string
    {
        $defaultId = $this->userMerchantRepo->getDefaultMerchantId($userId);
        if ($defaultId) {
            $_SESSION['active_merchant_id'] = $defaultId;
            $_SESSION['merchant_id'] = $defaultId;
        }
        return $defaultId;
    }

    /**
     * Security guard: does the user own/access this project?
     */
    public function userOwns(string $userId, string $merchantId): bool
    {
        if (!$this->isMigrated()) {
            // Legacy: user owns their single merchant_id
            $user = (new UserRepository())->find($userId);
            return ($user['merchant_id'] ?? null) === $merchantId;
        }
        return $this->userMerchantRepo->userHasAccess($userId, $merchantId);
    }

    /**
     * Update project basic settings (name, webhook, ip whitelist).
     * Only the owner can update. Does not change verification status.
     */
    public function update(string $userId, string $merchantId, array $data): array
    {
        if (!$this->userOwns($userId, $merchantId)) {
            return ['success' => false, 'message' => 'Akses ditolak.'];
        }

        $updates = ['updated_at' => now()];

        if (isset($data['name']) && trim($data['name']) !== '') {
            $updates['business_name'] = trim($data['name']);
        }
        if (isset($data['webhook_url'])) {
            $webhookUrl = trim($data['webhook_url']);
            if ($webhookUrl !== '' && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
                return ['success' => false, 'message' => 'Format Webhook URL tidak valid.'];
            }
            $updates['webhook_url'] = $webhookUrl;
        }
        if (isset($data['ip_whitelist'])) {
            $ipWhitelist = $this->normalizeIpWhitelist($data['ip_whitelist']);
            if ($ipWhitelist === false) {
                return ['success' => false, 'message' => 'Terdapat IP address yang tidak valid pada whitelist.'];
            }
            $updates['ip_whitelist'] = $ipWhitelist;
        }

        $this->merchantRepo->update($merchantId, $updates);

        $this->auditService->log(
            $userId, Auth::role() ?? 'merchant', $merchantId,
            'update_project', 'Pengaturan proyek diperbarui', $updates
        );

        return ['success' => true, 'message' => 'Pengaturan proyek berhasil disimpan.'];
    }

    /**
     * Find a project by id.
     */
    public function find(string $merchantId): ?array
    {
        return $this->merchantRepo->find($merchantId);
    }

    /**
     * Regenerate API key for a project (owner only).
     */
    public function regenerateApiKey(string $userId, string $merchantId): array
    {
        if (!$this->userOwns($userId, $merchantId)) {
            return ['success' => false, 'message' => 'Akses ditolak.'];
        }
        $newKey = $this->merchantRepo->regenerateApiKey($merchantId);

        $this->auditService->log(
            $userId, Auth::role() ?? 'merchant', $merchantId,
            'api_key_regenerated', 'API key proyek dibuat ulang', []
        );

        return ['success' => true, 'message' => 'API key berhasil dibuat ulang.', 'api_key' => $newKey];
    }

    // ==============================
    // HELPERS
    // ==============================

    /**
     * Generate a unique slug from a project name.
     * "Kancil Bot" -> "kancilbot", collisions get numeric suffix.
     */
    private function generateUniqueSlug(string $name): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        if ($base === '') {
            $base = 'proyek';
        }
        $base = substr($base, 0, 90);

        $slug = $base;
        $i = 1;
        while ($this->slugExists($slug)) {
            $slug = $base . $i;
            $i++;
        }
        return $slug;
    }

    /**
     * Check whether a slug already exists.
     */
    private function slugExists(string $slug): bool
    {
        $existing = $this->merchantRepo->findBySlug($slug);
        return $existing !== null;
    }

    /**
     * Normalize and validate IP whitelist.
     * Accepts newline or comma separated IPs. Returns normalized newline-joined
     * string, empty string if blank, or false if any entry is invalid.
     */
    private function normalizeIpWhitelist(string $raw): string|false
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Split by newline or comma
        $parts = preg_split('/[\r\n,]+/', $raw);
        $valid = [];
        foreach ($parts as $ip) {
            $ip = trim($ip);
            if ($ip === '') continue;
            // Allow plain IP (v4/v6) or CIDR notation
            $ipToCheck = $ip;
            if (str_contains($ip, '/')) {
                [$ipToCheck, $mask] = explode('/', $ip, 2);
                if (!is_numeric($mask)) return false;
            }
            if (filter_var($ipToCheck, FILTER_VALIDATE_IP) === false) {
                return false;
            }
            $valid[] = $ip;
        }
        return implode("\n", array_unique($valid));
    }
}
