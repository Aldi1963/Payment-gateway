<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();

$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

$transactions = $controller->transactions($filters);
$pagination = paginate($transactions, (int)($_GET['page'] ?? 1));

$pageTitle = 'Transaksi';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<?php if (!empty($_SESSION['last_payment_url'])): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">
    <p class="font-medium mb-1">Payment link berhasil dibuat!</p>
    <div class="flex items-center gap-2">
        <input type="text" value="<?= e($_SESSION['last_payment_url']) ?>" readonly class="flex-1 px-3 py-1.5 bg-white border border-blue-200 rounded text-xs font-mono" id="paymentUrlInput">
        <button onclick="copyToClipboard('paymentUrlInput')" class="px-3 py-1.5 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">Copy</button>
    </div>
</div>
<?php unset($_SESSION['last_payment_url'], $_SESSION['last_order_id']); endif; ?>

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex items-center gap-2 flex-wrap">
        <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Cari order ID..." class="px-4 py-2 border border-slate-300 rounded-lg text-sm w-52">
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">Semua</option>
            <option value="PENDING" <?= ($_GET['status'] ?? '') === 'PENDING' ? 'selected' : '' ?>>Pending</option>
            <option value="PAID" <?= ($_GET['status'] ?? '') === 'PAID' ? 'selected' : '' ?>>Paid</option>
            <option value="FAILED" <?= ($_GET['status'] ?? '') === 'FAILED' ? 'selected' : '' ?>>Failed</option>
            <option value="EXPIRED" <?= ($_GET['status'] ?? '') === 'EXPIRED' ? 'selected' : '' ?>>Expired</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm">Filter</button>
    </form>
    <a href="/merchant/create-payment.php" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">+ Buat Baru</a>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400"><p>Belum ada transaksi.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Order ID</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Customer</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Amount</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Tanggal</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $tx): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3 font-mono text-xs text-blue-600"><?= e($tx['order_id']) ?></td>
                <td class="px-6 py-3 text-slate-600"><?= e($tx['customer_name'] ?: '-') ?></td>
                <td class="px-6 py-3 font-medium"><?= format_currency($tx['amount']) ?></td>
                <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($tx['status']) ?>"><?= $tx['status'] ?></span></td>
                <td class="px-6 py-3 text-slate-500 text-xs"><?= format_date($tx['created_at'], 'd/m/Y H:i') ?></td>
                <td class="px-6 py-3">
                    <a href="/merchant/transaction-detail.php?id=<?= e($tx['id']) ?>" class="text-blue-600 text-xs font-medium hover:text-blue-700">Detail</a>
                    <?php if (!empty($tx['payment_url'])): ?>
                    <button onclick="copyText('<?= e($tx['payment_url']) ?>')" class="ml-2 text-slate-400 hover:text-slate-600 text-xs">Copy Link</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between">
        <p class="text-sm text-slate-500">Halaman <?= $pagination['current_page'] ?> dari <?= $pagination['total_pages'] ?></p>
        <div class="flex gap-1">
            <?php if ($pagination['has_prev']): ?><a href="?page=<?= $pagination['current_page']-1 ?>&<?= http_build_query(array_filter(['status'=>$_GET['status']??'','search'=>$_GET['search']??''])) ?>" class="px-3 py-1 rounded text-sm text-slate-600 hover:bg-slate-100">&laquo;</a><?php endif; ?>
            <?php if ($pagination['has_next']): ?><a href="?page=<?= $pagination['current_page']+1 ?>&<?= http_build_query(array_filter(['status'=>$_GET['status']??'','search'=>$_GET['search']??''])) ?>" class="px-3 py-1 rounded text-sm text-slate-600 hover:bg-slate-100">&raquo;</a><?php endif; ?>
        </div>
    </div>
    <?php endif; endif; ?>
</div>

<script>
function copyText(text) { navigator.clipboard.writeText(text).then(()=>showToast('Link copied!')); }
function copyToClipboard(id) { const el=document.getElementById(id); navigator.clipboard.writeText(el.value).then(()=>showToast('Copied!')); }
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
