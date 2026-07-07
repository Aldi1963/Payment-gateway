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

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Revenue Chart (Last 7 days) -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Revenue 7 Hari Terakhir</h3>
        <canvas id="revenueChart" height="200"></canvas>
    </div>
    <!-- Transaction Status Breakdown -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Status Transaksi</h3>
        <canvas id="statusChart" height="200"></canvas>
    </div>
</div>

<!-- Export Button -->
<div class="flex justify-end mb-4">
    <a href="/export.php?type=transactions&format=csv" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-xs font-medium hover:bg-slate-200 flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Export CSV
    </a>
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

<?php
// Build chart data - last 7 days revenue
$chartDays = [];
$chartRevenue = [];
$chartFee = [];
$chartTxCount = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartDays[] = date('d/m', strtotime($date));
    $dayRevenue = 0;
    $dayFee = 0;
    $dayCount = 0;
    foreach ($recentTx as $tx) {
        if (substr($tx['created_at'] ?? '', 0, 10) === $date && ($tx['status'] ?? '') === 'PAID') {
            $dayRevenue += $tx['amount'] ?? 0;
            $dayFee += $tx['fee'] ?? 0;
            $dayCount++;
        }
    }
    $chartRevenue[] = $dayRevenue;
    $chartFee[] = $dayFee;
    $chartTxCount[] = $dayCount;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Revenue Line Chart
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartDays) ?>,
        datasets: [
            {
                label: 'Revenue',
                data: <?= json_encode($chartRevenue) ?>,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 3,
            },
            {
                label: 'Fee',
                data: <?= json_encode($chartFee) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 3,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + v.toLocaleString('id-ID') } },
            x: { grid: { display: false } }
        }
    }
});

// Status Doughnut Chart
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Paid', 'Pending', 'Failed', 'Expired'],
        datasets: [{
            data: [<?= $stats['paid_count'] ?>, <?= $stats['pending_count'] ?>, <?= $stats['failed_count'] ?>, <?= ($stats['total_transactions'] - $stats['paid_count'] - $stats['pending_count'] - $stats['failed_count']) ?>],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#94a3b8'],
            borderWidth: 0,
            hoverOffset: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } }
        },
        cutout: '65%',
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
