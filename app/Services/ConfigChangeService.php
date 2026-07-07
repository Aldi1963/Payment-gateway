<?php
/**
 * Config Change Service
 * Handles security verification for merchant configuration changes.
 * 
 * Change types requiring verification:
 * - webhook_url
 * - redirect_url
 * - ip_whitelist
 * - domain_website
 * - api_key_regenerate
 * 
 * Flow: Merchant requests -> Pending -> Admin approves/rejects -> Applied/Rejected
 */

require_once base_path('app/Repositories/ConfigChangeRepository.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Repositories/UserRepository.php');
require_once base_path('app/Services/AuditLogService.php');
require_once base_path('app/Services/NotificationService.php');

class ConfigChangeService
{
    private ConfigChangeRepository $changeRepo;
    private MerchantRepository $merchantRepo;
    private AuditLogService $auditService;
    private NotificationService $notifService;

    // Change types that require admin verification
    public const VERIFIED_TYPES = [
        'webhook_url' => 'Webhook URL',
        'redirect_url' => 'Redirect URL',
        'ip_whitelist_add' => 'Tambah IP Whitelist',
        'ip_whitelist_remove' => 'Hapus IP Whitelist',
        'ip_whitelist_change' => 'Ubah IP Whitelist',
        'domain_website' => 'Domain / Website',
        'api_key_regenerate' => 'Regenerate API Key',
    ];

    public function __construct()
    {
        $this->changeRepo = new ConfigChangeRepository();
        $this->merchantRepo = new MerchantRepository();
        $this->auditService = new AuditLogService();
        $this->notifService = new NotificationService();
    }

    /**
     * Request a configuration change (merchant submits)
     */
    public function requestChange(array $data): array
    {
        $merchantId = $data['merchant_id'] ?? '';
        $changeType = $data['change_type'] ?? '';
        $oldValue = $data['old_value'] ?? '';
        $newValue = $data['new_value'] ?? '';
        $reason = $data['reason'] ?? '';
        $requestedBy = $data['requested_by'] ?? '';
        $requestedByRole = $data['requested_by_role'] ?? '';

        // Validate change type
        if (!isset(self::VERIFIED_TYPES[$changeType])) {
            return ['success' => false, 'message' => 'Tipe perubahan tidak valid.'];
        }

        // Check if merchant exists
        $merchant = $this->merchantRepo->find($merchantId);
        if (!$merchant) {
            return ['success' => false, 'message' => 'Merchant tidak ditemukan.'];
        }

        // Check if there's already a pending change for this field
        if ($this->changeRepo->hasPendingForField($merchantId, $changeType)) {
            return ['success' => false, 'message' => 'Sudah ada perubahan yang menunggu verifikasi untuk konfigurasi ini. Tunggu proses selesai atau batalkan terlebih dahulu.'];
        }

        // Create change request
        $changeId = generate_uuid();
        $change = [
            'id' => $changeId,
            'merchant_id' => $merchantId,
            'merchant_name' => $merchant['business_name'] ?? '',
            'change_type' => $changeType,
            'change_label' => self::VERIFIED_TYPES[$changeType],
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $requestedBy,
            'requested_by_role' => $requestedByRole,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
            'applied_at' => null,
            'rolled_back_at' => null,
            'rolled_back_by' => null,
            'version' => $this->getNextVersion($merchantId, $changeType),
            'ip' => get_client_ip(),
            'user_agent' => get_user_agent(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->changeRepo->create($change);

        // Audit log
        $this->auditService->log(
            $requestedBy, $requestedByRole, $merchantId,
            'config_change_requested',
            "Config change requested: " . self::VERIFIED_TYPES[$changeType],
            [
                'change_id' => $changeId,
                'change_type' => $changeType,
                'old_value' => $this->maskSensitive($oldValue),
                'new_value' => $this->maskSensitive($newValue),
            ]
        );

        // Notify admin
        $this->notifService->notifyAdmin(
            'config_change_pending',
            "Merchant {$merchant['business_name']} mengajukan perubahan: " . self::VERIFIED_TYPES[$changeType],
            ['change_id' => $changeId, 'merchant_id' => $merchantId]
        );

        // Notify merchant
        $this->notifService->notifyMerchant(
            $merchantId,
            'config_change_submitted',
            "Perubahan {$change['change_label']} telah diajukan dan menunggu verifikasi admin.",
            ['change_id' => $changeId]
        );

        return [
            'success' => true,
            'message' => 'Perubahan berhasil diajukan. Menunggu verifikasi admin.',
            'change' => $change,
        ];
    }

    /**
     * Admin approves a change request
     */
    public function approve(string $changeId, string $adminId, string $note = ''): array
    {
        $change = $this->changeRepo->find($changeId);
        if (!$change) {
            return ['success' => false, 'message' => 'Perubahan tidak ditemukan.'];
        }
        if ($change['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Perubahan tidak dalam status pending.'];
        }

        // Apply the change to merchant config
        $applied = $this->applyChange($change);
        if (!$applied) {
            return ['success' => false, 'message' => 'Gagal menerapkan perubahan.'];
        }

        // Update change record
        $this->changeRepo->update($changeId, [
            'status' => 'approved',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'review_note' => $note,
            'applied_at' => now(),
            'updated_at' => now(),
        ]);

        // Audit log
        $this->auditService->log(
            $adminId, 'admin', $change['merchant_id'],
            'config_change_approved',
            "Config change approved: {$change['change_label']}",
            [
                'change_id' => $changeId,
                'change_type' => $change['change_type'],
                'new_value' => $this->maskSensitive($change['new_value']),
                'note' => $note,
            ]
        );

        // Notify merchant
        $this->notifService->notifyMerchant(
            $change['merchant_id'],
            'config_change_approved',
            "Perubahan {$change['change_label']} telah disetujui dan diterapkan.",
            ['change_id' => $changeId, 'note' => $note]
        );

        return ['success' => true, 'message' => 'Perubahan disetujui dan diterapkan.'];
    }

    /**
     * Admin rejects a change request
     */
    public function reject(string $changeId, string $adminId, string $reason = ''): array
    {
        $change = $this->changeRepo->find($changeId);
        if (!$change) {
            return ['success' => false, 'message' => 'Perubahan tidak ditemukan.'];
        }
        if ($change['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Perubahan tidak dalam status pending.'];
        }

        $this->changeRepo->update($changeId, [
            'status' => 'rejected',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'review_note' => $reason,
            'updated_at' => now(),
        ]);

        // Audit log
        $this->auditService->log(
            $adminId, 'admin', $change['merchant_id'],
            'config_change_rejected',
            "Config change rejected: {$change['change_label']} - Reason: {$reason}",
            ['change_id' => $changeId, 'reason' => $reason]
        );

        // Notify merchant with rejection reason
        $this->notifService->notifyMerchant(
            $change['merchant_id'],
            'config_change_rejected',
            "Perubahan {$change['change_label']} ditolak. Alasan: {$reason}",
            ['change_id' => $changeId, 'reason' => $reason]
        );

        return ['success' => true, 'message' => 'Perubahan ditolak.'];
    }

    /**
     * Merchant cancels their own pending request
     */
    public function cancel(string $changeId, string $merchantId): array
    {
        $change = $this->changeRepo->find($changeId);
        if (!$change || $change['merchant_id'] !== $merchantId) {
            return ['success' => false, 'message' => 'Perubahan tidak ditemukan.'];
        }
        if ($change['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Hanya perubahan pending yang bisa dibatalkan.'];
        }

        $this->changeRepo->update($changeId, [
            'status' => 'canceled',
            'updated_at' => now(),
        ]);

        $this->auditService->log(
            Auth::id(), Auth::role(), $merchantId,
            'config_change_canceled',
            "Config change canceled: {$change['change_label']}",
            ['change_id' => $changeId]
        );

        return ['success' => true, 'message' => 'Perubahan dibatalkan.'];
    }

    /**
     * Admin rolls back a previously approved change
     */
    public function rollback(string $changeId, string $adminId, string $reason = ''): array
    {
        $change = $this->changeRepo->find($changeId);
        if (!$change) {
            return ['success' => false, 'message' => 'Perubahan tidak ditemukan.'];
        }
        if ($change['status'] !== 'approved') {
            return ['success' => false, 'message' => 'Hanya perubahan yang sudah approved bisa di-rollback.'];
        }

        // Revert to old value
        $rollbackData = $change;
        $rollbackData['new_value'] = $change['old_value']; // swap
        $reverted = $this->applyChange($rollbackData);

        if (!$reverted) {
            return ['success' => false, 'message' => 'Gagal melakukan rollback.'];
        }

        $this->changeRepo->update($changeId, [
            'status' => 'rolled_back',
            'rolled_back_at' => now(),
            'rolled_back_by' => $adminId,
            'review_note' => ($change['review_note'] ?? '') . " | Rolled back: {$reason}",
            'updated_at' => now(),
        ]);

        $this->auditService->log(
            $adminId, 'admin', $change['merchant_id'],
            'config_change_rolled_back',
            "Config change rolled back: {$change['change_label']} - reverted to previous value",
            ['change_id' => $changeId, 'reason' => $reason, 'reverted_to' => $this->maskSensitive($change['old_value'])]
        );

        $this->notifService->notifyMerchant(
            $change['merchant_id'],
            'config_change_rolled_back',
            "Perubahan {$change['change_label']} telah di-rollback oleh admin. Alasan: {$reason}",
            ['change_id' => $changeId]
        );

        return ['success' => true, 'message' => 'Perubahan berhasil di-rollback.'];
    }

    /**
     * Apply the config change to merchant record
     */
    private function applyChange(array $change): bool
    {
        $merchantId = $change['merchant_id'];
        $type = $change['change_type'];
        $newValue = $change['new_value'];

        $fieldMap = [
            'webhook_url' => 'webhook_url',
            'redirect_url' => 'redirect_url',
            'domain_website' => 'website',
            'ip_whitelist_add' => 'ip_whitelist',
            'ip_whitelist_remove' => 'ip_whitelist',
            'ip_whitelist_change' => 'ip_whitelist',
        ];

        if ($type === 'api_key_regenerate') {
            // Generate new API key
            $this->merchantRepo->regenerateApiKey($merchantId);
            return true;
        }

        if (isset($fieldMap[$type])) {
            $field = $fieldMap[$type];
            $this->merchantRepo->update($merchantId, [
                $field => $newValue,
                'updated_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get next version number for a merchant's change type
     */
    private function getNextVersion(string $merchantId, string $changeType): int
    {
        $history = $this->changeRepo->getVersionHistory($merchantId, $changeType);
        if (empty($history)) return 1;
        $maxVersion = max(array_map(fn($r) => $r['version'] ?? 0, $history));
        return $maxVersion + 1;
    }

    /**
     * Mask sensitive values for display
     */
    private function maskSensitive(mixed $value): string
    {
        if (!is_string($value)) return json_encode($value);
        if (strlen($value) > 20 && str_starts_with($value, 'pk_')) {
            return mask_api_key($value);
        }
        return $value;
    }

    /**
     * Get pending changes for merchant
     */
    public function getPendingByMerchant(string $merchantId): array
    {
        return $this->changeRepo->findPendingByMerchant($merchantId);
    }

    /**
     * Get all changes for merchant (history)
     */
    public function getHistoryByMerchant(string $merchantId): array
    {
        return $this->changeRepo->findByMerchant($merchantId);
    }

    /**
     * Get all pending changes (admin)
     */
    public function getAllPending(): array
    {
        return $this->changeRepo->findAllPending();
    }

    /**
     * Get all changes (admin)
     */
    public function getAll(array $filters = []): array
    {
        return $this->changeRepo->findAll($filters);
    }

    /**
     * Find single change
     */
    public function find(string $id): ?array
    {
        return $this->changeRepo->find($id);
    }

    /**
     * Get version history for a field
     */
    public function getVersionHistory(string $merchantId, string $changeType): array
    {
        return $this->changeRepo->getVersionHistory($merchantId, $changeType);
    }

    /**
     * Count pending changes
     */
    public function countPending(): int
    {
        return $this->changeRepo->countPending();
    }

    /**
     * Verify password for sensitive changes
     */
    public function verifyPassword(string $userId, string $password): bool
    {
        require_once base_path('app/Repositories/UserRepository.php');
        $userRepo = new UserRepository();
        $user = $userRepo->find($userId);
        if (!$user) return false;
        return password_verify($password, $user['password_hash']);
    }
}
