<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();

if (is_post() && ($_POST['action'] ?? '') === 'regenerate') {
    Auth::verifyCsrf();
    $result = $controller->regenerateApiKey();
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect('/merchant/api-keys.php');
}

$merchant = $controller->getMerchant();
$apiKey = $merchant['api_key'] ?? '';

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

        <div class="flex items-center justify-between pt-4 border-t border-slate-200">
            <p class="text-xs text-slate-400">Masked: <?= mask_api_key($apiKey) ?></p>
            <form method="POST" onsubmit="return confirm('PERHATIAN: API key lama akan tidak berlaku. Lanjutkan?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="regenerate">
                <button type="submit" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100">Regenerate Key</button>
            </form>
        </div>
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
                        <code>/api/index.php?action=create_transaction</code>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-mono font-bold">GET</span>
                        <code>/api/index.php?action=get_transaction&order_id=XXX</code>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-mono font-bold">GET</span>
                        <code>/api/index.php?action=wallet</code>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-mono font-bold">GET</span>
                        <code>/api/index.php?action=withdrawals</code>
                    </div>
                </div>
            </div>

            <div>
                <h4 class="text-sm font-medium text-slate-700 mb-1">Contoh Request</h4>
                <pre class="px-4 py-3 bg-slate-900 text-emerald-300 rounded-lg text-xs font-mono overflow-x-auto">curl -X POST <?= e(app_url('api/index.php?action=create_transaction')) ?> \
  -H "Authorization: Bearer <?= e(mask_api_key($apiKey)) ?>" \
  -H "Content-Type: application/json" \
  -d '{"order_id":"INV-001","amount":50000}'</pre>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(id) { navigator.clipboard.writeText(document.getElementById(id).value).then(()=>showToast('API Key copied!')); }
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
