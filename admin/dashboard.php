<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireAdmin();

require_once base_path('app/Controllers/AdminController.php');
$controller = new AdminController();
$data = $controller->dashboard();
$stats = $data['stats'];
$merchantCounts = $data['merchant_counts'];
$recentTx = $data['recent_transactions'];
$pendingWd = $data['pending_withdrawals'];

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500">Total Transaksi</p>
                <p class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($stats['total_transactions']) ?></p>
            </div>
            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
        </div>
        <p class="text-xs text-slate-400 mt-2">Hari ini: <?= $stats['today_transactions'] ?></p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500">Total Revenue</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1"><?= format_currency($stats['total_revenue']) ?></p>
            </div>
            <div class="w-10 h-10 bg-emerald-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-xs text-slate-400 mt-2">Fee: <?= format_currency($stats['total_fee']) ?></p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500">Merchant Aktif</p>
                <p class="text-2xl font-bold text-slate-800 mt-1"><?= $merchantCounts['active'] ?></p>
            </div>
            <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>
        <p class="text-xs text-slate-400 mt-2">Pending: <?= $merchantCounts['pending'] ?></p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-slate-500">Tx Bulan Ini</p>
                <p class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($stats['month_transactions']) ?></p>
            </div>
            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        </div>
        <p class="text-xs text-slate-400 mt-2">Revenue: <?= format_currency($stats['month_revenue']) ?></p>
    </div>
</div>

<!-- Status Summary -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-4">
        <div class="w-10 h-10 bg-amber-200 rounded-full flex items-center justify-center">
            <span class="text-amber-700 font-bold text-sm"><?= $stats['pending_count'] ?></span>
        </div>
        <div>
            <p class="text-sm font-medium text-amber-800">Transaksi Pending</p>
            <p class="text-xs text-amber-600">Menunggu pembayaran</p>
        </div>
    </div>
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-center gap-4">
        <div class="w-10 h-10 bg-emerald-200 rounded-full flex items-center justify-center">
            <span class="text-emerald-700 font-bold text-sm"><?= $stats['paid_count'] ?></span>
        </div>
        <div>
            <p class="text-sm font-medium text-emerald-800">Transaksi Paid</p>
            <p class="text-xs text-emerald-600">Pembayaran berhasil</p>
        </div>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-4">
        <div class="w-10 h-10 bg-red-200 rounded-full flex items-center justify-center">
            <span class="text-red-700 font-bold text-sm"><?= $stats['failed_count'] ?></span>
        </div>
        <div>
            <p class="text-sm font-medium text-red-800">Transaksi Failed</p>
            <p class="text-xs text-red-600">Gagal/expired</p>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-800">Transaksi Terbaru</h3>
        <a href="/admin/transactions.php" class="text-sm text-blue-600 hover:text-blue-700">Lihat Semua &rarr;</a>
    </div>
    <?php if (empty($recentTx)): ?>
    <div class="p-8 text-center text-slate-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <p class="text-sm">Belum ada transaksi.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-6 py-3 text-left font-medium">Order ID</th>
                    <th class="px-6 py-3 text-left font-medium">Amount</th>
                    <th class="px-6 py-3 text-left font-medium">Status</th>
                    <th class="px-6 py-3 text-left font-medium">Tanggal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach (array_slice($recentTx, 0, 10) as $tx): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3 font-mono text-xs"><?= e($tx['order_id']) ?></td>
                    <td class="px-6 py-3 font-medium"><?= format_currency($tx['amount']) ?></td>
                    <td class="px-6 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($tx['status']) ?>">
                            <?= e($tx['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-3 text-slate-500"><?= format_date($tx['created_at'], 'd/m/Y H:i') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
