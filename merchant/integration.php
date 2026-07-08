<?php
/**
 * Integrasi API - Consolidated Page
 * Tabs: API Key, Webhook, Test Webhook, Riwayat Perubahan
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
require_once base_path('app/Services/ConfigChangeService.php');

$controller = new MerchantController();
$configService = new ConfigChangeService();
$merchant = $controller->getMerchant();
$merchantId = Auth::merchantId();

$activeTab = $_GET['tab'] ?? 'apikey';
$validTabs = ['apikey', 'webhook', 'test', 'history'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'apikey';

// Handle POST actions
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? $_POST['action'] ?? '';

    if ($activeTab === 'apikey' && $action === 'regenerate') {
        $password = $_POST['confirm_password'] ?? '';
        if (!$configService->verifyPassword(Auth::id(), $password)) {
            flash('error', 'Password tidak valid. Verifikasi gagal.');
            redirect('/merchant/integration.php?tab=apikey');
        }
        $requireApproval = setting('require_approval_api_key', '1') === '1';
        if ($requireApproval) {
            $result = $configService->requestChange([
                'merchant_id' => $merchantId,
                'change_type' => 'api_key_regenerate',
                'old_value' => mask_api_key($merchant['api_key']),
                'new_value' => '(akan di-generate otomatis setelah approved)',
                'reason' => sanitize($_POST['reason'] ?? 'Regenerate API Key'),
                'requested_by' => Auth::id(),
                'requested_by_role' => Auth::role(),
            ]);
        } else {
            $result = $controller->regenerateApiKey();
        }
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('/merchant/integration.php?tab=apikey');
    }

    if ($activeTab === 'webhook' && $action === 'request_change') {
        $password = $_POST['confirm_password'] ?? '';
        if (!$configService->verifyPassword(Auth::id(), $password)) {
            flash('error', 'Password tidak valid. Verifikasi gagal.');
            redirect('/merchant/integration.php?tab=webhook');
        }
        $changeType = $_POST['change_type'] ?? '';
        $oldValueMap = ['webhook_url' => $merchant['webhook_url'] ?? '', 'redirect_url' => $merchant['redirect_url'] ?? ''];
        $result = $configService->requestChange([
            'merchant_id' => $merchantId,
            'change_type' => $changeType,
            'old_value' => $oldValueMap[$changeType] ?? '',
            'new_value' => sanitize($_POST['new_value'] ?? ''),
            'reason' => sanitize($_POST['reason'] ?? ''),
            'requested_by' => Auth::id(),
            'requested_by_role' => Auth::role(),
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('/merchant/integration.php?tab=webhook');
    }

    if ($action === 'cancel_change' || $action === 'cancel') {
        $changeId = $_POST['change_id'] ?? '';
        $result = $configService->cancel($changeId, $merchantId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('/merchant/integration.php?tab=' . $activeTab);
    }

    // Test webhook POST
    if ($activeTab === 'test') {
        $webhookUrl = $merchant['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            flash('error', 'Webhook URL belum dikonfigurasi.');
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
            'url' => $webhookUrl, 'payload' => $testPayload, 'signature' => $signature,
            'http_code' => $httpCode, 'response' => $response, 'error' => $error,
            'time_ms' => (int)($totalTime * 1000), 'success' => ($httpCode >= 200 && $httpCode < 300),
        ];
    }
}

// Load data for tabs
$pendingChanges = $configService->getPendingByMerchant($merchantId);
$hasPendingKey = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'api_key_regenerate'));
$pendingWebhook = array_filter($pendingChanges, fn($c) => $c['change_type'] === 'webhook_url');
$pendingRedirect = array_filter($pendingChanges, fn($c) => $c['change_type'] === 'redirect_url');
$hasPendingWebhook = !empty($pendingWebhook);
$hasPendingRedirect = !empty($pendingRedirect);

$historyPagination = null;
if ($activeTab === 'history') {
    $allChanges = $configService->getHistoryByMerchant($merchantId);
    $historyPagination = paginate($allChanges, (int)($_GET['page'] ?? 1), 15);
}

$pageTitle = 'Integrasi API';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<!-- Tab Navigation -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <?php
        $tabs = ['apikey' => 'API Key', 'webhook' => 'Webhook', 'test' => 'Test Webhook', 'history' => 'Riwayat Perubahan'];
        foreach ($tabs as $key => $label): ?>
        <a href="?tab=<?= $key ?>" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $activeTab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>


<?php if ($activeTab === 'apikey'): ?>
<!-- ============ TAB: API KEY ============ -->
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">API Key Anda</h3>
        <p class="text-sm text-slate-500 mb-4">Gunakan API key ini untuk autentikasi pada semua request API. <strong>Jangan bagikan ke pihak lain.</strong></p>
        
        <div class="flex items-center gap-2 mb-4">
            <input type="text" id="apiKeyField" value="<?= e($merchant['api_key'] ?? '') ?>" readonly class="flex-1 px-4 py-3 bg-slate-900 text-emerald-400 font-mono text-sm rounded-lg border-0">
            <button onclick="copyToClipboard('apiKeyField')" class="px-4 py-3 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 whitespace-nowrap">Copy</button>
        </div>

        <div class="pt-4 border-t border-slate-200">
            <p class="text-xs text-slate-400 mb-3">Masked: <?= mask_api_key($merchant['api_key'] ?? '') ?></p>
            
            <?php if ($hasPendingKey): ?>
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <p class="text-xs text-amber-700 font-medium">Regenerasi API key menunggu persetujuan admin.</p>
            </div>
            <?php else: ?>
            <form method="POST" action="?tab=apikey" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="regenerate">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Alasan <span class="text-slate-400">(opsional)</span></label>
                    <input type="text" name="reason" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" placeholder="Compromised, rotasi rutin, dll.">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                    <input type="password" name="confirm_password" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" placeholder="Masukkan password Anda">
                </div>
                <button type="submit" onclick="return confirm('API key lama tidak berlaku setelah disetujui. Lanjutkan?')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 border border-red-200">
                    Ajukan Regenerate API Key
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick API Reference -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-3">Quick Reference</h3>
        <div class="space-y-3 text-sm">
            <div><span class="text-xs text-slate-500">Base URL:</span><code class="block mt-1 px-3 py-2 bg-slate-100 rounded text-xs font-mono"><?= e(app_url('api/index.php')) ?></code></div>
            <div><span class="text-xs text-slate-500">Header:</span><code class="block mt-1 px-3 py-2 bg-slate-100 rounded text-xs font-mono">Authorization: Bearer YOUR_API_KEY</code></div>
        </div>
        <a href="/docs.php" target="_blank" class="inline-block mt-4 text-xs text-blue-600 font-medium hover:text-blue-700">Lihat Dokumentasi Lengkap &rarr;</a>
    </div>
</div>

<?php elseif ($activeTab === 'webhook'): ?>
<!-- ============ TAB: WEBHOOK ============ -->
<div class="max-w-2xl space-y-6">
    <!-- Current Config -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Konfigurasi Aktif</h3>
        <div class="space-y-4">
            <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-slate-700">Webhook URL</label>
                    <?php if ($hasPendingWebhook): ?><span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded">Pending</span><?php endif; ?>
                </div>
                <p class="text-sm font-mono text-slate-600 break-all"><?= e($merchant['webhook_url'] ?: '(belum diatur)') ?></p>
            </div>
            <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-slate-700">Redirect URL</label>
                    <?php if ($hasPendingRedirect): ?><span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded">Pending</span><?php endif; ?>
                </div>
                <p class="text-sm font-mono text-slate-600 break-all"><?= e($merchant['redirect_url'] ?: '(belum diatur)') ?></p>
            </div>
        </div>
    </div>

    <!-- Change Forms -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Ajukan Perubahan</h3>
        <?php if (!$hasPendingWebhook): ?>
        <form method="POST" action="?tab=webhook" class="mb-6 pb-6 border-b border-slate-200">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="request_change">
            <input type="hidden" name="change_type" value="webhook_url">
            <div class="mb-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Webhook URL Baru</label>
                <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/webhook">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Alasan</label>
                <input type="text" name="reason" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Migrasi server, update endpoint">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" onclick="return confirm('Ajukan perubahan webhook URL?')" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Ajukan Perubahan</button>
        </form>
        <?php endif; ?>
        <?php if (!$hasPendingRedirect): ?>
        <form method="POST" action="?tab=webhook">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="request_change">
            <input type="hidden" name="change_type" value="redirect_url">
            <div class="mb-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Redirect URL Baru</label>
                <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/success">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Alasan</label>
                <input type="text" name="reason" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" onclick="return confirm('Ajukan perubahan redirect URL?')" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Ajukan Perubahan</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Signature Info -->
    <div class="bg-slate-900 rounded-xl p-6">
        <h4 class="text-sm font-semibold text-slate-300 mb-3">Validasi Signature</h4>
        <p class="text-xs text-slate-400 mb-3">Header <code class="text-emerald-400">X-Signature</code> = HMAC-SHA256(body, API_KEY)</p>
        <pre class="text-xs text-emerald-300 font-mono bg-slate-800 rounded-lg p-4 overflow-x-auto">$calculated = hash_hmac('sha256', $payload, $apiKey);
if (hash_equals($calculated, $signature)) {
    // Valid webhook
}</pre>
    </div>
</div>


<?php elseif ($activeTab === 'test'): ?>
<!-- ============ TAB: TEST WEBHOOK ============ -->
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-2">Test Webhook</h3>
        <p class="text-sm text-slate-500 mb-4">Kirim test payload ke webhook URL untuk verifikasi koneksi dan signature.</p>

        <?php if (empty($merchant['webhook_url'])): ?>
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
            Webhook URL belum dikonfigurasi. <a href="?tab=webhook" class="font-medium underline">Set webhook URL</a> terlebih dahulu.
        </div>
        <?php else: ?>
        <div class="p-3 bg-slate-50 rounded-lg mb-4">
            <p class="text-xs text-slate-500">Target URL:</p>
            <p class="text-sm font-mono text-slate-700 break-all"><?= e($merchant['webhook_url']) ?></p>
        </div>
        <form method="POST" action="?tab=test" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Test Amount (Rp)</label>
                <input type="number" name="test_amount" value="50000" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
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
            <p class="text-xs font-medium text-slate-500 mb-1">Payload:</p>
            <pre class="text-xs font-mono bg-slate-900 text-emerald-300 rounded-lg p-4 overflow-x-auto max-h-48"><?= e(json_encode($testResult['payload'], JSON_PRETTY_PRINT)) ?></pre>
        </div>
        <div class="mb-4">
            <p class="text-xs font-medium text-slate-500 mb-1">X-Signature:</p>
            <p class="text-xs font-mono bg-slate-100 rounded p-2 break-all"><?= e($testResult['signature']) ?></p>
        </div>
        <?php if ($testResult['response']): ?>
        <div>
            <p class="text-xs font-medium text-slate-500 mb-1">Response:</p>
            <pre class="text-xs font-mono bg-slate-900 text-blue-300 rounded-lg p-4 overflow-x-auto max-h-32"><?= e($testResult['response']) ?></pre>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($activeTab === 'history'): ?>
<!-- ============ TAB: RIWAYAT PERUBAHAN ============ -->
<div class="max-w-4xl">
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <?php if (empty($historyPagination['data'])): ?>
        <div class="p-12 text-center text-slate-400"><p class="text-sm">Belum ada riwayat perubahan konfigurasi.</p></div>
        <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($historyPagination['data'] as $change): ?>
            <div class="p-4 hover:bg-slate-50">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-medium text-slate-800"><?= e($change['change_label'] ?? $change['change_type']) ?></span>
                            <?php
                            $statusClass = match($change['status']) {
                                'pending' => 'bg-amber-100 text-amber-800',
                                'approved' => 'bg-emerald-100 text-emerald-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                'canceled' => 'bg-slate-100 text-slate-600',
                                default => 'bg-slate-100 text-slate-600',
                            };
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>"><?= ucfirst(str_replace('_', ' ', $change['status'])) ?></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2 text-xs">
                            <div><span class="text-slate-400">Lama:</span><p class="font-mono text-slate-600 truncate"><?= e($change['old_value'] ?: '(kosong)') ?></p></div>
                            <div><span class="text-slate-400">Baru:</span><p class="font-mono text-slate-800 truncate"><?= e($change['new_value'] ?: '(kosong)') ?></p></div>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Diajukan: <?= format_date($change['created_at']) ?></p>
                    </div>
                    <?php if ($change['status'] === 'pending'): ?>
                    <form method="POST" action="?tab=history" onsubmit="return confirm('Batalkan perubahan ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="cancel">
                        <input type="hidden" name="change_id" value="<?= e($change['id']) ?>">
                        <button class="px-3 py-1.5 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50">Batalkan</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($historyPagination['total_pages'] > 1): ?>
        <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between">
            <p class="text-xs text-slate-500">Halaman <?= $historyPagination['current_page'] ?> dari <?= $historyPagination['total_pages'] ?></p>
            <div class="flex gap-1">
                <?php if ($historyPagination['has_prev']): ?><a href="?tab=history&page=<?= $historyPagination['current_page']-1 ?>" class="px-3 py-1 rounded text-xs hover:bg-slate-100">&laquo;</a><?php endif; ?>
                <?php if ($historyPagination['has_next']): ?><a href="?tab=history&page=<?= $historyPagination['current_page']+1 ?>" class="px-3 py-1 rounded text-xs hover:bg-slate-100">&raquo;</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<script>
function copyToClipboard(id) { navigator.clipboard.writeText(document.getElementById(id).value).then(()=>showToast('Copied!')); }
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
