<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
require_once base_path('app/Services/ConfigChangeService.php');

$controller = new MerchantController();
$configService = new ConfigChangeService();
$merchant = $controller->getMerchant();
$merchantId = Auth::merchantId();

if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'regenerate') {
        // Verify password
        $password = $_POST['confirm_password'] ?? '';
        if (!$configService->verifyPassword(Auth::id(), $password)) {
            flash('error', 'Password tidak valid. Verifikasi gagal.');
            redirect('/merchant/api-keys.php');
        }

        // Check if admin approval is required for key regeneration
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
            flash($result['success'] ? 'success' : 'error', $result['message']);
        } else {
            // Direct regeneration without approval
            $result = $controller->regenerateApiKey();
            flash($result['success'] ? 'success' : 'error', $result['message']);
        }
        redirect('/merchant/api-keys.php');
    }
}

$apiKey = $merchant['api_key'] ?? '';
$pendingChanges = $configService->getPendingByMerchant($merchantId);
$hasPendingKey = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'api_key_regenerate'));

$pageTitle = 'API Keys';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="max-w-2xl space-y-6">
    <!-- API Key Card -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">API Key Anda</h3>
        <p class="text-sm text-slate-500 mb-4">Gunakan API key ini untuk autentikasi pada semua request API. <strong>Jangan bagikan ke pihak lain.</strong></p>
        
        <div class="flex items-center gap-2 mb-4">
            <input type="text" id="apiKeyField" value="<?= e($apiKey) ?>" readonly class="flex-1 px-4 py-3 bg-slate-900 text-emerald-400 font-mono text-sm rounded-lg border-0">
            <button onclick="copyToClipboard('apiKeyField')" class="px-4 py-3 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 whitespace-nowrap">
                Copy
            </button>
        </div>

        <div class="pt-4 border-t border-slate-200">
            <p class="text-xs text-slate-400 mb-3">Masked: <?= mask_api_key($apiKey) ?></p>
            
            <?php if ($hasPendingKey): ?>
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <p class="text-xs text-amber-700 font-medium">Regenerasi API key sedang menunggu persetujuan admin.</p>
                <p class="text-xs text-amber-600 mt-1">API key saat ini tetap berlaku hingga admin menyetujui.</p>
            </div>
            <?php else: ?>
            <form method="POST" id="regenForm" class="space-y-3">
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
                <button type="submit" onclick="return confirm('PERHATIAN: Setelah disetujui admin, API key lama akan tidak berlaku. Lanjutkan?')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 border border-red-200">
                    Ajukan Regenerate API Key
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- IP Whitelist -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-2">IP Whitelist</h3>
        <p class="text-xs text-slate-500 mb-4">Batasi akses API hanya dari IP tertentu. Kosongkan untuk mengizinkan semua IP.</p>
        
        <?php
        $currentIps = $merchant['ip_whitelist'] ?? '';
        $ipList = is_array($currentIps) ? $currentIps : array_filter(array_map('trim', explode("\n", $currentIps)));
        $pendingIpChange = array_filter($pendingChanges, fn($c) => str_starts_with($c['change_type'], 'ip_whitelist'));
        $hasPendingIp = !empty($pendingIpChange);
        ?>

        <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg mb-4">
            <p class="text-xs font-medium text-slate-600 mb-1">IP Aktif Saat Ini:</p>
            <?php if (empty($ipList)): ?>
            <p class="text-xs text-slate-400 italic">Semua IP diizinkan (tidak ada whitelist)</p>
            <?php else: ?>
            <div class="flex flex-wrap gap-1">
                <?php foreach ($ipList as $ip): ?>
                <span class="inline-flex items-center px-2 py-0.5 bg-blue-50 text-blue-700 text-xs font-mono rounded"><?= e($ip) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($hasPendingIp): ?>
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-xs text-amber-700 font-medium">Perubahan IP whitelist menunggu verifikasi admin.</p>
        </div>
        <?php else: ?>
        <form method="POST" class="space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="request_change">
            <input type="hidden" name="change_type" value="ip_whitelist_change">
            <div>
                <label class="block text-xs text-slate-500 mb-1">IP Whitelist Baru (satu per baris)</label>
                <textarea name="new_value" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono" placeholder="1.2.3.4&#10;5.6.7.8"><?= e(implode("\n", $ipList)) ?></textarea>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Alasan</label>
                <input type="text" name="reason" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" placeholder="Tambah server baru, dll.">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" placeholder="Password Anda">
            </div>
            <button type="submit" onclick="return confirm('Ajukan perubahan IP whitelist?')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700">Ajukan Perubahan IP</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- API Documentation -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">API Documentation</h3>
        <div class="space-y-4">
            <div>
                <h4 class="text-sm font-medium text-slate-700 mb-1">Base URL</h4>
                <code class="block px-4 py-2 bg-slate-100 rounded text-sm font-mono"><?= e(app_url('api/index.php')) ?></code>
            </div>
            <div>
                <h4 class="text-sm font-medium text-slate-700 mb-1">Authentication Header</h4>
                <code class="block px-4 py-2 bg-slate-100 rounded text-sm font-mono">Authorization: Bearer YOUR_API_KEY</code>
            </div>
            <div>
                <h4 class="text-sm font-medium text-slate-700 mb-2">Endpoints</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-xs font-mono font-bold">POST</span>
                        <code>?action=create_transaction</code>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-mono font-bold">GET</span>
                        <code>?action=get_transaction&order_id=XXX</code>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-mono font-bold">GET</span>
                        <code>?action=wallet</code>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-mono font-bold">GET</span>
                        <code>?action=withdrawals</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(id) { navigator.clipboard.writeText(document.getElementById(id).value).then(()=>showToast('API Key copied!')); }
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
