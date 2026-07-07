<?php
/**
 * Auth Controller
 * Handles login, register, logout
 */

require_once base_path('app/Helpers.php');
require_once base_path('app/Auth.php');
require_once base_path('app/Services/AuditLogService.php');

class AuthController
{
    private AuditLogService $auditService;

    public function __construct()
    {
        $this->auditService = new AuditLogService();
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

        $result = Auth::attempt($email, $password);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect('/login.php');
        }

        $user = $result['user'];

        // Audit log
        $this->auditService->log(
            $user['id'], $user['role'], $user['merchant_id'] ?? null,
            'login', "User {$user['name']} logged in",
            ['email' => $user['email']]
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

        $data = [
            'name' => sanitize($_POST['name'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'business_name' => sanitize($_POST['business_name'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
        ];

        // Validate
        $errors = [];
        if (empty($data['name'])) $errors[] = 'Nama lengkap wajib diisi.';
        if (empty($data['email']) || !is_valid_email($data['email'])) $errors[] = 'Email tidak valid.';
        if (empty($data['business_name'])) $errors[] = 'Nama bisnis wajib diisi.';
        if (strlen($data['password']) < 8) $errors[] = 'Password minimal 8 karakter.';
        if ($data['password'] !== $data['password_confirm']) $errors[] = 'Konfirmasi password tidak cocok.';
        if (!empty($data['phone']) && !is_valid_phone($data['phone'])) $errors[] = 'Format nomor telepon tidak valid.';

        if (!empty($errors)) {
            foreach ($errors as $err) flash('error', $err);
            redirect('/register.php');
        }

        $result = Auth::register($data);

        if (!$result['success']) {
            flash('error', $result['message']);
            redirect('/register.php');
        }

        // Audit log
        $this->auditService->log(
            $result['user']['id'], 'merchant', $result['merchant']['id'],
            'register', "New merchant registered: {$data['business_name']}",
            ['email' => $data['email'], 'business_name' => $data['business_name']]
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
