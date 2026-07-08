<?php
/**
 * Google OAuth - Redirect to Google
 * Initiates the OAuth 2.0 flow
 */
require_once __DIR__ . '/../includes/init.php';

if (Auth::check()) {
    redirect('/');
}

$clientId = setting('google_client_id', '');
$redirectUri = setting('google_redirect_uri', app_url('auth/google-callback.php'));

if (empty($clientId)) {
    flash('error', 'Login dengan Google belum dikonfigurasi.');
    redirect('/login.php');
}

// Generate state token for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

// Build Google OAuth URL
$params = http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account',
]);

$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
header('Location: ' . $googleAuthUrl);
exit;
