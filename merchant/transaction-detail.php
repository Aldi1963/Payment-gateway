<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();

$id = $_GET['id'] ?? '';
$tx = $controller->transactionDetail($id);

if (!$tx) {
    flash('error', 'Transaksi tidak ditemukan.');
    redirect('/merchant/transactions.php');
}

$pageTitle = 'Detail Transaksi';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="max-w-3xl">
    <a href="/merchant/transactions.php" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Kembali
    </a>

    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold"><?= e($tx['order_id']) ?></h3>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= status_badge_class($tx['status']) ?>"><?= $tx['status'] ?></span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div><span class="text-slate-500">Amount</span><p class="font-bold text-lg mt-0.5"><?= format_currency($tx['amount']) ?></p></div>
            <div><span class="text-slate-500">Fee</span><p class="font-medium mt-0.5"><?= format_currency($tx['fee']) ?></p></div>
            <div><span class="text-slate-500">Net Amount</span><p class="font-medium text-emerald-600 mt-0.5"><?= format_currency($tx['net_amount']) ?></p></div>
            <div><span class="text-slate-500">Dibuat</span><p class="mt-0.5"><?= format_date($tx['created_at']) ?></p></div>
            <?php if ($tx['paid_at']): ?><div><span class="text-slate-500">Dibayar</span><p class="mt-0.5"><?= format_date($tx['paid_at']) ?></p></div><?php endif; ?>
            <div><span class="text-slate-500">Customer</span><p class="mt-0.5"><?= e($tx['customer_name'] ?: '-') ?></p></div>
            <div><span class="text-slate-500">WhatsApp</span><p class="mt-0.5"><?= e($tx['customer_wa'] ?: '-') ?></p></div>
            <div><span class="text-slate-500">Email</span><p class="mt-0.5"><?= e($tx['customer_email'] ?: '-') ?></p></div>
        </div>
    </div>

    <?php if (!empty($tx['payment_url'])): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">Payment URL</h4>
        <div class="flex items-center gap-2">
            <input type="text" value="<?= e($tx['payment_url']) ?>" readonly class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono" id="payUrl">
            <button onclick="copyToClipboard('payUrl')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs hover:bg-blue-700">Copy</button>
            <a href="<?= e($tx['payment_url']) ?>" target="_blank" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-xs hover:bg-slate-200">Open</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($tx['qr_url'])): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
        <h4 class="text-sm font-semibold text-slate-800 mb-3">QR Code</h4>
        <img src="<?= e($tx['qr_url']) ?>" alt="QRIS" class="w-48 h-48 mx-auto border rounded-lg">
    </div>
    <?php endif; ?>

    <?php if (!empty($tx['note'])): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-6 mb-4">
        <h4 class="text-sm font-semibold text-slate-800 mb-2">Catatan</h4>
        <p class="text-sm text-slate-600"><?= e($tx['note']) ?></p>
    </div>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(id) { navigator.clipboard.writeText(document.getElementById(id).value).then(()=>showToast('Copied!')); }
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
