<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();
$data = $controller->dashboard();
$merchant = $data['merchant'];
$stats = $data['stats'];
$wallet = $data['wallet'];
$recentTx = $data['recent_transactions'];

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<?php if ($merchant['status'] === 'pending'): ?>
<div class="mb-6 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    <strong>Akun menunggu verifikasi.</strong> Admin akan mengaktifkan akun Anda segera.
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-sm text-slate-500">Saldo Tersedia</p>
        <p class="text-2xl font-bold text-emerald-600 mt-1"><?= format_currency($wallet['available_balance'] ?? 0) ?></p>
        <p class="text-xs text-slate-400 mt-2">Hold: <?= format_currency($wallet['hold_balance'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-sm text-slate-500">Total Revenue</p>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= format_currency($stats['total_revenue']) ?></p>
        <p class="text-xs text-slate-400 mt-2">Bulan ini: <?= format_currency($stats['month_revenue']) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-sm text-slate-500">Transaksi Hari Ini</p>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= $stats['today_transactions'] ?></p>
        <p class="text-xs text-slate-400 mt-2">Revenue: <?= format_currency($stats['today_revenue']) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-sm text-slate-500">Total Transaksi</p>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= number_format($stats['total_transactions']) ?></p>
        <p class="text-xs text-slate-400 mt-2">Paid: <?= $stats['paid_count'] ?> | Pending: <?= $stats['pending_count'] ?></p>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <a href="/merchant/create-payment.php" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl p-5 transition-colors flex items-center gap-4">
        <div class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        </div>
        <div>
            <p class="font-semibold">Buat Pembayaran</p>
            <p class="text-xs text-emerald-200">Generate payment link baru</p>
        </div>
    </a>
    <a href="/merchant/wallet.php" class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl p-5 transition-colors flex items-center gap-4">
        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        </div>
        <div>
            <p class="font-semibold">Lihat Wallet</p>
            <p class="text-xs text-blue-200">Cek saldo & mutasi</p>
        </div>
    </a>
    <a href="/merchant/withdraw.php" class="bg-slate-700 hover:bg-slate-800 text-white rounded-xl p-5 transition-colors flex items-center gap-4">
        <div class="w-10 h-10 bg-slate-600 rounded-lg flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1"/></svg>
        </div>
        <div>
            <p class="font-semibold">Tarik Dana</p>
            <p class="text-xs text-slate-300">Cairkan saldo Anda</p>
        </div>
    </a>
</div>

<!-- Charts -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-3">Revenue 7 Hari Terakhir</h3>
        <div class="relative" style="height: 180px;">
            <canvas id="mRevenueChart"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-800 mb-3">Transaksi Harian</h3>
        <div class="relative" style="height: 180px;">
            <canvas id="mTxChart"></canvas>
        </div>
    </div>
</div>

<!-- Export -->
<div class="flex justify-end mb-4 gap-2">
    <a href="/export.php?type=transactions&format=csv" class="px-3 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-xs hover:bg-slate-200">Export Transaksi CSV</a>
    <a href="/export.php?type=withdrawals&format=csv" class="px-3 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-xs hover:bg-slate-200">Export Withdrawal CSV</a>
</div>

<!-- Recent Transactions -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-800">Transaksi Terbaru</h3>
        <a href="/merchant/transactions.php" class="text-sm text-blue-600 hover:text-blue-700">Lihat Semua &rarr;</a>
    </div>
    <?php if (empty($recentTx)): ?>
    <div class="p-8 text-center text-slate-400">
        <p class="text-sm">Belum ada transaksi. <a href="/merchant/create-payment.php" class="text-blue-600">Buat sekarang</a></p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Order ID</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Amount</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Tanggal</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($recentTx as $tx): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3 font-mono text-xs"><?= e($tx['order_id']) ?></td>
                <td class="px-6 py-3 font-medium"><?= format_currency($tx['amount']) ?></td>
                <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($tx['status']) ?>"><?= $tx['status'] ?></span></td>
                <td class="px-6 py-3 text-slate-500 text-xs"><?= format_date($tx['created_at'], 'd/m/Y H:i') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
// Chart data - last 7 days (use ALL transactions from the controller, not just recent)
$allTxForChart = $controller->transactions([]);
$mChartDays = []; $mChartRev = []; $mChartCount = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $mChartDays[] = date('d/m', strtotime($date));
    $dayRev = 0; $dayCnt = 0;
    foreach ($allTxForChart as $tx) {
        if (substr($tx['created_at'] ?? '', 0, 10) === $date) {
            $dayCnt++;
            if (($tx['status'] ?? '') === 'PAID') $dayRev += $tx['net_amount'] ?? 0;
        }
    }
    $mChartRev[] = $dayRev;
    $mChartCount[] = $dayCnt;
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('mRevenueChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($mChartDays) ?>,
        datasets: [{
            label: 'Net Revenue',
            data: <?= json_encode($mChartRev) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16,185,129,0.1)',
            fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + v.toLocaleString('id-ID') } }, x: { grid: { display: false } } }
    }
});
new Chart(document.getElementById('mTxChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($mChartDays) ?>,
        datasets: [{
            label: 'Transaksi',
            data: <?= json_encode($mChartCount) ?>,
            backgroundColor: '#3b82f6',
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { grid: { display: false } } }
    }
});
</script>
<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
