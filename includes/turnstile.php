<?php
/**
 * Cloudflare Turnstile Helper
 * Renders widget and validates server-side
 */

/**
 * Check if Turnstile is enabled
 */
function turnstile_enabled(): bool
{
    return setting('turnstile_enabled', '0') === '1'
        && !empty(setting('turnstile_site_key', ''))
        && !empty(setting('turnstile_secret_key', ''));
}

/**
 * Render Turnstile widget HTML
 */
function turnstile_widget(): string
{
    if (!turnstile_enabled()) return '';
    $siteKey = setting('turnstile_site_key', '');
    return '<div class="cf-turnstile mb-4" data-sitekey="' . e($siteKey) . '" data-theme="light"></div>';
}

/**
 * Render Turnstile script tag (place in <head> or before </body>)
 */
function turnstile_script(): string
{
    if (!turnstile_enabled()) return '';
    return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

/**
 * Validate Turnstile response server-side
 * Returns true if valid or if Turnstile is disabled
 */
function turnstile_verify(): bool
{
    if (!turnstile_enabled()) return true;

    $token = $_POST['cf-turnstile-response'] ?? '';
    if (empty($token)) return false;

    $secretKey = setting('turnstile_secret_key', '');
    $ip = get_client_ip();

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $ip,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return !empty($data['success']);
}
