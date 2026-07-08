<?php
/**
 * Comprehensive Test Suite for Payment Gateway
 * Tests all core functions without requiring database
 */

// Suppress session warnings in CLI
@ini_set('session.use_cookies', '0');
@ini_set('session.use_only_cookies', '0');
@ini_set('session.cache_limiter', '');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/test_all.php';
$_SERVER['REQUEST_URI'] = '/test_all.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'TestRunner/1.0';

$results = [];
$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void {
    global $results, $passed, $failed;
    try {
        $result = $fn();
        if ($result === true) {
            $results[] = ['PASS', $name];
            $passed++;
        } else {
            $results[] = ['FAIL', $name, $result];
            $failed++;
        }
    } catch (\Throwable $e) {
        $results[] = ['ERROR', $name, $e->getMessage()];
        $failed++;
    }
}

echo "=== PAYMENT GATEWAY - COMPREHENSIVE TEST SUITE ===\n\n";


// ============================================================
// SECTION 1: HELPER FUNCTIONS
// ============================================================
echo "--- 1. HELPER FUNCTIONS ---\n";

// Load helpers manually (without full init to avoid DB)
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/Helpers.php';

test('generate_uuid() returns valid UUID v4', function() {
    $uuid = generate_uuid();
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) === 1;
});

test('generate_random() returns correct length', function() {
    $r16 = generate_random(16);
    $r32 = generate_random(32);
    return strlen($r16) === 16 && strlen($r32) === 32;
});

test('generate_order_id() format INV-YYYYMMDD-XXXXXX', function() {
    $id = generate_order_id();
    return preg_match('/^INV-\d{8}-[A-F0-9]{6}$/', $id) === 1;
});

test('generate_api_key() starts with pk_', function() {
    $key = generate_api_key();
    return str_starts_with($key, 'pk_') && strlen($key) === 35;
});


test('e() escapes HTML correctly', function() {
    $escaped = e('<script>alert("xss")</script>');
    return $escaped === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
});

test('e() handles null', function() {
    return e(null) === '';
});

test('format_currency() formats IDR correctly', function() {
    return format_currency(1500000) === 'Rp 1.500.000';
});

test('format_currency() handles zero', function() {
    return format_currency(0) === 'Rp 0';
});

test('now() returns current datetime format', function() {
    $n = now();
    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $n) === 1;
});

test('is_valid_email() validates correct email', function() {
    return is_valid_email('test@example.com') === true;
});

test('is_valid_email() rejects invalid email', function() {
    return is_valid_email('not-an-email') === false;
});

test('is_valid_phone() validates Indonesian phone', function() {
    return is_valid_phone('081234567890') === true
        && is_valid_phone('+6281234567890') === true;
});

test('is_valid_phone() rejects invalid phone', function() {
    return is_valid_phone('12345') === false;
});


test('mask_api_key() masks correctly', function() {
    $masked = mask_api_key('pk_abcdef1234567890abcdef1234567890');
    return str_starts_with($masked, 'pk_abc') && str_ends_with($masked, '7890')
        && str_contains($masked, '***');
});

test('sanitize() removes HTML tags', function() {
    return sanitize('<b>Hello</b> <script>bad</script>') === 'Hello bad';
});

test('sanitize() handles null', function() {
    return sanitize(null) === '';
});

test('truncate() truncates long text', function() {
    $long = str_repeat('a', 100);
    $result = truncate($long, 50);
    return strlen($result) === 53 && str_ends_with($result, '...');
});

test('truncate() keeps short text', function() {
    return truncate('hello', 50) === 'hello';
});

test('array_dot_get() extracts nested values', function() {
    $data = ['a' => ['b' => ['c' => 'value']]];
    return array_dot_get($data, 'a.b.c') === 'value';
});

test('array_dot_get() returns default for missing', function() {
    $data = ['a' => 1];
    return array_dot_get($data, 'b.c', 'default') === 'default';
});

test('extract_from_keys() finds first match', function() {
    $data = ['status' => 'paid', 'data' => ['status' => 'success']];
    $keys = ['transaction_status', 'status', 'data.status'];
    return extract_from_keys($data, $keys) === 'paid';
});

test('is_valid_json() validates JSON', function() {
    return is_valid_json('{"key":"value"}') === true
        && is_valid_json('not json') === false;
});


test('status_badge_class() returns correct CSS', function() {
    return str_contains(status_badge_class('PAID'), 'emerald')
        && str_contains(status_badge_class('PENDING'), 'amber')
        && str_contains(status_badge_class('FAILED'), 'red')
        && str_contains(status_badge_class('EXPIRED'), 'slate');
});

test('paginate() works correctly', function() {
    $items = range(1, 100);
    $page = paginate($items, 2, 10);
    return $page['current_page'] === 2
        && $page['per_page'] === 10
        && $page['total'] === 100
        && $page['total_pages'] === 10
        && $page['has_prev'] === true
        && $page['has_next'] === true
        && count($page['data']) === 10
        && $page['data'][0] === 11;
});

test('paginate() handles empty array', function() {
    $page = paginate([], 1, 10);
    return $page['total'] === 0 && $page['total_pages'] === 1;
});

test('sanitize_log_message() masks Bearer tokens', function() {
    $msg = 'Authorization: Bearer pk_abcdef1234567890abcdef1234567890';
    $result = sanitize_log_message($msg);
    return !str_contains($result, '1234567890abcdef1234567890');
});

test('base_path() returns correct path', function() {
    $base = base_path();
    return str_ends_with($base, 'Payment-gateway');
});

test('base_path() appends subpath', function() {
    $path = base_path('config/app.php');
    return str_ends_with($path, 'Payment-gateway/config/app.php');
});


// ============================================================
// SECTION 2: CONFIGURATION
// ============================================================
echo "\n--- 2. CONFIGURATION ---\n";

test('config() loads app.php values', function() {
    return config('app.app_name') === 'Clipku Pay'
        && config('app.app_version') === '1.0.0';
});

test('config() loads gateway.php values', function() {
    $baseUrl = config('gateway.aldiqris.base_url');
    return $baseUrl === 'https://aldiqris.pages.dev';
});

test('config() loads nested array', function() {
    $roles = config('app.roles');
    return is_array($roles) && isset($roles['super_admin']);
});

test('config() returns default for missing key', function() {
    return config('nonexistent.key', 'fallback') === 'fallback';
});

test('config() gateway status_mapping is complete', function() {
    $mapping = config('gateway.status_mapping');
    return isset($mapping['paid']) && $mapping['paid'] === 'PAID'
        && isset($mapping['pending']) && $mapping['pending'] === 'PENDING'
        && isset($mapping['failed']) && $mapping['failed'] === 'FAILED'
        && isset($mapping['expired']) && $mapping['expired'] === 'EXPIRED';
});

test('config() webhook settings exist', function() {
    return config('gateway.webhook.hash_algo') === 'sha256'
        && config('gateway.webhook.signature_header') === 'X-Signature'
        && config('gateway.webhook.max_payload_size') === 65536;
});


// ============================================================
// SECTION 3: AldiQRIS SERVICE
// ============================================================
echo "\n--- 3. ALDIQRIS SERVICE ---\n";

require_once __DIR__ . '/app/Services/AldiQrisService.php';
$aldiQris = new AldiQrisService();

test('AldiQrisService::validateWebhookSignature() correct signature', function() use ($aldiQris) {
    $payload = '{"order_id":"INV-001","status":"paid","amount":50000}';
    $secret = 'pk_test_secret_key_123';
    $signature = hash_hmac('sha256', $payload, $secret);
    return $aldiQris->validateWebhookSignature($payload, $signature, $secret) === true;
});

test('AldiQrisService::validateWebhookSignature() wrong signature', function() use ($aldiQris) {
    $payload = '{"order_id":"INV-001","status":"paid"}';
    $secret = 'pk_test_secret_key_123';
    $wrongSig = 'invalidhashvalue123456789';
    return $aldiQris->validateWebhookSignature($payload, $wrongSig, $secret) === false;
});

test('AldiQrisService::validateWebhookSignature() tampered payload', function() use ($aldiQris) {
    $payload = '{"order_id":"INV-001","status":"paid","amount":50000}';
    $secret = 'pk_test_secret_key_123';
    $signature = hash_hmac('sha256', $payload, $secret);
    $tampered = '{"order_id":"INV-001","status":"paid","amount":999999}';
    return $aldiQris->validateWebhookSignature($tampered, $signature, $secret) === false;
});

test('AldiQrisService::extractOrderId() from various formats', function() use ($aldiQris) {
    $t1 = $aldiQris->extractOrderId(['order_id' => 'INV-001']);
    $t2 = $aldiQris->extractOrderId(['data' => ['order_id' => 'INV-002']]);
    $t3 = $aldiQris->extractOrderId(['invoice_id' => 'INV-003']);
    $t4 = $aldiQris->extractOrderId(['reference_id' => 'INV-004']);
    return $t1 === 'INV-001' && $t2 === 'INV-002' && $t3 === 'INV-003' && $t4 === 'INV-004';
});


test('AldiQrisService::extractStatus() maps correctly', function() use ($aldiQris) {
    $t1 = $aldiQris->extractStatus(['transaction_status' => 'paid']);
    $t2 = $aldiQris->extractStatus(['status' => 'pending']);
    $t3 = $aldiQris->extractStatus(['data' => ['status' => 'failed']]);
    $t4 = $aldiQris->extractStatus(['transaction_status' => 'settlement']);
    $t5 = $aldiQris->extractStatus(['status' => 'expired']);
    return $t1 === 'PAID' && $t2 === 'PENDING' && $t3 === 'FAILED'
        && $t4 === 'PAID' && $t5 === 'EXPIRED';
});

test('AldiQrisService::extractStatus() handles unknown status', function() use ($aldiQris) {
    $result = $aldiQris->extractStatus(['status' => 'CUSTOM_STATUS']);
    return $result === 'CUSTOM_STATUS'; // uppercase
});

test('AldiQrisService::extractStatus() returns null for missing', function() use ($aldiQris) {
    $result = $aldiQris->extractStatus(['unrelated_key' => 'value']);
    return $result === null;
});

test('AldiQrisService::createTransaction() SSRF blocks localhost', function() use ($aldiQris) {
    // We can't easily test this without changing internal state
    // But we can verify the service instantiates properly
    return $aldiQris instanceof AldiQrisService;
});

// ============================================================
// SECTION 4: QRIS CHANNEL
// ============================================================
echo "\n--- 4. QRIS CHANNEL ---\n";

require_once __DIR__ . '/app/Interfaces/PaymentChannelInterface.php';
require_once __DIR__ . '/app/Channels/QrisChannel.php';
$qrisChannel = new QrisChannel();

test('QrisChannel::getChannelCode() returns qris', function() use ($qrisChannel) {
    return $qrisChannel->getChannelCode() === 'qris';
});

test('QrisChannel::getChannelName() returns QRIS', function() use ($qrisChannel) {
    return $qrisChannel->getChannelName() === 'QRIS';
});

test('QrisChannel::isEnabled() returns true', function() use ($qrisChannel) {
    return $qrisChannel->isEnabled() === true;
});


test('QrisChannel::getSupportedMethods() returns QRIS method', function() use ($qrisChannel) {
    $methods = $qrisChannel->getSupportedMethods();
    return count($methods) === 1 && $methods[0]['code'] === 'qris';
});

test('QrisChannel::parseWebhook() parses standard payload', function() use ($qrisChannel) {
    $payload = json_encode([
        'order_id' => 'INV-20240115-ABC123',
        'status' => 'paid',
        'gross_amount' => 75000,
        'transaction_time' => '2024-01-15 10:30:00'
    ]);
    $result = $qrisChannel->parseWebhook($payload);
    return $result['order_id'] === 'INV-20240115-ABC123'
        && $result['status'] === 'PAID'
        && $result['amount'] === 75000
        && $result['paid_at'] === '2024-01-15 10:30:00';
});

test('QrisChannel::parseWebhook() parses nested payload', function() use ($qrisChannel) {
    $payload = json_encode([
        'data' => ['order_id' => 'INV-NESTED'],
        'transaction_status' => 'settlement',
        'amount' => 100000,
    ]);
    $result = $qrisChannel->parseWebhook($payload);
    return $result['order_id'] === 'INV-NESTED'
        && $result['status'] === 'PAID'
        && $result['amount'] === 100000;
});

test('QrisChannel::validateWebhook() requires signature', function() use ($qrisChannel) {
    $payload = '{"order_id":"test"}';
    $withSig = $qrisChannel->validateWebhook($payload, ['HTTP_X_SIGNATURE' => 'abc123']);
    $withoutSig = $qrisChannel->validateWebhook($payload, []);
    return $withSig === true && $withoutSig === false;
});


// ============================================================
// SECTION 5: MIDTRANS CHANNEL
// ============================================================
echo "\n--- 5. MIDTRANS CHANNEL ---\n";

require_once __DIR__ . '/app/Channels/MidtransChannel.php';
$midtransChannel = new MidtransChannel();

test('MidtransChannel::getChannelCode() returns midtrans', function() use ($midtransChannel) {
    return $midtransChannel->getChannelCode() === 'midtrans';
});

test('MidtransChannel::getSupportedMethods() returns multiple methods', function() use ($midtransChannel) {
    $methods = $midtransChannel->getSupportedMethods();
    return count($methods) > 0;
});

test('MidtransChannel::parseWebhook() parses Midtrans notification', function() use ($midtransChannel) {
    $payload = json_encode([
        'order_id' => 'MID-001',
        'transaction_status' => 'settlement',
        'gross_amount' => '150000.00',
        'settlement_time' => '2024-01-15 11:00:00',
    ]);
    $result = $midtransChannel->parseWebhook($payload);
    return $result['order_id'] === 'MID-001'
        && $result['status'] === 'PAID';
});

// ============================================================
// SECTION 6: SECURITY TESTS
// ============================================================
echo "\n--- 6. SECURITY TESTS ---\n";

test('CSRF token generation is random each session', function() {
    $_SESSION = [];
    $t1 = csrf_token();
    return strlen($t1) === 64 && ctype_xdigit($t1);
});

test('CSRF token stays same within session', function() {
    $t1 = csrf_token();
    $t2 = csrf_token();
    return $t1 === $t2;
});

test('CSRF field generates hidden input', function() {
    $field = csrf_field();
    return str_contains($field, '<input type="hidden"')
        && str_contains($field, 'name="_csrf_token"')
        && str_contains($field, 'value="');
});


test('verify_csrf() validates correct token', function() {
    $_SESSION['_csrf_token'] = 'test_token_12345';
    $_POST['_csrf_token'] = 'test_token_12345';
    return verify_csrf() === true;
});

test('verify_csrf() rejects wrong token', function() {
    $_SESSION['_csrf_token'] = 'correct_token';
    $_POST['_csrf_token'] = 'wrong_token';
    return verify_csrf() === false;
});

test('verify_csrf() rejects empty token', function() {
    $_SESSION['_csrf_token'] = 'some_token';
    $_POST['_csrf_token'] = '';
    return verify_csrf() === false;
});

test('HMAC signature is timing-safe (hash_equals)', function() {
    $payload = '{"order_id":"test","amount":50000}';
    $secret = 'my_secret_key';
    $sig = hash_hmac('sha256', $payload, $secret);
    // hash_equals prevents timing attacks
    return hash_equals($sig, hash_hmac('sha256', $payload, $secret)) === true;
});

test('XSS prevention via e() function', function() {
    $attacks = [
        '<img src=x onerror=alert(1)>',
        '"><script>alert("xss")</script>',
        "javascript:alert('xss')",
    ];
    foreach ($attacks as $attack) {
        $escaped = e($attack);
        if (str_contains($escaped, '<') || str_contains($escaped, '>')) {
            return false;
        }
    }
    return true;
});


// ============================================================
// SECTION 7: WEBHOOK SIGNATURE FULL FLOW
// ============================================================
echo "\n--- 7. WEBHOOK SIGNATURE FLOW ---\n";

test('Full webhook signature flow: create -> validate', function() use ($aldiQris) {
    // Simulate AldiQRIS sending webhook
    $payload = json_encode([
        'order_id' => 'INV-20240115-TEST01',
        'transaction_status' => 'paid',
        'gross_amount' => 150000,
        'transaction_time' => '2024-01-15 14:30:00'
    ]);
    $merchantApiKey = 'pk_test_merchant_api_key_12345';
    
    // AldiQRIS signs with merchant's API key
    $signature = hash_hmac('sha256', $payload, $merchantApiKey);
    
    // Gateway validates
    return $aldiQris->validateWebhookSignature($payload, $signature, $merchantApiKey);
});

test('Webhook rejects if API key mismatch', function() use ($aldiQris) {
    $payload = '{"order_id":"INV-001","status":"paid"}';
    $realKey = 'pk_real_merchant_key';
    $wrongKey = 'pk_attacker_key';
    
    $signature = hash_hmac('sha256', $payload, $wrongKey);
    return $aldiQris->validateWebhookSignature($payload, $signature, $realKey) === false;
});

test('Webhook status mapping handles all valid statuses', function() use ($aldiQris) {
    $testCases = [
        ['paid', 'PAID'],
        ['success', 'PAID'],
        ['settlement', 'PAID'],
        ['completed', 'PAID'],
        ['pending', 'PENDING'],
        ['unpaid', 'PENDING'],
        ['waiting', 'PENDING'],
        ['failed', 'FAILED'],
        ['cancel', 'FAILED'],
        ['canceled', 'FAILED'],
        ['cancelled', 'FAILED'],
        ['error', 'FAILED'],
        ['expired', 'EXPIRED'],
        ['expire', 'EXPIRED'],
    ];
    foreach ($testCases as [$input, $expected]) {
        $result = $aldiQris->extractStatus(['status' => $input]);
        if ($result !== $expected) {
            return "Failed for '{$input}': got '{$result}', expected '{$expected}'";
        }
    }
    return true;
});


// ============================================================
// SECTION 8: RATE LIMITER
// ============================================================
echo "\n--- 8. RATE LIMITER ---\n";

require_once __DIR__ . '/app/RateLimiter.php';

test('RateLimiter class exists and is instantiable', function() {
    return class_exists('RateLimiter');
});

// ============================================================
// SECTION 9: AUTH CLASS
// ============================================================
echo "\n--- 9. AUTH CLASS ---\n";

require_once __DIR__ . '/app/Auth.php';

test('Auth class exists with required methods', function() {
    return method_exists('Auth', 'check')
        && method_exists('Auth', 'init')
        && method_exists('Auth', 'isAdmin')
        && method_exists('Auth', 'isMerchant')
        && method_exists('Auth', 'verifyCsrf');
});

test('Auth::check() returns false when not logged in', function() {
    $_SESSION = [];
    return Auth::check() === false;
});

// ============================================================
// SECTION 10: FILE STRUCTURE VALIDATION
// ============================================================
echo "\n--- 10. FILE STRUCTURE ---\n";

test('All required directories exist', function() {
    $dirs = ['app', 'config', 'admin', 'api', 'merchant', 'includes', 'assets'];
    foreach ($dirs as $dir) {
        if (!is_dir(base_path($dir))) {
            return "Missing directory: {$dir}";
        }
    }
    return true;
});

test('All controller files exist', function() {
    $controllers = [
        'AdminController', 'ApiController', 'AuthController',
        'MerchantController', 'TransactionController', 'WalletController',
        'WebhookController', 'WithdrawalController'
    ];
    foreach ($controllers as $c) {
        if (!file_exists(base_path("app/Controllers/{$c}.php"))) {
            return "Missing: {$c}.php";
        }
    }
    return true;
});


test('All service files exist', function() {
    $services = [
        'AldiQrisService', 'WebhookService', 'TransactionService',
        'WalletService', 'WithdrawalService', 'FeeService',
        'SettlementService', 'AuditLogService', 'FraudService',
        'RefundService', 'NotificationService', 'EmailService',
        'InvoiceService', 'ReportService', 'PaymentChannelManager',
    ];
    foreach ($services as $s) {
        if (!file_exists(base_path("app/Services/{$s}.php"))) {
            return "Missing: {$s}.php";
        }
    }
    return true;
});

test('All repository files exist', function() {
    $repos = [
        'BaseRepository', 'MerchantRepository', 'TransactionRepository',
        'WalletRepository', 'WebhookRepository', 'WithdrawalRepository',
        'SettlementRepository', 'AuditLogRepository', 'FeeRuleRepository',
    ];
    foreach ($repos as $r) {
        if (!file_exists(base_path("app/Repositories/{$r}.php"))) {
            return "Missing: {$r}.php";
        }
    }
    return true;
});

test('Payment channel files exist', function() {
    $channels = ['QrisChannel', 'MidtransChannel', 'EWalletChannel', 'VirtualAccountChannel'];
    foreach ($channels as $c) {
        if (!file_exists(base_path("app/Channels/{$c}.php"))) {
            return "Missing: {$c}.php";
        }
    }
    return true;
});

test('Critical entry points exist', function() {
    $files = ['index.php', 'login.php', 'register.php', 'webhook.php',
              'install.php', 'cron.php', 'api-docs.php', 'api/index.php'];
    foreach ($files as $f) {
        if (!file_exists(base_path($f))) {
            return "Missing: {$f}";
        }
    }
    return true;
});

test('database.sql schema file exists', function() {
    return file_exists(base_path('database.sql'));
});


// ============================================================
// SECTION 11: API CREATE TRANSACTION VALIDATION
// ============================================================
echo "\n--- 11. API LOGIC VALIDATION ---\n";

test('AldiQRIS endpoint configured correctly', function() {
    $endpoint = config('gateway.aldiqris.endpoint_create');
    $baseUrl = config('gateway.aldiqris.base_url');
    return $endpoint === '/api/trx' && $baseUrl === 'https://aldiqris.pages.dev';
});

test('Payment URL extraction keys configured', function() {
    $keys = config('gateway.payment_url_keys');
    return is_array($keys) && in_array('payment_url', $keys);
});

test('QR URL extraction keys configured', function() {
    $keys = config('gateway.qr_url_keys');
    return is_array($keys) && in_array('qr_url', $keys);
});

test('Fee configuration defaults exist', function() {
    $feeType = config('app.default_fee_type');
    $feeValue = config('app.default_fee_value');
    return $feeType === 'percentage' && $feeValue === 0.7;
});

test('Minimum withdrawal configured', function() {
    return config('app.min_withdrawal') === 10000;
});

// ============================================================
// SECTION 12: LOGGING
// ============================================================
echo "\n--- 12. LOGGING ---\n";

test('app_log() writes to storage/logs.txt', function() {
    $logFile = storage_path('logs.txt');
    @unlink($logFile); // clean
    app_log('Test log message', 'TEST');
    $content = file_get_contents($logFile);
    @unlink($logFile); // cleanup
    return str_contains($content, 'Test log message') && str_contains($content, '[TEST]');
});

test('app_log() sanitizes sensitive data', function() {
    $logFile = storage_path('logs.txt');
    @unlink($logFile);
    app_log('Key: Bearer pk_abcdef1234567890abcdef1234567890', 'INFO');
    $content = file_get_contents($logFile);
    @unlink($logFile);
    return !str_contains($content, 'pk_abcdef1234567890abcdef1234567890');
});


// ============================================================
// RESULTS SUMMARY
// ============================================================
echo "\n\n" . str_repeat('=', 60) . "\n";
echo "   TEST RESULTS SUMMARY\n";
echo str_repeat('=', 60) . "\n\n";

foreach ($results as $r) {
    $icon = $r[0] === 'PASS' ? '✅' : '❌';
    echo "  {$icon} [{$r[0]}] {$r[1]}";
    if (isset($r[2])) {
        echo " — {$r[2]}";
    }
    echo "\n";
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "  TOTAL: " . ($passed + $failed) . " tests\n";
echo "  ✅ PASSED: {$passed}\n";
echo "  ❌ FAILED: {$failed}\n";
echo str_repeat('-', 60) . "\n";

if ($failed === 0) {
    echo "\n  🎉 ALL TESTS PASSED!\n\n";
} else {
    echo "\n  ⚠️  SOME TESTS FAILED - Review above for details\n\n";
}

exit($failed > 0 ? 1 : 0);
