<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();
$merchant = $controller->getMerchant();

if (is_post()) {
    Auth::verifyCsrf();
    $result = $controller->createPayment($_POST);
    if ($result['success']) {
        flash('success', $result['message']);
        $_SESSION['last_payment_url'] = $result['transaction']['payment_url'] ?? '';
        $_SESSION['last_order_id'] = $result['transaction']['order_id'] ?? '';
        redirect('/merchant/transactions.php');
    } else {
        flash('error', $result['message']);
    }
}

$pageTitle = 'Buat Pembayaran';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<?php if ($merchant['status'] !== 'active'): ?>
<div class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
    Merchant belum aktif. Anda tidak dapat membuat transaksi.
</div>
<?php endif; ?>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <form method="POST" class="space-y-5">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Order ID <span class="text-slate-400">(opsional)</span></label>
                    <input type="text" name="order_id" value="<?= e($_POST['order_id'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500" placeholder="Auto-generate jika kosong">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Amount (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" required min="1" value="<?= e($_POST['amount'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500" placeholder="10000">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Pembayaran</label>
                <input type="text" name="link_name" value="<?= e($_POST['link_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Pembayaran Pesanan #123">
            </div>

            <div class="border-t border-slate-200 pt-4">
                <p class="text-sm font-medium text-slate-700 mb-3">Informasi Customer</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Nama</label>
                        <input type="text" name="customer_name" value="<?= e($_POST['customer_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="John Doe">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">WhatsApp</label>
                        <input type="text" name="customer_wa" value="<?= e($_POST['customer_wa'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="08123456789">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-slate-500 mb-1">Email</label>
                        <input type="email" name="customer_email" value="<?= e($_POST['customer_email'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="customer@email.com">
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <p class="text-sm font-medium text-slate-700 mb-3">URL Opsional</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Webhook URL</label>
                        <input type="url" name="webhook_url" value="<?= e($_POST['webhook_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://domain.com/webhook">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Redirect URL</label>
                        <input type="url" name="redirect_url" value="<?= e($_POST['redirect_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://domain.com/success">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Catatan Internal</label>
                <textarea name="note" rows="2" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Catatan untuk internal..."><?= e($_POST['note'] ?? '') ?></textarea>
            </div>

            <button type="submit" <?= $merchant['status'] !== 'active' ? 'disabled' : '' ?> class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                Buat Pembayaran
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
