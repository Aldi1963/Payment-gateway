<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();

$transactions = $controller->transactions(['status' => '']);
// Filter only those with payment_url
$links = array_filter($transactions, fn($tx) => !empty($tx['payment_url']));
$pagination = paginate(array_values($links), (int)($_GET['page'] ?? 1));

$pageTitle = 'Payment Links';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="flex justify-end mb-4">
    <a href="/merchant/create-payment.php" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">+ Buat Payment Link</a>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400">
        <p>Belum ada payment link. <a href="/merchant/create-payment.php" class="text-blue-600">Buat sekarang</a></p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-slate-100">
        <?php foreach ($pagination['data'] as $tx): ?>
        <div class="p-4 hover:bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs text-slate-600"><?= e($tx['order_id']) ?></span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($tx['status']) ?>"><?= $tx['status'] ?></span>
                </div>
                <p class="text-sm font-medium text-slate-800 mt-1"><?= e($tx['link_name'] ?: $tx['order_id']) ?> - <?= format_currency($tx['amount']) ?></p>
                <p class="text-xs text-slate-400 mt-0.5 truncate"><?= e($tx['payment_url']) ?></p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button onclick="copyText('<?= e($tx['payment_url']) ?>')" class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded text-xs font-medium hover:bg-blue-100">Copy Link</button>
                <a href="<?= e($tx['payment_url']) ?>" target="_blank" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded text-xs hover:bg-slate-200">Open</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function copyText(text) { navigator.clipboard.writeText(text).then(()=>showToast('Link copied!')); }
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
