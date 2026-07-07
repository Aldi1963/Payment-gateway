<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();

if (is_post()) {
    Auth::verifyCsrf();
    $result = $controller->updateWebhookSettings($_POST);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect('/merchant/webhook-settings.php');
}

$merchant = $controller->getMerchant();
$pageTitle = 'Webhook Settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-2">Pengaturan Webhook</h3>
        <p class="text-sm text-slate-500 mb-6">Webhook digunakan untuk menerima notifikasi real-time saat pembayaran berhasil.</p>

        <form method="POST" class="space-y-5">
            <?= csrf_field() ?>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Webhook URL</label>
                <input type="url" name="webhook_url" value="<?= e($merchant['webhook_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/webhook">
                <p class="text-xs text-slate-400 mt-1">Kami akan mengirim POST request ke URL ini saat status pembayaran berubah.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Redirect URL (setelah bayar)</label>
                <input type="url" name="redirect_url" value="<?= e($merchant['redirect_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/success">
                <p class="text-xs text-slate-400 mt-1">Customer akan diarahkan ke URL ini setelah pembayaran berhasil.</p>
            </div>

            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
        </form>
    </div>

    <!-- Webhook Info -->
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
