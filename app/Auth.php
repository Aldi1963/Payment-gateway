<?php
/**
 * Authentication & Authorization System
 * Payment Gateway SaaS Multi Merchant
 */

class Auth
{
    private static ?array $currentUser = null;

    /**
     * Initialize session
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionName = config('app.session_name', 'paygate_session');
            $lifetime = config('app.session_lifetime', 7200);
            
            session_name($sessionName);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['_last_regenerate'])) {
                $_SESSION['_last_regenerate'] = time();
            } elseif (time() - $_SESSION['_last_regenerate'] > 300) {
                session_regenerate_id(true);
                $_SESSION['_last_regenerate'] = time();
            }
        }
    }

    /**
     * Attempt login
     */
    public static function attempt(string $email, string $password): array
    {
        require_once base_path('app/Repositories/UserRepository.php');
        require_once base_path('app/RateLimiter.php');
        $userRepo = new UserRepository();
        $rateLimiter = new RateLimiter();
        
        // File-based rate limiting (cannot be bypassed by clearing cookies)
        $ip = self::getTrustedClientIp();
        $rateLimitKey = RateLimiter::loginKey($email, $ip);
        $maxAttempts = (int)setting('login_max_attempts', config('app.login_max_attempts', 5));
        $lockoutTime = (int)setting('login_lockout_time', config('app.login_lockout_time', 900));
        
        $rateCheck = $rateLimiter->check($rateLimitKey, $maxAttempts, $lockoutTime);
        if (!$rateCheck['allowed']) {
            $remaining = ceil($rateCheck['retry_after'] / 60);
            app_log("Rate limited login attempt for {$email} from IP {$ip}", 'WARNING');
            return [
                'success' => false,
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$remaining} menit."
            ];
        }
        
        $user = $userRepo->findByEmail($email);
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Record failed attempt
            $rateLimiter->hit($rateLimitKey, $lockoutTime);
            return ['success' => false, 'message' => 'Email atau password salah.'];
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Akun Anda tidak aktif. Hubungi administrator.'];
        }
        
        // Clear rate limit on success
        $rateLimiter->clear($rateLimitKey);
        
        // Regenerate session after successful login
        session_regenerate_id(true);
        
        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['merchant_id'] = $user['merchant_id'] ?? null;
        $_SESSION['permissions'] = $user['permissions'] ?? [];
        $_SESSION['logged_in_at'] = time();
        $_SESSION['_fingerprint'] = self::generateFingerprint();
        $_SESSION['_login_ip'] = self::getTrustedClientIp();
        
        // Update last login
        $userRepo->update($user['id'], ['last_login_at' => now()]);
        
        return ['success' => true, 'user' => $user];
    }

    /**
     * Register new merchant user
     */
    public static function register(array $data): array
    {
        require_once base_path('app/Repositories/UserRepository.php');
        require_once base_path('app/Repositories/MerchantRepository.php');
        require_once base_path('app/Repositories/WalletRepository.php');
        
        $userRepo = new UserRepository();
        $merchantRepo = new MerchantRepository();
        $walletRepo = new WalletRepository();
        
        // Check if email exists
        if ($userRepo->findByEmail($data['email'])) {
            return ['success' => false, 'message' => 'Email sudah terdaftar.'];
        }
        
        // Create merchant
        $merchantId = generate_uuid();
        $merchant = [
            'id' => $merchantId,
            'business_name' => $data['business_name'],
            'owner_name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'status' => 'pending',
            'api_key' => generate_api_key(),
            'webhook_url' => '',
            'redirect_url' => '',
            'fee_type' => config('app.default_fee_type', 'percentage'),
            'fee_value' => config('app.default_fee_value', 0.7),
            'fee_flat' => config('app.default_fee_flat', 0),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $merchantRepo->create($merchant);
        
        // Create user
        $userId = generate_uuid();
        $user = [
            'id' => $userId,
            'merchant_id' => $merchantId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => 'merchant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $userRepo->create($user);
        
        // Create wallet
        $wallet = [
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
        ];
        $walletRepo->create($wallet);
        
        return ['success' => true, 'user' => $user, 'merchant' => $merchant];
    }

    /**
     * Logout
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current user ID
     */
    public static function id(): ?string
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user role
     */
    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Get current merchant ID
     */
    public static function merchantId(): ?string
    {
        return $_SESSION['merchant_id'] ?? null;
    }

    /**
     * Get current user data
     */
    public static function user(): ?array
    {
        if (!self::check()) return null;
        
        if (self::$currentUser === null) {
            require_once base_path('app/Repositories/UserRepository.php');
            $userRepo = new UserRepository();
            self::$currentUser = $userRepo->find(self::id());
        }
        
        return self::$currentUser;
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole(string|array $roles): bool
    {
        $userRole = self::role();
        if (!$userRole) return false;
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        return in_array($userRole, $roles);
    }

    /**
     * Check if user is super admin
     */
    public static function isSuperAdmin(): bool
    {
        return self::hasRole('super_admin');
    }

    /**
     * Check if user is any admin role
     */
    public static function isAdmin(): bool
    {
        return self::hasRole(['super_admin', 'admin']);
    }

    /**
     * Check if user is finance role
     */
    public static function isFinance(): bool
    {
        return self::hasRole(['super_admin', 'admin', 'finance']);
    }

    /**
     * Check if user is merchant
     */
    public static function isMerchant(): bool
    {
        return self::hasRole(['merchant', 'staff_merchant']);
    }

    /**
     * Require authentication - redirect if not logged in
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (is_ajax()) {
                json_response(['error' => 'Unauthorized'], 401);
            }
            flash('error', 'Silakan login terlebih dahulu.');
            redirect('/login.php');
        }
    }

    /**
     * Require specific role(s)
     */
    public static function requireRole(string|array $roles): void
    {
        self::requireLogin();
        
        if (!self::hasRole($roles)) {
            if (is_ajax()) {
                json_response(['error' => 'Forbidden'], 403);
            }
            flash('error', 'Anda tidak memiliki akses ke halaman ini.');
            redirect('/login.php');
        }
    }

    /**
     * Require admin access
     */
    public static function requireAdmin(): void
    {
        self::requireRole(['super_admin', 'admin', 'finance', 'support']);
    }

    /**
     * Require merchant access
     */
    public static function requireMerchant(): void
    {
        self::requireRole(['merchant', 'staff_merchant']);
    }

    /**
     * Verify CSRF for form submissions
     */
    public static function verifyCsrf(): void
    {
        if (is_post() && !verify_csrf()) {
            if (is_ajax()) {
                json_response(['error' => 'Invalid CSRF token'], 403);
            }
            flash('error', 'Sesi telah kedaluwarsa. Silakan coba lagi.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/login.php');
        }
    }

    /**
     * Get trusted client IP (only REMOTE_ADDR is trusted unless behind known proxy)
     * SECURITY: Never trust X-Forwarded-For from public internet
     */
    public static function getTrustedClientIp(): string
    {
        // Only trust REMOTE_ADDR by default (safe from spoofing)
        // If behind a KNOWN reverse proxy (Cloudflare, nginx, etc),
        // the admin should configure trusted_proxy_ips in settings
        $trustedProxies = setting('trusted_proxy_ips', '');
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!empty($trustedProxies)) {
            $proxies = array_map('trim', explode(',', $trustedProxies));
            if (in_array($remoteAddr, $proxies)) {
                // Behind trusted proxy - use forwarded IP
                if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                    return trim($_SERVER['HTTP_X_REAL_IP']);
                }
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    return trim($ips[0]);
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * Validate session fingerprint (detect session hijacking)
     * Checks if the current request matches the session's original fingerprint.
     */
    public static function validateSession(): bool
    {
        if (!self::check()) return true; // no session to validate

        $fingerprint = self::generateFingerprint();
        if (isset($_SESSION['_fingerprint'])) {
            if (!hash_equals($_SESSION['_fingerprint'], $fingerprint)) {
                // Potential session hijacking - destroy session
                app_log("Session fingerprint mismatch for user " . ($_SESSION['user_id'] ?? 'unknown') . " from IP " . self::getTrustedClientIp(), 'SECURITY');
                self::logout();
                return false;
            }
        }
        return true;
    }

    /**
     * Generate session fingerprint
     */
    private static function generateFingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        return hash('sha256', $ua . '|' . $accept);
    }
}
