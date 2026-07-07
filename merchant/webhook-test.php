<?php
/**
 * Webhook Testing Tool
 * Merchant can send test payload to their webhook URL
 */

require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();
$merchant = $controller->getMerchant();

$testResult = null;

if (is_post()) {
    Auth::verifyCsrf();
    
    $webhookUrl = $merchant['webhook_url'] ?? '';
    if (empty($webhookUrl)) {
        flash('error', 'Webhook URL belum dikonfigurasi.');
        redirect('/merchant/webhook-test.php');
    }

    // Build test payload
    $testPayload = [
        'event' => 'payment.test',
        'transaction_id' => 'test-' . generate_random(8),
        'order_id' => 'TEST-' . date('Ymd') . '-' . strtoupper(generate_random(4)),
        'status' => 'settlement',
        'amount' => (int)($_POST['test_amount'] ?? 50000),
        'fee' => 350,
        'net_amount' => (int)($_POST['test_amount'] ?? 50000) - 350,
        'paid_at' => now(),
        'timestamp' => now(),
        '_test' => true,
    ];

    // Sign payload
    $jsonPayload = json_encode($testPayload);
    $signature = hash_hmac('sha256', $jsonPayload, $merchant['api_key']);

    // Send test webhook
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $webhookUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Signature: ' . $signature,
            'User-Agent: PayGate-Webhook-Test/1.0',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $testResult = [
        'url' => $webhookUrl,
        'payload' => $testPayload,
        'signature' => $signature,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'time_ms' => (int)($totalTime * 1000),
        'success' => ($httpCode >= 200 && $httpCode < 300),
    ];
}

$pageTitle = 'Webhook Test';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-2">Test Webhook</h3>
        <p class="text-sm text-slate-500 mb-4">Kirim test payload ke webhook URL Anda untuk memverifikasi koneksi dan validasi signature.</p>

        <?php if (empty($merchant['webhook_url'])): ?>
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
            Webhook URL belum dikonfigurasi. <a href="/merchant/webhook-settings.php" class="font-medium underline">Set webhook URL</a> terlebih dahulu.
        </div>
        <?php else: ?>
        <div class="p-3 bg-slate-50 rounded-lg mb-4">
            <p class="text-xs text-slate-500">Target URL:</p>
            <p class="text-sm font-mono text-slate-700 break-all"><?= e($merchant['webhook_url']) ?></p>
        </div>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Test Amount (Rp)</label>
                <input type="number" name="test_amount" value="50000" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Kirim Test Webhook
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($testResult): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center gap-2 mb-4">
            <?php if ($testResult['success']): ?>
            <div class="w-3 h-3 bg-emerald-500 rounded-full"></div>
            <h3 class="text-sm font-semibold text-emerald-700">Berhasil (HTTP <?= $testResult['http_code'] ?>)</h3>
            <?php else: ?>
            <div class="w-3 h-3 bg-red-500 rounded-full"></div>
            <h3 class="text-sm font-semibold text-red-700">Gagal <?= $testResult['http_code'] ? "(HTTP {$testResult['http_code']})" : '' ?></h3>
            <?php endif; ?>
            <span class="text-xs text-slate-400 ml-auto"><?= $testResult['time_ms'] ?>ms</span>
        </div>

        <?php if ($testResult['error']): ?>
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 mb-4">
            Error: <?= e($testResult['error']) ?>
        </div>
        <?php endif; ?>

        <!-- Request -->
        <div class="mb-4">
            <p class="text-xs font-medium text-slate-500 mb-1">Request Payload:</p>
            <pre class="text-xs font-mono bg-slate-900 text-emerald-300 rounded-lg p-4 overflow-x-auto max-h-48"><?= e(json_encode($testResult['payload'], JSON_PRETTY_PRINT)) ?></pre>
        </div>

        <div class="mb-4">
            <p class="text-xs font-medium text-slate-500 mb-1">X-Signature:</p>
            <p class="text-xs font-mono bg-slate-100 rounded p-2 break-all"><?= e($testResult['signature']) ?></p>
        </div>

        <!-- Response -->
        <?php if ($testResult['response']): ?>
        <div>
            <p class="text-xs font-medium text-slate-500 mb-1">Response Body:</p>
            <pre class="text-xs font-mono bg-slate-900 text-blue-300 rounded-lg p-4 overflow-x-auto max-h-32"><?= e($testResult['response']) ?></pre>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
