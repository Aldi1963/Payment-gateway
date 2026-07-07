<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireAdmin();

require_once base_path('app/Controllers/AdminController.php');
$controller = new AdminController();

$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

$transactions = $controller->transactions($filters);
$pagination = paginate($transactions, (int)($_GET['page'] ?? 1));

$pageTitle = 'Transaksi';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex items-center gap-2 flex-wrap">
        <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Cari order ID..." class="px-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 w-52">
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">Semua Status</option>
            <option value="PENDING" <?= ($_GET['status'] ?? '') === 'PENDING' ? 'selected' : '' ?>>Pending</option>
            <option value="PAID" <?= ($_GET['status'] ?? '') === 'PAID' ? 'selected' : '' ?>>Paid</option>
            <option value="FAILED" <?= ($_GET['status'] ?? '') === 'FAILED' ? 'selected' : '' ?>>Failed</option>
            <option value="EXPIRED" <?= ($_GET['status'] ?? '') === 'EXPIRED' ? 'selected' : '' ?>>Expired</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-700">Filter</button>
        <?php if (!empty($_GET['search']) || !empty($_GET['status'])): ?>
        <a href="/admin/transactions.php" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700">Reset</a>
        <?php endif; ?>
    </form>
    <p class="text-sm text-slate-500"><?= $pagination['total'] ?> transaksi</p>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg>
        <p>Tidak ada transaksi ditemukan.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-6 py-3 text-left font-medium">Order ID</th>
                    <th class="px-6 py-3 text-left font-medium">Customer</th>
                    <th class="px-6 py-3 text-left font-medium">Amount</th>
                    <th class="px-6 py-3 text-left font-medium">Fee</th>
                    <th class="px-6 py-3 text-left font-medium">Status</th>
                    <th class="px-6 py-3 text-left font-medium">Tanggal</th>
                    <th class="px-6 py-3 text-left font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($pagination['data'] as $tx): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3 font-mono text-xs text-blue-600"><?= e($tx['order_id']) ?></td>
                    <td class="px-6 py-3">
                        <p class="text-slate-800"><?= e($tx['customer_name'] ?: '-') ?></p>
                        <p class="text-xs text-slate-400"><?= e($tx['customer_email'] ?? '') ?></p>
                    </td>
                    <td class="px-6 py-3 font-medium"><?= format_currency($tx['amount']) ?></td>
                    <td class="px-6 py-3 text-slate-500"><?= format_currency($tx['fee'] ?? 0) ?></td>
                    <td class="px-6 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($tx['status']) ?>">
                            <?= e($tx['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-3 text-slate-500 text-xs"><?= format_date($tx['created_at'], 'd/m/Y H:i') ?></td>
                    <td class="px-6 py-3">
                        <button onclick="showTxDetail('<?= e($tx['id']) ?>')" class="text-blue-600 hover:text-blue-700 text-xs font-medium">Detail</button>
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
            <?php if ($pagination['has_prev']): ?>
            <a href="?page=<?= $pagination['current_page'] - 1 ?>&<?= http_build_query(array_filter(['status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? ''])) ?>" class="px-3 py-1 rounded text-sm text-slate-600 hover:bg-slate-100">&laquo; Prev</a>
            <?php endif; ?>
            <?php if ($pagination['has_next']): ?>
            <a href="?page=<?= $pagination['current_page'] + 1 ?>&<?= http_build_query(array_filter(['status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? ''])) ?>" class="px-3 py-1 rounded text-sm text-slate-600 hover:bg-slate-100">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Transaction Detail Modal -->
<div id="txDetailModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeTxDetail()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[80vh] overflow-y-auto p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Detail Transaksi</h3>
        <div id="txDetailContent" class="text-sm space-y-2">Loading...</div>
        <button onclick="closeTxDetail()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>

<script>
function showTxDetail(id) {
    document.getElementById('txDetailModal').classList.remove('hidden');
    document.getElementById('txDetailContent').innerHTML = '<p class="text-slate-400">Memuat...</p>';
    
    fetch('/api/index.php?action=tx_detail&id=' + id, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const tx = data.transaction;
                let html = `
                    <div class="space-y-3">
                        <div class="flex justify-between"><span class="text-slate-500">Order ID</span><span class="font-mono font-medium">${tx.order_id}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Amount</span><span class="font-bold">${tx.amount?.toLocaleString('id-ID')}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Fee</span><span>${tx.fee?.toLocaleString('id-ID')}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Net</span><span>${tx.net_amount?.toLocaleString('id-ID')}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Status</span><span class="font-medium">${tx.status}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Customer</span><span>${tx.customer_name || '-'}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Dibuat</span><span>${tx.created_at}</span></div>
                        ${tx.payment_url ? '<div class="pt-2"><a href="'+tx.payment_url+'" target="_blank" class="text-blue-600 text-xs break-all">'+tx.payment_url+'</a></div>' : ''}
                    </div>`;
                document.getElementById('txDetailContent').innerHTML = html;
            }
        })
        .catch(() => { document.getElementById('txDetailContent').innerHTML = '<p class="text-red-500">Gagal memuat detail.</p>'; });
}
function closeTxDetail() { document.getElementById('txDetailModal').classList.add('hidden'); }
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
