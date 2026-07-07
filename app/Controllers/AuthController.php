<?php
/**
 * Auth Controller
 * Handles login, register, logout
 * 
 * SECURITY:
 * - Registration IP-based throttle (prevents mass bot registration)
 * - Input length limits and character validation
 * - Password complexity checks
 * - Email normalization
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/Auth.php');
require_once base_path('app/RateLimiter.php');
require_once base_path('app/Services/AuditLogService.php');

class AuthController
{
    private AuditLogService $auditService;
    private RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->auditService = new AuditLogService();
        $this->rateLimiter = new RateLimiter();
    }

    /**
     * Handle login
     */
    public function login(): void
    {
        if (!is_post()) return;
        
        Auth::verifyCsrf();

        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            flash('error', 'Email dan password wajib diisi.');
            redirect('/login.php');
        }

        if (!is_valid_email($email)) {
            flash('error', 'Format email tidak valid.');
            redirect('/login.php');
        }

        // Normalize email
        $email = strtolower(trim($email));

        $result = Auth::attempt($email, $password);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect('/login.php');
        }

        $user = $result['user'];

        // Audit log
        $this->auditService->log(
            $user['id'], $user['role'], $user['merchant_id'] ?? null,
            'login', "User logged in",
            ['ip' => Auth::getTrustedClientIp()]
        );

        // Redirect based on role
        if (in_array($user['role'], ['super_admin', 'admin', 'finance', 'support'])) {
            redirect('/admin/dashboard.php');
        } else {
            redirect('/merchant/dashboard.php');
        }
    }

    /**
     * Handle registration
     */
    public function register(): void
    {
        if (!is_post()) return;
        
        Auth::verifyCsrf();

        // SECURITY: IP-based registration throttle
        $ip = Auth::getTrustedClientIp();
        $regKey = RateLimiter::registerKey($ip);
        $maxRegistrations = (int)setting('max_registrations_per_hour', 3);
        $regWindow = 3600; // 1 hour

        $rateCheck = $this->rateLimiter->check($regKey, $maxRegistrations, $regWindow);
        if (!$rateCheck['allowed']) {
            $retryMin = ceil($rateCheck['retry_after'] / 60);
            app_log("Registration rate limited for IP {$ip}", 'SECURITY');
            flash('error', "Terlalu banyak registrasi dari IP ini. Coba lagi dalam {$retryMin} menit.");
            redirect('/register.php');
        }

        $data = [
            'name' => sanitize($_POST['name'] ?? ''),
            'email' => strtolower(trim(sanitize($_POST['email'] ?? ''))),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'business_name' => sanitize($_POST['business_name'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
        ];

        // Input hardening - length limits
        $errors = [];
        if (empty($data['name']) || mb_strlen($data['name']) < 2) {
            $errors[] = 'Nama lengkap minimal 2 karakter.';
        }
        if (mb_strlen($data['name']) > 100) {
            $errors[] = 'Nama terlalu panjang (maks 100 karakter).';
        }
        if (empty($data['email']) || !is_valid_email($data['email'])) {
            $errors[] = 'Email tidak valid.';
        }
        if (mb_strlen($data['email']) > 150) {
            $errors[] = 'Email terlalu panjang.';
        }
        if (empty($data['business_name']) || mb_strlen($data['business_name']) < 3) {
            $errors[] = 'Nama bisnis minimal 3 karakter.';
        }
        if (mb_strlen($data['business_name']) > 150) {
            $errors[] = 'Nama bisnis terlalu panjang (maks 150 karakter).';
        }

        // Password complexity
        $password = $data['password'];
        $minLength = (int)setting('password_min_length', 8);
        if (strlen($password) < $minLength) {
            $errors[] = "Password minimal {$minLength} karakter.";
        }
        if (strlen($password) > 128) {
            $errors[] = 'Password terlalu panjang (maks 128 karakter).';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung huruf dan angka.';
        }
        if ($password !== $data['password_confirm']) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }

        // Phone validation
        if (!empty($data['phone'])) {
            if (!is_valid_phone($data['phone'])) {
                $errors[] = 'Format nomor telepon tidak valid.';
            }
        }

        // Block disposable/suspicious email patterns
        $emailDomain = substr($data['email'], strrpos($data['email'], '@') + 1);
        $blockedDomains = ['tempmail.com', 'throwaway.email', 'guerrillamail.com', 'mailinator.com', 'yopmail.com', 'temp-mail.org'];
        if (in_array($emailDomain, $blockedDomains)) {
            $errors[] = 'Email disposable tidak diizinkan. Gunakan email asli.';
        }

        if (!empty($errors)) {
            foreach ($errors as $err) flash('error', $err);
            redirect('/register.php');
        }

        // Record registration attempt (for throttle)
        $this->rateLimiter->hit($regKey, $regWindow);

        $result = Auth::register($data);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect('/register.php');
        }

        // Audit log
        $this->auditService->log(
            $result['user']['id'], 'merchant', $result['merchant']['id'],
            'register', "New merchant registered: {$data['business_name']}",
            ['email' => $data['email'], 'ip' => $ip]
        );

        flash('success', 'Registrasi berhasil! Silakan login.');
        redirect('/login.php');
    }

    /**
     * Handle logout
     */
    public function logout(): void
    {
        if (Auth::check()) {
            $this->auditService->log(
                Auth::id(), Auth::role(), Auth::merchantId(),
                'logout', 'User logged out', []
            );
        }
        Auth::logout();
        flash('success', 'Anda telah keluar.');
        redirect('/login.php');
    }
}
