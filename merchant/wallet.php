<?php
/**
 * Wallet - Consolidated Page
 * Tabs: Saldo, Tarik Dana, Riwayat Penarikan, Mutasi
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();
$walletData = $controller->wallet();
$wallet = $walletData['wallet'];
$ledger = $walletData['ledger'];
$merchant = $controller->getMerchant();

$activeTab = $_GET['tab'] ?? 'saldo';
$validTabs = ['saldo', 'withdraw', 'history', 'mutasi'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'saldo';

// Handle withdraw POST
if (is_post() && $activeTab === 'withdraw') {
    Auth::verifyCsrf();
    $result = $controller->requestWithdrawal($_POST);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) redirect('/merchant/wallet.php?tab=history');
    redirect('/merchant/wallet.php?tab=withdraw');
}

// Handle cancel withdrawal POST
if (is_post() && ($_POST['action'] ?? '') === 'cancel') {
    Auth::verifyCsrf();
    $result = $controller->cancelWithdrawal($_POST['withdrawal_id'] ?? '');
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect('/merchant/wallet.php?tab=history');
}

// Load withdrawal history if needed
$withdrawals = [];
$pagination = null;
if ($activeTab === 'history') {
    $withdrawals = $controller->withdrawalHistory();
    $pagination = paginate($withdrawals, (int)($_GET['page'] ?? 1));
}

$pageTitle = 'Wallet';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<!-- Tab Navigation -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <?php
        $tabs = [
            'saldo' => 'Saldo',
            'withdraw' => 'Tarik Dana',
            'history' => 'Riwayat Penarikan',
            'mutasi' => 'Mutasi',
        ];
        foreach ($tabs as $key => $label): ?>
        <a href="?tab=<?= $key ?>" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $activeTab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<?php if ($activeTab === 'saldo'): ?>
<!-- ============ TAB: SALDO ============ -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-xl p-5">
        <p class="text-sm text-blue-100">Saldo Tersedia</p>
        <p class="text-3xl font-bold mt-1"><?= format_currency($wallet['available_balance'] ?? 0) ?></p>
        <a href="?tab=withdraw" class="inline-block mt-3 text-xs bg-white/20 hover:bg-white/30 rounded px-3 py-1 transition-colors">Tarik Dana &rarr;</a>
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

<!-- Quick Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-xs text-slate-500">Pending Balance</p>
        <p class="text-lg font-bold text-slate-700 mt-1"><?= format_currency($wallet['pending_balance'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-xs text-slate-500">Total Fee</p>
        <p class="text-lg font-bold text-red-600 mt-1"><?= format_currency($wallet['total_fee'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-xs text-slate-500">Total Ditarik</p>
        <p class="text-lg font-bold text-slate-700 mt-1"><?= format_currency($wallet['withdrawn_balance'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center">
        <p class="text-xs text-slate-500">Total Diterima</p>
        <p class="text-lg font-bold text-emerald-600 mt-1"><?= format_currency($wallet['total_received'] ?? 0) ?></p>
    </div>
</div>

<?php elseif ($activeTab === 'withdraw'): ?>
<!-- ============ TAB: TARIK DANA ============ -->
<div class="max-w-lg">
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
        <p class="text-sm text-blue-700">Saldo tersedia: <strong><?= format_currency($wallet['available_balance'] ?? 0) ?></strong></p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Form Penarikan</h3>
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Jumlah (Rp) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" required min="10000" max="<?= $wallet['available_balance'] ?? 0 ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Minimal Rp 10.000">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Bank <span class="text-red-500">*</span></label>
                <select name="bank_name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    <option value="">Pilih Bank</option>
                    <?php
                    require_once base_path('app/Repositories/SettingRepository.php');
                    $sr = new SettingRepository();
                    $banks = $sr->get('bank_list', ['BCA','BNI','BRI','Mandiri','CIMB Niaga','BSI','Permata','DANA','OVO','GoPay','ShopeePay']);
                    if (!is_array($banks)) $banks = array_filter(array_map('trim', explode("\n", $banks)));
                    $merchantBank = $merchant['bank_name'] ?? '';
                    foreach ($banks as $bank): $bank = trim($bank); if (empty($bank)) continue; ?>
                    <option value="<?= e($bank) ?>" <?= $merchantBank === $bank ? 'selected' : '' ?>><?= e($bank) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Rekening <span class="text-red-500">*</span></label>
                <input type="text" name="account_number" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="1234567890" value="<?= e($merchant['bank_account_number'] ?? '') ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Pemilik Rekening <span class="text-red-500">*</span></label>
                <input type="text" name="account_name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Sesuai buku tabungan" value="<?= e($merchant['bank_account_name'] ?? '') ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                <textarea name="note" rows="2" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Catatan opsional..."></textarea>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                Ajukan Penarikan
            </button>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'history'): ?>
<!-- ============ TAB: RIWAYAT PENARIKAN ============ -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400"><p>Belum ada riwayat penarikan.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Amount</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Bank</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Rekening</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Tanggal</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $wd): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3 font-medium"><?= format_currency($wd['amount']) ?></td>
                <td class="px-6 py-3"><?= e($wd['bank_name']) ?></td>
                <td class="px-6 py-3 text-xs"><p><?= e($wd['account_number']) ?></p><p class="text-slate-400"><?= e($wd['account_name']) ?></p></td>
                <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($wd['status']) ?>"><?= $wd['status'] ?></span></td>
                <td class="px-6 py-3 text-xs text-slate-500"><?= format_date($wd['created_at'], 'd/m/Y H:i') ?></td>
                <td class="px-6 py-3">
                    <?php if ($wd['status'] === 'PENDING'): ?>
                    <form method="POST" onsubmit="return confirm('Batalkan penarikan ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="withdrawal_id" value="<?= $wd['id'] ?>">
                        <button class="text-xs text-red-600 hover:text-red-700 font-medium">Batalkan</button>
                    </form>
                    <?php else: ?><span class="text-xs text-slate-400">-</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($activeTab === 'mutasi'): ?>
<!-- ============ TAB: MUTASI ============ -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-sm font-semibold text-slate-800">Riwayat Mutasi Wallet</h3>
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

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
