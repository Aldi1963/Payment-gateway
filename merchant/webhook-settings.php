<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
require_once base_path('app/Services/ConfigChangeService.php');

$controller = new MerchantController();
$configService = new ConfigChangeService();
$merchant = $controller->getMerchant();
$merchantId = Auth::merchantId();

// Handle POST - config change requests
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? 'request_change';

    // Verify password for all sensitive changes
    $password = $_POST['confirm_password'] ?? '';
    if (!$configService->verifyPassword(Auth::id(), $password)) {
        flash('error', 'Password tidak valid. Verifikasi gagal.');
        redirect('/merchant/webhook-settings.php');
    }

    if ($action === 'request_change') {
        $changeType = $_POST['change_type'] ?? '';
        $newValue = sanitize($_POST['new_value'] ?? '');

        $oldValueMap = [
            'webhook_url' => $merchant['webhook_url'] ?? '',
            'redirect_url' => $merchant['redirect_url'] ?? '',
        ];
        $oldValue = $oldValueMap[$changeType] ?? '';

        $result = $configService->requestChange([
            'merchant_id' => $merchantId,
            'change_type' => $changeType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => sanitize($_POST['reason'] ?? ''),
            'requested_by' => Auth::id(),
            'requested_by_role' => Auth::role(),
        ]);

        flash($result['success'] ? 'success' : 'error', $result['message']);

    } elseif ($action === 'cancel_change') {
        $changeId = $_POST['change_id'] ?? '';
        $result = $configService->cancel($changeId, $merchantId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
    }

    redirect('/merchant/webhook-settings.php');
}

// Get pending changes for this merchant
$pendingChanges = $configService->getPendingByMerchant($merchantId);
$pendingWebhook = array_filter($pendingChanges, fn($c) => $c['change_type'] === 'webhook_url');
$pendingRedirect = array_filter($pendingChanges, fn($c) => $c['change_type'] === 'redirect_url');
$hasPendingWebhook = !empty($pendingWebhook);
$hasPendingRedirect = !empty($pendingRedirect);

$pageTitle = 'Webhook & URL Settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="max-w-2xl space-y-6">

    <!-- Security Notice -->
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            <div>
                <p class="text-sm font-medium text-amber-800">Perubahan Memerlukan Verifikasi</p>
                <p class="text-xs text-amber-600 mt-0.5">Perubahan webhook URL dan redirect URL memerlukan persetujuan admin sebelum aktif. Konfigurasi lama tetap digunakan selama proses verifikasi.</p>
            </div>
        </div>
    </div>

    <!-- Current Active Configuration -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-slate-800">Konfigurasi Aktif</h3>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1.5"></span> Active
            </span>
        </div>

        <div class="space-y-4">
            <!-- Webhook URL -->
            <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-slate-700">Webhook URL</label>
                    <?php if ($hasPendingWebhook): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">Pending Change</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm font-mono text-slate-600 break-all"><?= e($merchant['webhook_url'] ?: '(belum diatur)') ?></p>
                <?php if ($hasPendingWebhook):
                    $pw = array_values($pendingWebhook)[0]; ?>
                <div class="mt-2 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                    <p>Menunggu verifikasi: <strong class="font-mono break-all"><?= e($pw['new_value']) ?></strong></p>
                    <p class="text-amber-500 mt-1">Diajukan: <?= format_date($pw['created_at']) ?></p>
                    <form method="POST" class="mt-2 inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="cancel_change">
                        <input type="hidden" name="change_id" value="<?= e($pw['id']) ?>">
                        <input type="hidden" name="confirm_password" value="skip_for_cancel">
                        <button onclick="return confirm('Batalkan perubahan ini?')" class="text-red-600 hover:text-red-700 text-xs font-medium">Batalkan</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Redirect URL -->
            <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-slate-700">Redirect URL (setelah bayar)</label>
                    <?php if ($hasPendingRedirect): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">Pending Change</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm font-mono text-slate-600 break-all"><?= e($merchant['redirect_url'] ?: '(belum diatur)') ?></p>
                <?php if ($hasPendingRedirect):
                    $pr = array_values($pendingRedirect)[0]; ?>
                <div class="mt-2 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                    <p>Menunggu verifikasi: <strong class="font-mono break-all"><?= e($pr['new_value']) ?></strong></p>
                    <p class="text-amber-500 mt-1">Diajukan: <?= format_date($pr['created_at']) ?></p>
                    <form method="POST" class="mt-2 inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="cancel_change">
                        <input type="hidden" name="change_id" value="<?= e($pr['id']) ?>">
                        <input type="hidden" name="confirm_password" value="skip_for_cancel">
                        <button onclick="return confirm('Batalkan perubahan ini?')" class="text-red-600 hover:text-red-700 text-xs font-medium">Batalkan</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Request Change Forms -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Ajukan Perubahan</h3>

        <!-- Change Webhook URL -->
        <?php if (!$hasPendingWebhook): ?>
        <form method="POST" class="mb-6 pb-6 border-b border-slate-200" id="webhookForm">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="request_change">
            <input type="hidden" name="change_type" value="webhook_url">
            <div class="mb-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Webhook URL Baru</label>
                <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/webhook">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Alasan Perubahan</label>
                <input type="text" name="reason" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Migrasi server, update endpoint, dll.">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Masukkan password Anda">
            </div>
            <button type="submit" onclick="return confirm('Ajukan perubahan webhook URL? Perubahan akan aktif setelah disetujui admin.')" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Ajukan Perubahan Webhook
            </button>
        </form>
        <?php endif; ?>

        <!-- Change Redirect URL -->
        <?php if (!$hasPendingRedirect): ?>
        <form method="POST" id="redirectForm">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="request_change">
            <input type="hidden" name="change_type" value="redirect_url">
            <div class="mb-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Redirect URL Baru</label>
                <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/success">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Alasan Perubahan</label>
                <input type="text" name="reason" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Update halaman sukses, dll.">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Masukkan password Anda">
            </div>
            <button type="submit" onclick="return confirm('Ajukan perubahan redirect URL?')" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                Ajukan Perubahan Redirect
            </button>
        </form>
        <?php endif; ?>

        <?php if ($hasPendingWebhook && $hasPendingRedirect): ?>
        <p class="text-sm text-slate-400 py-4">Semua konfigurasi memiliki perubahan pending. Tunggu verifikasi atau batalkan perubahan yang ada.</p>
        <?php endif; ?>
    </div>

    <!-- View History Link -->
    <div class="text-center">
        <a href="/merchant/config-changes.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Lihat Riwayat Perubahan Konfigurasi &rarr;</a>
    </div>

    <!-- Webhook Signature Info -->
    <div class="bg-slate-900 rounded-xl p-6">
        <h4 class="text-sm font-semibold text-slate-300 mb-3">Validasi Signature Webhook</h4>
        <p class="text-xs text-slate-400 mb-4">Setiap webhook dikirim dengan header <code class="text-emerald-400">X-Signature</code> berisi HMAC SHA-256 dari payload menggunakan API key Anda sebagai secret.</p>
        <pre class="text-xs text-emerald-300 font-mono overflow-x-auto bg-slate-800 rounded-lg p-4">$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$secret = 'YOUR_API_KEY';
$calculated = hash_hmac('sha256', $payload, $secret);

if (hash_equals($calculated, $signature)) {
    // Valid - proses pembayaran
} else {
    http_response_code(403);
    exit('Invalid signature');
}</pre>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
