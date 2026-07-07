<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();
$data = $controller->wallet();
$wallet = $data['wallet'];
$ledger = $data['ledger'];

$pageTitle = 'Wallet';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<!-- Balance Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 text-white rounded-xl p-5">
        <p class="text-sm text-emerald-100">Saldo Tersedia</p>
        <p class="text-3xl font-bold mt-1"><?= format_currency($wallet['available_balance'] ?? 0) ?></p>
        <a href="/merchant/withdraw.php" class="inline-block mt-3 text-xs bg-white/20 hover:bg-white/30 rounded px-3 py-1 transition-colors">Tarik Dana &rarr;</a>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-sm text-slate-500">Hold Balance</p>
        <p class="text-2xl font-bold text-amber-600 mt-1"><?= format_currency($wallet['hold_balance'] ?? 0) ?></p>
        <p class="text-xs text-slate-400 mt-2">Dalam proses pencairan</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <p class="text-sm text-slate-500">Total Diterima</p>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= format_currency($wallet['total_received'] ?? 0) ?></p>
        <p class="text-xs text-slate-400 mt-2">Fee: <?= format_currency($wallet['total_fee'] ?? 0) ?> | Dicairkan: <?= format_currency($wallet['withdrawn_balance'] ?? 0) ?></p>
    </div>
</div>

<!-- Ledger -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-sm font-semibold text-slate-800">Riwayat Mutasi</h3>
    </div>
    <?php if (empty($ledger)): ?>
    <div class="p-8 text-center text-slate-400"><p class="text-sm">Belum ada mutasi.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Type</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Amount</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Balance</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Keterangan</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Waktu</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($ledger as $entry): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $entry['type'] === 'credit' ? 'bg-emerald-100 text-emerald-700' : ($entry['type'] === 'fee' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-700') ?>">
                        <?= $entry['type'] ?>
                    </span>
                </td>
                <td class="px-6 py-3 font-medium <?= $entry['amount'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                    <?= $entry['amount'] >= 0 ? '+' : '' ?><?= format_currency(abs($entry['amount'])) ?>
                </td>
                <td class="px-6 py-3 text-slate-600"><?= format_currency($entry['balance_after']) ?></td>
                <td class="px-6 py-3 text-slate-500 text-xs max-w-xs truncate"><?= e($entry['description']) ?></td>
                <td class="px-6 py-3 text-xs text-slate-400"><?= format_date($entry['created_at'], 'd/m H:i') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
