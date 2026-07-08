<?php
/**
 * Helper Functions
 * Payment Gateway SaaS Multi Merchant
 */

require_once dirname(__DIR__) . '/app/Database.php';

/**
 * Get application config - merges file config with dynamic DB settings
 * DB settings override file config for keys that exist in both
 */
function config(string $key, mixed $default = null): mixed
{
    static $configs = [];
    static $dbSettings = null;
    
    // Load DB settings once (lazy)
    if ($dbSettings === null) {
        $dbSettings = load_db_settings();
    }
    
    // Check if this key has a DB override (flat key like 'app_name', 'aldiqris_base_url')
    $flatKey = str_replace('.', '_', $key);
    if (isset($dbSettings[$flatKey])) {
        return $dbSettings[$flatKey];
    }
    
    // Also check the raw key without prefix (e.g. 'gateway.aldiqris.base_url' -> 'aldiqris_base_url')
    $parts = explode('.', $key);
    if (count($parts) >= 2) {
        // Try removing first segment: 'gateway.aldiqris.base_url' -> 'aldiqris_base_url'
        $dbKey = implode('_', array_slice($parts, 1));
        if (isset($dbSettings[$dbKey])) {
            return $dbSettings[$dbKey];
        }
    }
    
    // Fallback to file-based config
    $file = $parts[0];
    
    if (!isset($configs[$file])) {
        $path = dirname(__DIR__) . '/config/' . $file . '.php';
        if (file_exists($path)) {
            $configs[$file] = require $path;
        } else {
            return $default;
        }
    }
    
    $value = $configs[$file];
    array_shift($parts);
    
    foreach ($parts as $part) {
        if (is_array($value) && isset($value[$part])) {
            $value = $value[$part];
        } else {
            return $default;
        }
    }
    
    return $value;
}

/**
 * Load all dynamic settings from database (MySQL)
 */
function load_db_settings(): array
{
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * Get a dynamic setting with fallback to file config
 */
function setting(string $key, mixed $default = null): mixed
{
    static $settings = null;
    if ($settings === null) {
        $settings = load_db_settings();
    }
    return $settings[$key] ?? $default;
}

/**
 * Get base path
 */
function base_path(string $path = ''): string
{
    return dirname(__DIR__) . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Get storage path
 */
function storage_path(string $path = ''): string
{
    return base_path('storage') . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Generate UUID v4
 */
function generate_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Generate random string
 */
function generate_random(int $length = 16): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate order ID
 */
function generate_order_id(): string
{
    $date = date('Ymd');
    $random = strtoupper(substr(generate_random(6), 0, 6));
    return "INV-{$date}-{$random}";
}

/**
 * Generate API key
 */
function generate_api_key(): string
{
    return 'pk_' . generate_random(32);
}

/**
 * Escape HTML output
 */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency (Indonesian Rupiah)
 */
function format_currency(float|int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Format date
 */
function format_date(string $datetime, string $format = 'd M Y H:i'): string
{
    if (empty($datetime)) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Get current datetime in ISO 8601 format
 */
function now(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Get client IP address
 * WARNING: This is for logging/display only. For security decisions, use Auth::getTrustedClientIp()
 */
function get_client_ip(): string
{
    // For security-critical operations, only trust REMOTE_ADDR
    // X-Forwarded-For headers are ONLY trusted if behind a known proxy
    // configured via 'trusted_proxy_ips' setting
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get user agent
 */
function get_user_agent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Redirect to URL
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Return JSON response
 */
function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Set flash message
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash messages
 */
function get_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Generate CSRF token
 * Uses per-session token with rotation after successful verification
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Get CSRF hidden input field
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify CSRF token
 * SECURITY: Rotates token after successful verification to prevent replay
 */
function verify_csrf(): bool
{
    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['_csrf_token'] ?? '';
    
    if (empty($token) || empty($sessionToken)) {
        return false;
    }
    
    $valid = hash_equals($sessionToken, $token);
    
    if ($valid) {
        // Rotate CSRF token after successful use (prevent replay attacks)
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $valid;
}

/**
 * Validate email
 */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Indonesian format)
 */
function is_valid_phone(string $phone): bool
{
    return preg_match('/^(\+62|62|0)8[1-9][0-9]{6,11}$/', $phone) === 1;
}

/**
 * Mask API key for display
 */
function mask_api_key(string $key): string
{
    if (strlen($key) <= 8) {
        return str_repeat('*', strlen($key));
    }
    return substr($key, 0, 6) . str_repeat('*', strlen($key) - 10) . substr($key, -4);
}

/**
 * Extract nested value from array using dot notation
 */
function array_dot_get(array $array, string $key, mixed $default = null): mixed
{
    $keys = explode('.', $key);
    $value = $array;
    
    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    
    return $value;
}

/**
 * Extract value from array using multiple possible keys
 */
function extract_from_keys(array $data, array $keys, mixed $default = null): mixed
{
    foreach ($keys as $key) {
        $value = array_dot_get($data, $key);
        if ($value !== null) {
            return $value;
        }
    }
    return $default;
}

/**
 * Truncate text
 */
function truncate(string $text, int $length = 50, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Get status badge CSS class
 */
function status_badge_class(string $status): string
{
    return match(strtoupper($status)) {
        'PAID', 'SUCCESS', 'COMPLETED', 'ACTIVE', 'APPROVED', 'TRANSFERRED' => 'bg-emerald-100 text-emerald-800',
        'PENDING', 'WAITING', 'REVIEWING', 'PROCESSING' => 'bg-amber-100 text-amber-800',
        'FAILED', 'ERROR', 'REJECTED', 'SUSPENDED' => 'bg-red-100 text-red-800',
        'EXPIRED', 'CANCELED', 'INACTIVE' => 'bg-slate-100 text-slate-800',
        default => 'bg-blue-100 text-blue-800',
    };
}

/**
 * Sanitize input string
 */
function sanitize(mixed $value): string
{
    if ($value === null) return '';
    return trim(strip_tags((string)$value));
}

/**
 * Check if request is AJAX
 */
function is_ajax(): bool
{
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
           (!empty($_SERVER['HTTP_ACCEPT']) && 
            str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
}

/**
 * Check if request method is POST
 */
function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Log message to file
 * SECURITY: Automatically sanitizes sensitive data (API keys, passwords)
 */
function app_log(string $message, string $level = 'INFO'): void
{
    $logFile = storage_path('logs.txt');
    
    // Sanitize sensitive data from log messages
    $message = sanitize_log_message($message);
    
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Sanitize sensitive data from log messages
 * Masks API keys, passwords, tokens in log output
 */
function sanitize_log_message(string $message): string
{
    // Mask Bearer tokens: "Bearer pk_abc123..." -> "Bearer pk_ab****"
    $message = preg_replace('/Bearer\s+([a-zA-Z0-9_]{6})[a-zA-Z0-9_]+/i', 'Bearer $1****', $message);
    
    // Mask API keys in JSON: "api_key":"pk_..." -> "api_key":"[REDACTED]"
    $message = preg_replace('/(api_key|secret_key|password|token)(["\s:=]+)(["\']?)([^"\'&\s]{8})[^"\'&\s]*/i', '$1$2$3$4****', $message);
    
    // Mask pk_ prefixed keys anywhere
    $message = preg_replace('/pk_[a-f0-9]{6}[a-f0-9]+/', 'pk_******', $message);
    
    return $message;
}

/**
 * Debug dump and die
 */
function dd(mixed ...$vars): never
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre>';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    exit;
}

/**
 * Get pagination data
 */
function paginate(array $items, int $page = 1, int $perPage = 20): array
{
    $total = count($items);
    $totalPages = (int)max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'data' => array_slice($items, $offset, $perPage),
        'current_page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages,
    ];
}

/**
 * Get the app URL
 */
function app_url(string $path = ''): string
{
    $url = config('app.app_url', 'http://localhost');
    return rtrim($url, '/') . '/' . ltrim($path, '/');
}

/**
 * Check if value is valid JSON
 */
function is_valid_json(string $string): bool
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Validate webhook URL is safe (not targeting internal/private networks).
 * SECURITY: Prevents SSRF attacks by blocking private IPs, loopback,
 * link-local (AWS metadata), and reserved address ranges.
 *
 * @param string $url URL to validate
 * @return array ['safe' => bool, 'reason' => string]
 */
function validate_webhook_url(string $url): array
{
    // Must be a valid URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['safe' => false, 'reason' => 'URL tidak valid.'];
    }

    // Must use http or https
    $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
    if (!in_array($scheme, ['http', 'https'])) {
        return ['safe' => false, 'reason' => 'Hanya http/https yang diizinkan.'];
    }

    $host = parse_url($url, PHP_URL_HOST);
    if (empty($host)) {
        return ['safe' => false, 'reason' => 'Host tidak valid.'];
    }

    // Block obvious localhost variants
    $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]'];
    if (in_array(strtolower($host), $blockedHosts)) {
        return ['safe' => false, 'reason' => 'Tidak boleh mengarah ke localhost.'];
    }

    // Resolve hostname to IP
    $ip = gethostbyname($host);
    if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
        // DNS resolution failed
        return ['safe' => false, 'reason' => 'Host tidak dapat di-resolve.'];
    }

    // If host is already an IP, use it directly
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ip = $host;
    }

    // Block private and reserved IP ranges
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['safe' => false, 'reason' => 'URL tidak boleh mengarah ke jaringan internal/private.'];
    }

    // Block link-local (169.254.x.x - AWS/cloud metadata endpoint)
    if (str_starts_with($ip, '169.254.')) {
        return ['safe' => false, 'reason' => 'URL tidak boleh mengarah ke link-local address.'];
    }

    // Block loopback range (127.0.0.0/8)
    if (str_starts_with($ip, '127.')) {
        return ['safe' => false, 'reason' => 'URL tidak boleh mengarah ke loopback.'];
    }

    return ['safe' => true, 'reason' => ''];
}
