<?php
/**
 * Idempotency Service
 * Prevents duplicate API requests using idempotency keys
 * 
 * Usage:
 * - Client sends header: Idempotency-Key: <unique-key>
 * - If key already exists with same request, return cached response
 * - If key exists with different request body, return 422 error
 * - Keys expire after configurable TTL (default 24 hours)
 */

require_once base_path('app/Database.php');

class IdempotencyService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Check if an idempotency key exists and return cached response if so
     * 
     * @param string $merchantId
     * @param string $idempotencyKey
     * @param string $requestPath Current request path/action
     * @param string $requestBody Current request body
     * @return array|null Cached response or null if key doesn't exist
     */
    public function check(string $merchantId, string $idempotencyKey, string $requestPath, string $requestBody): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `idempotency_keys` WHERE `merchant_id` = :mid AND `idempotency_key` = :key AND `expires_at` > NOW()"
        );
        $stmt->execute(['mid' => $merchantId, 'key' => $idempotencyKey]);
        $existing = $stmt->fetch();

        if (!$existing) {
            return null; // Key doesn't exist, proceed normally
        }

        // Key exists - verify request is the same
        $currentHash = hash('sha256', $requestPath . ':' . $requestBody);
        if ($existing['request_hash'] !== $currentHash) {
            // Same idempotency key but different request - conflict
            return [
                'conflict' => true,
                'message' => 'Idempotency key already used for a different request',
                'code' => 422,
            ];
        }

        // Same request - return cached response
        return [
            'conflict' => false,
            'cached' => true,
            'response_code' => (int)$existing['response_code'],
            'response_body' => $existing['response_body'],
        ];
    }

    /**
     * Store the response for an idempotency key
     * 
     * @param string $merchantId
     * @param string $idempotencyKey
     * @param string $requestPath
     * @param string $requestBody
     * @param int $responseCode HTTP response code
     * @param string $responseBody JSON response body
     */
    public function store(string $merchantId, string $idempotencyKey, string $requestPath, string $requestBody, int $responseCode, string $responseBody): void
    {
        $ttlHours = (int)setting('idempotency_key_ttl_hours', 24);
        $requestHash = hash('sha256', $requestPath . ':' . $requestBody);

        $stmt = $this->db->prepare(
            "INSERT INTO `idempotency_keys` (`id`, `merchant_id`, `idempotency_key`, `request_path`, `request_hash`, `response_code`, `response_body`, `expires_at`, `created_at`)
             VALUES (:id, :mid, :key, :path, :hash, :code, :body, DATE_ADD(NOW(), INTERVAL :ttl HOUR), NOW())
             ON DUPLICATE KEY UPDATE `response_code` = VALUES(`response_code`), `response_body` = VALUES(`response_body`)"
        );
        $stmt->execute([
            'id' => generate_uuid(),
            'mid' => $merchantId,
            'key' => $idempotencyKey,
            'path' => $requestPath,
            'hash' => $requestHash,
            'code' => $responseCode,
            'body' => $responseBody,
            'ttl' => $ttlHours,
        ]);
    }

    /**
     * Clean up expired idempotency keys
     * Called from cron
     */
    public function cleanup(): int
    {
        $stmt = $this->db->prepare("DELETE FROM `idempotency_keys` WHERE `expires_at` < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Extract idempotency key from request headers
     */
    public static function extractFromHeaders(): ?string
    {
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? null;
        if ($key !== null) {
            $key = trim($key);
            // Validate key length (must be between 1-255 characters)
            if (strlen($key) < 1 || strlen($key) > 255) {
                return null;
            }
        }
        return $key;
    }
}
