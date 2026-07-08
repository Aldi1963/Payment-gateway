<?php
/**
 * Integrasi API - Dokumentasi & Testing
 * 
 * Halaman ini hanya untuk:
 * - Dokumentasi endpoint API
 * - Contoh request/response
 * - Test webhook
 * - Webhook signature verification
 * 
 * Konfigurasi API key ada di /merchant/settings.php?tab=apikey
 * Konfigurasi webhook URL & IP whitelist ada di /merchant/project-settings.php
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();
$merchant = $controller->getMerchant();
$merchantId = Auth::merchantId();

$activeTab = $_GET['tab'] ?? 'docs';
$validTabs = ['docs', 'webhook-docs', 'test'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'docs';

// Handle Test Webhook POST
$testResult = null;
if (is_post() && $activeTab === 'test') {
    Auth::verifyCsrf();
    $webhookUrl = $merchant['webhook_url'] ?? '';
    if (empty($webhookUrl)) {
        flash('error', 'Webhook URL belum dikonfigurasi. Atur di Project Settings.');
        redirect('/merchant/integration.php?tab=test');
    }

    // SECURITY: Validate webhook URL is not targeting internal networks (SSRF protection)
    $urlCheck = validate_webhook_url($webhookUrl);
    if (!$urlCheck['safe']) {
        flash('error', 'Webhook URL tidak aman: ' . $urlCheck['reason']);
        redirect('/merchant/integration.php?tab=test');
    }
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
    $jsonPayload = json_encode($testPayload);
    $signature = hash_hmac('sha256', $jsonPayload, $merchant['api_key']);
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
            'User-Agent: ClipkuPay-Webhook-Test/1.0',
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

$pageTitle = 'Integrasi API';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<!-- Info banner: settings are in Project Settings -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <p class="text-sm text-blue-800"><strong>API Key</strong> diatur di <a href="/merchant/settings.php?tab=apikey" class="font-semibold underline hover:text-blue-900">Pengaturan</a>. <strong>Webhook URL</strong> & <strong>IP Whitelist</strong> diatur di <a href="/merchant/project-settings.php?id=<?= e($merchantId) ?>" class="font-semibold underline hover:text-blue-900">Project Settings</a>.</p>
            <p class="text-xs text-blue-600 mt-1">Halaman ini berisi dokumentasi teknis dan testing untuk proyek aktif Anda.</p>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <?php
        $tabs = [
            'docs' => 'Dokumentasi API',
            'webhook-docs' => 'Webhook & Signature',
            'test' => 'Test Webhook',
        ];
        foreach ($tabs as $key => $label): ?>
        <a href="?tab=<?= $key ?>" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $activeTab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>


<?php if ($activeTab === 'docs'): ?>
<!-- ============ TAB: DOKUMENTASI API ============ -->
<div class="max-w-3xl space-y-6">

    <!-- Base Info -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Endpoint API</h3>
        <div class="space-y-3 text-sm">
            <div>
                <span class="text-xs text-slate-500">Base URL:</span>
                <code class="block mt-1 px-3 py-2 bg-slate-100 rounded text-xs font-mono"><?= e(app_url('api/v1/')) ?></code>
            </div>
            <div>
                <span class="text-xs text-slate-500">Authentication (API key akun, 1 untuk semua proyek):</span>
                <code class="block mt-1 px-3 py-2 bg-slate-100 rounded text-xs font-mono">Authorization: Bearer YOUR_ACCOUNT_API_KEY</code>
            </div>
            <div>
                <span class="text-xs text-slate-500">Proyek tujuan (wajib jika akun punya &gt;1 proyek):</span>
                <code class="block mt-1 px-3 py-2 bg-slate-100 rounded text-xs font-mono">X-Project-Id: <?= e($merchantId) ?></code>
                <p class="text-xs text-slate-400 mt-1">Alternatif: <code class="font-mono">X-Project: &lt;slug&gt;</code>. Ambil API key di <a href="/merchant/settings.php?tab=apikey" class="text-blue-600 hover:underline">Pengaturan &rsaquo; API Key</a>.</p>
            </div>
            <div>
                <span class="text-xs text-slate-500">Content-Type:</span>
                <code class="block mt-1 px-3 py-2 bg-slate-100 rounded text-xs font-mono">application/json</code>
            </div>
        </div>
    </div>

    <!-- Create Transaction -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">
            <span class="inline-block px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded text-xs font-mono mr-2">POST</span>
            /api/v1/transactions
        </h4>
        <p class="text-sm text-slate-600 mb-4">Membuat transaksi pembayaran baru.</p>
        
        <div class="mb-4">
            <p class="text-xs font-medium text-slate-500 mb-2">Request Body:</p>
            <pre class="text-xs font-mono bg-slate-900 text-emerald-300 rounded-lg p-4 overflow-x-auto">{
  "order_id": "INV-20250708-001",
  "amount": 150000,
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "customer_wa": "628123456789",
  "note": "Pembelian Paket Premium",
  "expiry_minutes": 60
}</pre>
        </div>

        <div>
            <p class="text-xs font-medium text-slate-500 mb-2">Response (201 Created):</p>
            <pre class="text-xs font-mono bg-slate-900 text-blue-300 rounded-lg p-4 overflow-x-auto">{
  "success": true,
  "data": {
    "id": "a1b87ce9-c215-4458-b3c2-a650e20011db",
    "order_id": "INV-20250708-001",
    "amount": 150000,
    "fee": 1050,
    "net_amount": 148950,
    "status": "pending",
    "payment_url": "https://pay.clipku.com/pay/a1b87ce9...",
    "qr_url": "https://pay.clipku.com/qr/a1b87ce9...",
    "expires_at": "2025-07-08T11:00:00+07:00"
  }
}</pre>
        </div>
    </div>

    <!-- Check Status -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">
            <span class="inline-block px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-xs font-mono mr-2">GET</span>
            /api/v1/transactions/{id}
        </h4>
        <p class="text-sm text-slate-600 mb-4">Cek status transaksi.</p>
        
        <div>
            <p class="text-xs font-medium text-slate-500 mb-2">Response:</p>
            <pre class="text-xs font-mono bg-slate-900 text-blue-300 rounded-lg p-4 overflow-x-auto">{
  "success": true,
  "data": {
    "id": "a1b87ce9-...",
    "order_id": "INV-20250708-001",
    "amount": 150000,
    "status": "settlement",
    "paid_at": "2025-07-08T10:05:23+07:00"
  }
}</pre>
        </div>
    </div>


    <!-- cURL Example -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">Contoh cURL</h4>
        <div class="relative">
            <pre id="curlExample" class="text-xs font-mono bg-slate-900 text-emerald-300 rounded-lg p-4 overflow-x-auto">curl -X POST <?= e(app_url('api/v1/transactions')) ?> \
  -H "Authorization: Bearer YOUR_ACCOUNT_API_KEY" \
  -H "X-Project-Id: <?= e($merchantId) ?>" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "INV-001",
    "amount": 50000,
    "customer_name": "Customer",
    "customer_wa": "628123456789"
  }'</pre>
            <button onclick="copyCode('curlExample')" class="absolute top-2 right-2 px-2 py-1 bg-slate-700 text-slate-300 rounded text-xs hover:bg-slate-600">Copy</button>
        </div>
    </div>

    <!-- Status Codes -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">Status Transaksi</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-200"><th class="py-2 text-left text-xs text-slate-500">Status</th><th class="py-2 text-left text-xs text-slate-500">Keterangan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="py-2"><code class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded">pending</code></td><td class="py-2 text-sm text-slate-600">Menunggu pembayaran</td></tr>
                    <tr><td class="py-2"><code class="text-xs px-1.5 py-0.5 bg-emerald-100 text-emerald-800 rounded">settlement</code></td><td class="py-2 text-sm text-slate-600">Pembayaran berhasil</td></tr>
                    <tr><td class="py-2"><code class="text-xs px-1.5 py-0.5 bg-red-100 text-red-800 rounded">expired</code></td><td class="py-2 text-sm text-slate-600">Pembayaran kedaluwarsa</td></tr>
                    <tr><td class="py-2"><code class="text-xs px-1.5 py-0.5 bg-slate-100 text-slate-800 rounded">canceled</code></td><td class="py-2 text-sm text-slate-600">Dibatalkan</td></tr>
                    <tr><td class="py-2"><code class="text-xs px-1.5 py-0.5 bg-purple-100 text-purple-800 rounded">refunded</code></td><td class="py-2 text-sm text-slate-600">Dana dikembalikan</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Error Codes -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">HTTP Status Codes</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-200"><th class="py-2 text-left text-xs text-slate-500">Code</th><th class="py-2 text-left text-xs text-slate-500">Keterangan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="py-2 font-mono text-xs">200</td><td class="py-2 text-sm text-slate-600">OK</td></tr>
                    <tr><td class="py-2 font-mono text-xs">201</td><td class="py-2 text-sm text-slate-600">Created - Transaksi berhasil dibuat</td></tr>
                    <tr><td class="py-2 font-mono text-xs">400</td><td class="py-2 text-sm text-slate-600">Bad Request - Parameter tidak valid</td></tr>
                    <tr><td class="py-2 font-mono text-xs">401</td><td class="py-2 text-sm text-slate-600">Unauthorized - API key salah/tidak ada</td></tr>
                    <tr><td class="py-2 font-mono text-xs">403</td><td class="py-2 text-sm text-slate-600">Forbidden - IP tidak di-whitelist</td></tr>
                    <tr><td class="py-2 font-mono text-xs">404</td><td class="py-2 text-sm text-slate-600">Not Found - Resource tidak ditemukan</td></tr>
                    <tr><td class="py-2 font-mono text-xs">429</td><td class="py-2 text-sm text-slate-600">Too Many Requests - Rate limit terlampaui</td></tr>
                    <tr><td class="py-2 font-mono text-xs">500</td><td class="py-2 text-sm text-slate-600">Internal Server Error</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php elseif ($activeTab === 'webhook-docs'): ?>
<!-- ============ TAB: WEBHOOK & SIGNATURE ============ -->
<div class="max-w-3xl space-y-6">

    <!-- How Webhooks Work -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Cara Kerja Webhook</h3>
        <div class="text-sm text-slate-600 space-y-3">
            <p>Webhook adalah notifikasi HTTP POST yang dikirim ke server Anda setiap kali terjadi event pembayaran. Sistem kami akan mengirimkan payload JSON ke Webhook URL yang telah Anda konfigurasi di <a href="/merchant/project-settings.php?id=<?= e($merchantId) ?>" class="text-blue-600 font-medium hover:underline">Project Settings</a>.</p>
            <ol class="list-decimal list-inside space-y-2 ml-2">
                <li>Customer melakukan pembayaran</li>
                <li>Sistem memverifikasi pembayaran</li>
                <li>Webhook dikirim ke URL Anda dengan payload JSON</li>
                <li>Server Anda memvalidasi signature dan memproses data</li>
                <li>Return HTTP 2xx untuk konfirmasi penerimaan</li>
            </ol>
            <p class="text-xs text-slate-500 mt-2">Jika server Anda gagal merespons (timeout / non-2xx), webhook akan di-retry hingga 5x dengan backoff eksponensial.</p>
        </div>
    </div>

    <!-- Events -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">Event yang Dikirim</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="border-b border-slate-200"><th class="py-2 text-left text-xs text-slate-500">Event</th><th class="py-2 text-left text-xs text-slate-500">Keterangan</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <tr><td class="py-2"><code class="text-xs px-1.5 py-0.5 bg-slate-100 rounded">payment.settlement</code></td><td class="py-2 text-slate-600">Pembayaran berhasil / lunas</td></tr>
                    <tr><td class="py-2"><code class="text-xs px-1.5 py-0.5 bg-slate-100 rounded">payment.expired</code></td><td class="py-2 text-slate-600">Pembayaran kedaluwarsa</td></tr>
                    <tr><td class="py-2"><code class="text-xs px-1.5 py-0.5 bg-slate-100 rounded">payment.refunded</code></td><td class="py-2 text-slate-600">Dana dikembalikan</td></tr>
                    <tr><td class="py-2"><code class="text-xs px-1.5 py-0.5 bg-slate-100 rounded">payment.test</code></td><td class="py-2 text-slate-600">Test webhook (dari halaman ini)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payload Example -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">Contoh Payload</h4>
        <pre class="text-xs font-mono bg-slate-900 text-emerald-300 rounded-lg p-4 overflow-x-auto">{
  "event": "payment.settlement",
  "transaction_id": "a1b87ce9-c215-4458-b3c2-a650e20011db",
  "order_id": "INV-20250708-001",
  "status": "settlement",
  "amount": 150000,
  "fee": 1050,
  "net_amount": 148950,
  "paid_at": "2025-07-08T10:05:23+07:00",
  "timestamp": "2025-07-08T10:05:24+07:00"
}</pre>
    </div>


    <!-- Signature Verification -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">Validasi Signature</h4>
        <p class="text-sm text-slate-600 mb-4">Setiap webhook membawa header <code class="text-xs px-1.5 py-0.5 bg-slate-100 rounded">X-Signature</code> berisi HMAC-SHA256 dari body menggunakan <strong>Webhook Signing Secret</strong> proyek sebagai secret (bukan API key akun). Ambil di <a href="/merchant/project-settings.php?id=<?= e($merchantId) ?>" class="text-blue-600 hover:underline">Project Settings</a>.</p>

        <div class="mb-4">
            <p class="text-xs font-medium text-slate-500 mb-2">Headers yang dikirim:</p>
            <pre class="text-xs font-mono bg-slate-100 rounded-lg p-3 overflow-x-auto">Content-Type: application/json
X-Signature: &lt;hmac_sha256_hex&gt;
User-Agent: ClipkuPay-Webhook/1.0</pre>
        </div>

        <div class="mb-4">
            <p class="text-xs font-medium text-slate-500 mb-2">PHP - Verifikasi Signature:</p>
            <pre class="text-xs font-mono bg-slate-900 text-emerald-300 rounded-lg p-4 overflow-x-auto">// 1. Ambil raw body dan signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

// 2. Hitung HMAC dengan Webhook Signing Secret proyek (dari Project Settings)
$calculated = hash_hmac('sha256', $payload, $webhookSigningSecret);

// 3. Bandingkan (timing-safe)
if (!hash_equals($calculated, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

// 4. Proses webhook
$data = json_decode($payload, true);
// ... update status order di database Anda</pre>
        </div>

        <div class="mb-4">
            <p class="text-xs font-medium text-slate-500 mb-2">Node.js - Verifikasi Signature:</p>
            <pre class="text-xs font-mono bg-slate-900 text-emerald-300 rounded-lg p-4 overflow-x-auto">const crypto = require('crypto');

app.post('/webhook', (req, res) => {
  const payload = JSON.stringify(req.body);
  const signature = req.headers['x-signature'];
  const calculated = crypto
    .createHmac('sha256', WEBHOOK_SIGNING_SECRET)
    .update(payload)
    .digest('hex');

  if (calculated !== signature) {
    return res.status(401).send('Invalid signature');
  }

  // Process webhook...
  res.status(200).send('OK');
});</pre>
        </div>

        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-xs text-amber-800"><strong>Penting:</strong> Selalu validasi signature sebelum memproses webhook. Jangan pernah mempercayai data tanpa verifikasi.</p>
        </div>
    </div>

    <!-- Retry Policy -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">Retry Policy</h4>
        <div class="text-sm text-slate-600 space-y-2">
            <p>Jika webhook gagal (timeout atau non-2xx response), sistem akan melakukan retry:</p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li>Retry ke-1: setelah 1 menit</li>
                <li>Retry ke-2: setelah 5 menit</li>
                <li>Retry ke-3: setelah 30 menit</li>
                <li>Retry ke-4: setelah 2 jam</li>
                <li>Retry ke-5: setelah 12 jam</li>
            </ul>
            <p class="mt-2">Setelah 5 kali gagal, webhook ditandai <code class="text-xs px-1.5 py-0.5 bg-red-100 rounded">failed</code>. Anda bisa manual retry dari <a href="/merchant/webhook-logs.php" class="text-blue-600 font-medium hover:underline">Webhook Logs</a>.</p>
        </div>
    </div>
</div>


<?php elseif ($activeTab === 'test'): ?>
<!-- ============ TAB: TEST WEBHOOK ============ -->
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-2">Test Webhook</h3>
        <p class="text-sm text-slate-500 mb-4">Kirim test payload ke webhook URL proyek aktif untuk verifikasi koneksi dan signature.</p>

        <?php if (empty($merchant['webhook_url'])): ?>
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
            Webhook URL belum dikonfigurasi. <a href="/merchant/project-settings.php?id=<?= e($merchantId) ?>" class="font-medium underline">Set webhook URL di Project Settings</a> terlebih dahulu.
        </div>
        <?php else: ?>
        <div class="p-3 bg-slate-50 rounded-lg mb-4">
            <p class="text-xs text-slate-500">Target URL (dari Project Settings):</p>
            <p class="text-sm font-mono text-slate-700 break-all"><?= e($merchant['webhook_url']) ?></p>
        </div>
        <form method="POST" action="?tab=test" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Test Amount (Rp)</label>
                <input type="number" name="test_amount" value="50000" min="1000" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Kirim Test Webhook</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!empty($testResult)): ?>
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
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 mb-4">Error: <?= e($testResult['error']) ?></div>
        <?php endif; ?>
        <div class="mb-4">
            <p class="text-xs font-medium text-slate-500 mb-1">Payload yang dikirim:</p>
            <pre class="text-xs font-mono bg-slate-900 text-emerald-300 rounded-lg p-4 overflow-x-auto max-h-48"><?= e(json_encode($testResult['payload'], JSON_PRETTY_PRINT)) ?></pre>
        </div>
        <div class="mb-4">
            <p class="text-xs font-medium text-slate-500 mb-1">X-Signature:</p>
            <p class="text-xs font-mono bg-slate-100 rounded p-2 break-all"><?= e($testResult['signature']) ?></p>
        </div>
        <?php if ($testResult['response']): ?>
        <div>
            <p class="text-xs font-medium text-slate-500 mb-1">Response dari server Anda:</p>
            <pre class="text-xs font-mono bg-slate-900 text-blue-300 rounded-lg p-4 overflow-x-auto max-h-32"><?= e($testResult['response']) ?></pre>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<script>
function copyCode(id) {
    const el = document.getElementById(id);
    navigator.clipboard.writeText(el.textContent).then(() => showToast('Copied!'));
}
function copyToClipboard(id) {
    navigator.clipboard.writeText(document.getElementById(id).value).then(() => showToast('Copied!'));
}
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
