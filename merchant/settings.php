<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Repositories/UserRepository.php');
require_once base_path('app/Services/AuditLogService.php');

$merchantRepo = new MerchantRepository();
$userRepo = new UserRepository();
$auditService = new AuditLogService();
$merchantId = Auth::merchantId();
$merchant = $merchantRepo->find($merchantId);
$user = $userRepo->find(Auth::id());

if (is_post()) {
    Auth::verifyCsrf();
    $tab = $_POST['_tab'] ?? 'business';

    if ($tab === 'business') {
        $merchantRepo->update($merchantId, [
            'business_name' => sanitize($_POST['business_name'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'city' => sanitize($_POST['city'] ?? ''),
            'website' => sanitize($_POST['website'] ?? ''),
            'business_type' => sanitize($_POST['business_type'] ?? ''),
            'description' => sanitize($_POST['description'] ?? ''),
            'updated_at' => now(),
        ]);
        $_SESSION['user_name'] = sanitize($_POST['business_name'] ?? $merchant['business_name']);
        $auditService->log(Auth::id(), Auth::role(), $merchantId, 'settings_changed', 'Business info updated', []);
        flash('success', 'Informasi bisnis berhasil disimpan.');


    } elseif ($tab === 'bank') {
        $merchantRepo->update($merchantId, [
            'bank_name' => sanitize($_POST['bank_name'] ?? ''),
            'bank_account_number' => sanitize($_POST['bank_account_number'] ?? ''),
            'bank_account_name' => sanitize($_POST['bank_account_name'] ?? ''),
            'bank_branch' => sanitize($_POST['bank_branch'] ?? ''),
            'updated_at' => now(),
        ]);
        flash('success', 'Informasi bank berhasil disimpan.');

    } elseif ($tab === 'notifications') {
        $merchantRepo->update($merchantId, [
            'notif_email_payment' => isset($_POST['notif_email_payment']) ? '1' : '0',
            'notif_email_withdrawal' => isset($_POST['notif_email_withdrawal']) ? '1' : '0',
            'notif_wa_payment' => isset($_POST['notif_wa_payment']) ? '1' : '0',
            'notif_wa_number' => sanitize($_POST['notif_wa_number'] ?? ''),
            'updated_at' => now(),
        ]);
        flash('success', 'Preferensi notifikasi berhasil disimpan.');

    } elseif ($tab === 'payment') {
        $merchantRepo->update($merchantId, [
            'payment_expiry_minutes' => (int)($_POST['payment_expiry_minutes'] ?? 60),
            'default_redirect_url' => sanitize($_POST['default_redirect_url'] ?? ''),
            'thank_you_message' => sanitize($_POST['thank_you_message'] ?? ''),
            'updated_at' => now(),
        ]);
        flash('success', 'Pengaturan pembayaran berhasil disimpan.');
    }
    redirect('/merchant/settings.php?tab=' . $tab);
}

// Reload data
$merchant = $merchantRepo->find($merchantId);
$activeTab = $_GET['tab'] ?? 'business';

// Get bank list from admin settings
require_once base_path('app/Repositories/SettingRepository.php');
$settingRepo = new SettingRepository();
$bankList = $settingRepo->get('bank_list', ['BCA','BNI','BRI','Mandiri','CIMB Niaga','BSI','Permata','DANA','OVO','GoPay','ShopeePay']);
if (!is_array($bankList)) $bankList = explode("\n", $bankList);

$pageTitle = 'Pengaturan';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>


<!-- Tab Navigation -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <?php $tabs = ['business'=>'Bisnis','bank'=>'Rekening Bank','payment'=>'Pembayaran','notifications'=>'Notifikasi'];
        foreach ($tabs as $tk => $tl): ?>
        <a href="?tab=<?= $tk ?>" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 <?= $activeTab === $tk ? 'border-emerald-600 text-emerald-600' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            <?= $tl ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="max-w-2xl">
<div class="bg-white rounded-xl border border-slate-200 p-6">

<?php if ($activeTab === 'business'): ?>
<h3 class="text-lg font-semibold text-slate-800 mb-4">Informasi Bisnis</h3>
<form method="POST" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="business">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Nama Bisnis</label>
            <input type="text" name="business_name" value="<?= e($merchant['business_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Tipe Bisnis</label>
            <select name="business_type" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                <?php foreach ([''=>'-- Pilih --','retail'=>'Retail','food_beverage'=>'F&B','services'=>'Jasa','digital'=>'Digital','education'=>'Edukasi','other'=>'Lainnya'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($merchant['business_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select></div>
    </div>
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
        <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"><?= e($merchant['description'] ?? '') ?></textarea></div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Telepon</label>
            <input type="text" name="phone" value="<?= e($merchant['phone'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Website</label>
            <input type="url" name="website" value="<?= e($merchant['website'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://..."></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Kota</label>
            <input type="text" name="city" value="<?= e($merchant['city'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Alamat</label>
            <input type="text" name="address" value="<?= e($merchant['address'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
    </div>
    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Simpan</button>
</form>

<?php elseif ($activeTab === 'bank'): ?>
<h3 class="text-lg font-semibold text-slate-800 mb-2">Informasi Rekening Bank</h3>
<p class="text-sm text-slate-500 mb-4">Rekening ini digunakan untuk pencairan dana (withdrawal).</p>
<form method="POST" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="bank">
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Nama Bank</label>
        <select name="bank_name" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
            <option value="">-- Pilih Bank --</option>
            <?php foreach ($bankList as $bank): $bank = trim($bank); if (empty($bank)) continue; ?>
            <option value="<?= e($bank) ?>" <?= ($merchant['bank_name'] ?? '') === $bank ? 'selected' : '' ?>><?= e($bank) ?></option>
            <?php endforeach; ?>
        </select></div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Nomor Rekening</label>
            <input type="text" name="bank_account_number" value="<?= e($merchant['bank_account_number'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="1234567890"></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Atas Nama</label>
            <input type="text" name="bank_account_name" value="<?= e($merchant['bank_account_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Sesuai buku tabungan"></div>
    </div>
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Cabang <span class="text-slate-400">(opsional)</span></label>
        <input type="text" name="bank_branch" value="<?= e($merchant['bank_branch'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Simpan Rekening</button>
</form>


<?php elseif ($activeTab === 'payment'): ?>
<h3 class="text-lg font-semibold text-slate-800 mb-2">Pengaturan Pembayaran</h3>
<p class="text-sm text-slate-500 mb-4">Konfigurasi default untuk pembayaran baru.</p>
<form method="POST" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="payment">
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Expiry (menit)</label>
        <input type="number" name="payment_expiry_minutes" value="<?= e($merchant['payment_expiry_minutes'] ?? 60) ?>" min="5" max="1440" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        <p class="text-xs text-slate-400 mt-1">Berapa menit sebelum payment link expired. Default: 60 menit.</p></div>
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Default Redirect URL</label>
        <input type="url" name="default_redirect_url" value="<?= e($merchant['default_redirect_url'] ?? $merchant['redirect_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/thank-you">
        <p class="text-xs text-slate-400 mt-1">Customer diarahkan ke sini setelah bayar (jika tidak diset per transaksi).</p></div>
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Pesan Thank You</label>
        <textarea name="thank_you_message" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Terima kasih atas pembayaran Anda..."><?= e($merchant['thank_you_message'] ?? '') ?></textarea>
        <p class="text-xs text-slate-400 mt-1">Ditampilkan di halaman sukses pembayaran.</p></div>
    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Simpan</button>
</form>

<?php elseif ($activeTab === 'notifications'): ?>
<h3 class="text-lg font-semibold text-slate-800 mb-2">Preferensi Notifikasi</h3>
<p class="text-sm text-slate-500 mb-4">Atur kapan dan bagaimana Anda ingin menerima notifikasi.</p>
<form method="POST" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="notifications">
    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
        <p class="text-sm font-medium text-slate-700">Email Notification</p>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="notif_email_payment" value="1" <?= ($merchant['notif_email_payment'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-emerald-600">
            <span class="text-sm text-slate-700">Kirim email saat pembayaran berhasil</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="notif_email_withdrawal" value="1" <?= ($merchant['notif_email_withdrawal'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-emerald-600">
            <span class="text-sm text-slate-700">Kirim email saat withdrawal diproses</span>
        </label>
    </div>
    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
        <p class="text-sm font-medium text-slate-700">WhatsApp Notification</p>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="notif_wa_payment" value="1" <?= ($merchant['notif_wa_payment'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-emerald-600">
            <span class="text-sm text-slate-700">Kirim WA saat pembayaran berhasil</span>
        </label>
        <div><label class="block text-xs text-slate-500 mb-1">Nomor WhatsApp</label>
            <input type="text" name="notif_wa_number" value="<?= e($merchant['notif_wa_number'] ?? $merchant['phone'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="08123456789">
        </div>
    </div>
    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Simpan</button>
</form>
<?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
