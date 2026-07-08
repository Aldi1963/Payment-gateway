<?php
/**
 * Security Service
 * Centralized security management for the application
 * 
 * Features:
 * - Login history tracking
 * - Session management (multi-device, force logout)
 * - CORS configuration
 * - CSP (Content Security Policy) headers
 * - API key rotation with grace period
 * - Suspicious login detection
 */

require_once base_path('app/Database.php');

class SecurityService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ==============================
    // LOGIN HISTORY
    // ==============================

    /**
     * Record a login attempt (success or failure)
     */
    public function recordLogin(string $userId, string $ip, string $userAgent, string $status = 'success', ?string $failureReason = null): string
    {
        $id = generate_uuid();
        $deviceFingerprint = $this->generateDeviceFingerprint($ip, $userAgent);

        $stmt = $this->db->prepare(
            "INSERT INTO `login_history` (`id`, `user_id`, `ip`, `user_agent`, `status`, `failure_reason`, `device_fingerprint`, `created_at`)
             VALUES (:id, :user_id, :ip, :ua, :status, :reason, :fingerprint, NOW())"
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'ip' => $ip,
            'ua' => $userAgent,
            'status' => $status,
            'reason' => $failureReason,
            'fingerprint' => $deviceFingerprint,
        ]);

        // Check for suspicious login patterns
        if ($status === 'success') {
            $this->checkSuspiciousLogin($userId, $ip, $deviceFingerprint);
        }

        return $id;
    }

    /**
     * Get login history for a user
     */
    public function getLoginHistory(string $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `login_history` WHERE `user_id` = :uid ORDER BY `created_at` DESC LIMIT :lim"
        );
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get recent failed attempts for a user
     */
    public function getRecentFailedAttempts(string $userId, int $minutes = 30): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM `login_history` 
             WHERE `user_id` = :uid AND `status` = 'failed' 
             AND `created_at` >= DATE_SUB(NOW(), INTERVAL :mins MINUTE)"
        );
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':mins', $minutes, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Detect suspicious login (new IP or device)
     */
    private function checkSuspiciousLogin(string $userId, string $ip, string $fingerprint): void
    {
        // Check if this IP/device was ever used by this user
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM `login_history` 
             WHERE `user_id` = :uid AND `device_fingerprint` = :fp AND `status` = 'success'
             AND `id` != (SELECT id FROM login_history WHERE user_id = :uid2 ORDER BY created_at DESC LIMIT 1)"
        );
        $stmt->execute(['uid' => $userId, 'fp' => $fingerprint, 'uid2' => $userId]);
        $previousLogins = (int)$stmt->fetchColumn();

        if ($previousLogins === 0) {
            // First time from this device - flag as suspicious
            $this->createSecurityAlert($userId, 'new_device', "Login from new device/location: IP {$ip}");
        }
    }

    /**
     * Create a security alert notification
     */
    private function createSecurityAlert(string $userId, string $type, string $message): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO `notifications` (`id`, `recipient_id`, `type`, `message`, `data`, `created_at`)
                 VALUES (:id, :uid, :type, :msg, :data, NOW())"
            );
            $stmt->execute([
                'id' => generate_uuid(),
                'uid' => $userId,
                'type' => 'security_alert',
                'msg' => $message,
                'data' => json_encode(['alert_type' => $type, 'timestamp' => now()]),
            ]);
        } catch (\Throwable $e) {
            app_log("Failed to create security alert: " . $e->getMessage(), 'ERROR');
        }
    }

    // ==============================
    // SESSION MANAGEMENT
    // ==============================

    /**
     * Register a new session
     */
    public function registerSession(string $userId, string $sessionId, string $ip, string $userAgent): string
    {
        $id = generate_uuid();
        $deviceName = $this->parseDeviceName($userAgent);
        $expiresAt = date('Y-m-d H:i:s', time() + (int)setting('session_lifetime', 7200));

        $stmt = $this->db->prepare(
            "INSERT INTO `user_sessions` (`id`, `user_id`, `session_id`, `ip`, `user_agent`, `device_name`, `is_active`, `last_activity_at`, `expires_at`, `created_at`)
             VALUES (:id, :uid, :sid, :ip, :ua, :device, 1, NOW(), :expires, NOW())"
        );
        $stmt->execute([
            'id' => $id,
            'uid' => $userId,
            'sid' => $sessionId,
            'ip' => $ip,
            'ua' => $userAgent,
            'device' => $deviceName,
            'expires' => $expiresAt,
        ]);

        return $id;
    }

    /**
     * Update session activity
     */
    public function touchSession(string $sessionId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE `user_sessions` SET `last_activity_at` = NOW() WHERE `session_id` = :sid AND `is_active` = 1"
        );
        $stmt->execute(['sid' => $sessionId]);
    }

    /**
     * Get active sessions for a user
     */
    public function getActiveSessions(string $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM `user_sessions` 
             WHERE `user_id` = :uid AND `is_active` = 1 AND `expires_at` > NOW()
             ORDER BY `last_activity_at` DESC"
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Terminate a specific session
     */
    public function terminateSession(string $sessionId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE `user_sessions` SET `is_active` = 0 WHERE `session_id` = :sid"
        );
        $stmt->execute(['sid' => $sessionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Force logout all sessions for a user (except current)
     */
    public function forceLogoutAll(string $userId, ?string $exceptSessionId = null): int
    {
        if ($exceptSessionId) {
            $stmt = $this->db->prepare(
                "UPDATE `user_sessions` SET `is_active` = 0 WHERE `user_id` = :uid AND `session_id` != :sid"
            );
            $stmt->execute(['uid' => $userId, 'sid' => $exceptSessionId]);
        } else {
            $stmt = $this->db->prepare(
                "UPDATE `user_sessions` SET `is_active` = 0 WHERE `user_id` = :uid"
            );
            $stmt->execute(['uid' => $userId]);
        }
        return $stmt->rowCount();
    }

    /**
     * Check if a session is still valid
     */
    public function isSessionValid(string $sessionId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM `user_sessions` WHERE `session_id` = :sid AND `is_active` = 1 AND `expires_at` > NOW()"
        );
        $stmt->execute(['sid' => $sessionId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Cleanup expired sessions
     */
    public function cleanupExpiredSessions(): int
    {
        $stmt = $this->db->prepare(
            "UPDATE `user_sessions` SET `is_active` = 0 WHERE `expires_at` < NOW() AND `is_active` = 1"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    // ==============================
    // SECURITY HEADERS
    // ==============================

    /**
     * Set all security headers for HTML pages
     */
    public static function setSecurityHeaders(): void
    {
        // Content Security Policy
        $csp = self::buildCSP();
        header("Content-Security-Policy: {$csp}");

        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // XSS Protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy (restrict browser features)
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS (only in production with HTTPS)
        if (getenv('APP_ENV') === 'production' && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Build Content Security Policy header value
     */
    private static function buildCSP(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://app.sandbox.midtrans.com https://app.midtrans.com",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' https://api.sandbox.midtrans.com https://api.midtrans.com",
            "frame-src 'self' https://app.sandbox.midtrans.com https://app.midtrans.com",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directives);
    }

    /**
     * Set CORS headers for API responses
     */
    public static function setCorsHeaders(?string $allowedOrigins = null): void
    {
        $origins = $allowedOrigins ?? setting('cors_allowed_origins', '*');
        
        // If specific origins configured, validate against request origin
        if ($origins !== '*') {
            $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowedList = array_map('trim', explode(',', $origins));
            
            if (in_array($requestOrigin, $allowedList)) {
                header("Access-Control-Allow-Origin: {$requestOrigin}");
                header('Vary: Origin');
            }
        } else {
            header('Access-Control-Allow-Origin: *');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key, X-Idempotency-Key, X-CSRF-Token, X-Requested-With');
        header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After, X-Idempotency-Replayed, X-API-Version');
        header('Access-Control-Max-Age: 86400');
    }

    // ==============================
    // API KEY ROTATION
    // ==============================

    /**
     * Rotate API key with grace period
     * Old key remains valid for grace period (default 24 hours)
     */
    public function rotateApiKey(string $merchantId, int $gracePeriodHours = 24): array
    {
        $newApiKey = generate_api_key();
        
        // Store old key temporarily for grace period
        $merchantRepo = new MerchantRepository();
        $merchant = $merchantRepo->find($merchantId);
        
        if (!$merchant) {
            return ['success' => false, 'message' => 'Merchant not found'];
        }

        $oldKey = $merchant['api_key'];
        $gracePeriodEnd = date('Y-m-d H:i:s', time() + ($gracePeriodHours * 3600));

        // Store in settings for grace period lookup
        $graceKey = "api_key_grace_{$merchantId}";
        $graceData = json_encode([
            'old_key' => $oldKey,
            'expires_at' => $gracePeriodEnd,
        ]);

        // Update merchant with new key
        $stmt = $this->db->prepare("UPDATE `merchants` SET `api_key` = :key, `updated_at` = NOW() WHERE `id` = :id");
        $stmt->execute(['key' => $newApiKey, 'id' => $merchantId]);

        // Store grace period data
        $stmt = $this->db->prepare(
            "INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) 
             VALUES (:id, :key, :val, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()"
        );
        $stmt->execute(['id' => generate_uuid(), 'key' => $graceKey, 'val' => $graceData]);

        return [
            'success' => true,
            'new_api_key' => $newApiKey,
            'old_key_valid_until' => $gracePeriodEnd,
            'message' => "API key rotated. Old key valid until {$gracePeriodEnd}",
        ];
    }

    /**
     * Check if an API key is valid during grace period
     */
    public function checkGracePeriodKey(string $apiKey, string $merchantId): bool
    {
        $graceKey = "api_key_grace_{$merchantId}";
        $stmt = $this->db->prepare("SELECT `value` FROM `settings` WHERE `key` = :key");
        $stmt->execute(['key' => $graceKey]);
        $row = $stmt->fetch();

        if (!$row) return false;

        $data = json_decode($row['value'], true);
        if (!$data) return false;

        // Check if grace period is still active
        if (strtotime($data['expires_at']) < time()) {
            // Grace period expired, clean up
            $this->db->prepare("DELETE FROM `settings` WHERE `key` = :key")->execute(['key' => $graceKey]);
            return false;
        }

        return hash_equals($data['old_key'], $apiKey);
    }

    // ==============================
    // HELPERS
    // ==============================

    /**
     * Generate device fingerprint from IP and user agent
     */
    private function generateDeviceFingerprint(string $ip, string $userAgent): string
    {
        return hash('sha256', $ip . '|' . $userAgent);
    }

    /**
     * Parse device name from user agent string
     */
    private function parseDeviceName(string $userAgent): string
    {
        if (empty($userAgent)) return 'Unknown Device';

        // Detect OS
        $os = 'Unknown OS';
        if (str_contains($userAgent, 'Windows')) $os = 'Windows';
        elseif (str_contains($userAgent, 'Mac OS')) $os = 'macOS';
        elseif (str_contains($userAgent, 'Linux')) $os = 'Linux';
        elseif (str_contains($userAgent, 'Android')) $os = 'Android';
        elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) $os = 'iOS';

        // Detect Browser
        $browser = 'Unknown Browser';
        if (str_contains($userAgent, 'Firefox')) $browser = 'Firefox';
        elseif (str_contains($userAgent, 'Edg/')) $browser = 'Edge';
        elseif (str_contains($userAgent, 'Chrome')) $browser = 'Chrome';
        elseif (str_contains($userAgent, 'Safari')) $browser = 'Safari';
        elseif (str_contains($userAgent, 'Opera') || str_contains($userAgent, 'OPR')) $browser = 'Opera';

        return "{$browser} on {$os}";
    }
}
