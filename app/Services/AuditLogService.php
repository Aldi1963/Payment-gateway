<?php
/**
 * Audit Log Service
 * Records all significant system activities
 */

require_once base_path('app/Repositories/AuditLogRepository.php');

class AuditLogService
{
    private AuditLogRepository $auditRepo;

    public function __construct()
    {
        $this->auditRepo = new AuditLogRepository();
    }

    /**
     * Log an activity
     */
    public function log(
        ?string $actorId,
        ?string $actorRole,
        ?string $merchantId,
        string $action,
        string $description,
        array $metadata = []
    ): void {
        $entry = [
            'id' => generate_uuid(),
            'actor_id' => $actorId ?? 'system',
            'actor_role' => $actorRole ?? 'system',
            'merchant_id' => $merchantId,
            'action' => $action,
            'description' => $description,
            'ip' => get_client_ip(),
            'user_agent' => get_user_agent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ];

        $this->auditRepo->create($entry);
    }

    /**
     * Get all logs (admin)
     */
    public function getAll(array $filters = []): array
    {
        return $this->auditRepo->findAll($filters);
    }

    /**
     * Get logs by merchant
     */
    public function getByMerchant(string $merchantId): array
    {
        return $this->auditRepo->findByMerchant($merchantId);
    }

    /**
     * Get logs by actor
     */
    public function getByActor(string $actorId): array
    {
        return $this->auditRepo->findByActor($actorId);
    }

    /**
     * Get logs by action type
     */
    public function getByAction(string $action): array
    {
        return $this->auditRepo->findByAction($action);
    }
}
