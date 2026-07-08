<?php
/**
 * Google OAuth - Callback Handler
 * Receives authorization code, exchanges for token, gets user info
 * Creates/links user account with Google profile picture
 */
require_once __DIR__ . '/../includes/init.php';
require_once base_path('app/Repositories/UserRepository.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Services/AuditLogService.php');

// Verify state for CSRF
$state = $_GET['state'] ?? '';
$savedState = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);

if (empty($state) || !hash_equals($savedState, $state)) {
    flash('error', 'Sesi tidak valid. Silakan coba lagi.');
    redirect('/login.php');
}

// Check for errors from Google
if (!empty($_GET['error'])) {
    flash('error', 'Login dibatalkan.');
    redirect('/login.php');
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    flash('error', 'Kode otorisasi tidak ditemukan.');
    redirect('/login.php');
}

$clientId = setting('google_client_id', '');
$clientSecret = setting('google_client_secret', '');
$redirectUri = setting('google_redirect_uri', app_url('auth/google-callback.php'));

// Exchange code for access token
$tokenResponse = exchangeCodeForToken($code, $clientId, $clientSecret, $redirectUri);
if (!$tokenResponse) {
    flash('error', 'Gagal mendapatkan token dari Google.');
    redirect('/login.php');
}

$accessToken = $tokenResponse['access_token'] ?? '';
if (empty($accessToken)) {
    flash('error', 'Token akses tidak valid.');
    redirect('/login.php');
}

// Get user info from Google
$googleUser = getGoogleUserInfo($accessToken);
if (!$googleUser || empty($googleUser['email'])) {
    flash('error', 'Gagal mendapatkan informasi akun Google.');
    redirect('/login.php');
}

$email = $googleUser['email'];
$name = $googleUser['name'] ?? '';
$avatar = $googleUser['picture'] ?? '';
$googleId = $googleUser['sub'] ?? '';

// Find or create user
$userRepo = new UserRepository();
$merchantRepo = new MerchantRepository();
$auditService = new AuditLogService();

$user = $userRepo->findByEmail($email);

if ($user) {
    // Existing user - update avatar if changed
    $updates = ['last_login_at' => now()];
    if (!empty($avatar) && ($user['avatar_url'] ?? '') !== $avatar) {
        $updates['avatar_url'] = $avatar;
    }
    if (!empty($googleId) && empty($user['google_id'])) {
        $updates['google_id'] = $googleId;
    }
    $userRepo->update($user['id'], $updates);
    $user = array_merge($user, $updates);

} else {
    // New user - create merchant account
    $merchantId = generate_uuid();
    $userId = generate_uuid();

    // Create merchant
    $merchantRepo->create([
        'id' => $merchantId,
        'business_name' => $name,
        'owner_name' => $name,
        'email' => $email,
        'phone' => '',
        'status' => 'pending',
        'api_key' => generate_api_key(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create user (no password - Google only) with account-level API key
    $newUser = [
        'id' => $userId,
        'merchant_id' => $merchantId,
        'name' => $name,
        'email' => $email,
        'password_hash' => '', // No password for Google users
        'api_key' => generate_api_key(),
        'role' => 'merchant',
        'status' => 'active',
        'email_verified' => 1,
        'google_id' => $googleId,
        'avatar_url' => $avatar,
        'last_login_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ];
    require_once base_path('app/Schema.php');
    if (!Schema::accountApiKeyReady()) {
        unset($newUser['api_key']);
    }
    $userRepo->create($newUser);

    $user = $userRepo->find($userId);
    $auditService->log($userId, 'merchant', $merchantId, 'register_google', "New merchant registered via Google: {$email}", []);
}

// Login the user
Auth::loginUser($user);

// Store avatar in session for sidebar display
$_SESSION['user_avatar'] = $avatar;
$_SESSION['user_name'] = $user['name'] ?? $name;

$auditService->log($user['id'], $user['role'] ?? 'merchant', $user['merchant_id'] ?? null, 'login_google', "Login via Google: {$email}", []);

flash('success', 'Selamat datang, ' . e($name) . '!');

// Redirect based on role
if (in_array($user['role'] ?? '', ['super_admin', 'admin', 'finance', 'support'])) {
    redirect('/admin/dashboard.php');
} else {
    redirect('/merchant/dashboard.php');
}

// =========================================================
// HELPER FUNCTIONS
// =========================================================

function exchangeCodeForToken(string $code, string $clientId, string $clientSecret, string $redirectUri): ?array
{
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function getGoogleUserInfo(string $accessToken): ?array
{
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
