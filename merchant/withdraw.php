<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();
$walletData = $controller->wallet();
$wallet = $walletData['wallet'];
$merchant = $controller->getMerchant();

if (is_post()) {
    Auth::verifyCsrf();
    $result = $controller->requestWithdrawal($_POST);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) redirect('/merchant/withdraw-history.php');
}

$pageTitle = 'Tarik Dana';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="max-w-lg">
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-6">
        <p class="text-sm text-emerald-700">Saldo tersedia: <strong><?= format_currency($wallet['available_balance'] ?? 0) ?></strong></p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Form Penarikan</h3>
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Jumlah (Rp) <span class="text-red-500">*</span></label>
                <input type="number" name="amount" required min="10000" max="<?= $wallet['available_balance'] ?? 0 ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Minimal Rp 10.000">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Bank <span class="text-red-500">*</span></label>
                <select name="bank_name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
                    <option value="">Pilih Bank</option>
                    <?php
                    require_once base_path('app/Repositories/SettingRepository.php');
                    $sr = new SettingRepository();
                    $banks = $sr->get('bank_list', ['BCA','BNI','BRI','Mandiri','CIMB Niaga','BSI','Permata','DANA','OVO','GoPay','ShopeePay']);
                    if (!is_array($banks)) $banks = array_filter(array_map('trim', explode("\n", $banks)));
                    // Pre-fill from merchant's saved bank
                    $merchantBank = $merchant['bank_name'] ?? '';
                    foreach ($banks as $bank): $bank = trim($bank); if (empty($bank)) continue; ?>
                    <option value="<?= e($bank) ?>" <?= $merchantBank === $bank ? 'selected' : '' ?>><?= e($bank) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Rekening <span class="text-red-500">*</span></label>
                <input type="text" name="account_number" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="1234567890" value="<?= e($merchant['bank_account_number'] ?? '') ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Pemilik Rekening <span class="text-red-500">*</span></label>
                <input type="text" name="account_name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Sesuai buku tabungan" value="<?= e($merchant['bank_account_name'] ?? '') ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Catatan</label>
                <textarea name="note" rows="2" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Catatan opsional..."></textarea>
            </div>

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                Ajukan Penarikan
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
