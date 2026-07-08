<?php
/**
 * Pengaturan - Consolidated Page
 * Tabs: Profil, Bisnis, Rekening Bank, Pembayaran, Notifikasi, Password
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Repositories/UserRepository.php');
require_once base_path('app/Services/AuditLogService.php');
require_once base_path('app/Services/ConfigChangeService.php');

$merchantRepo = new MerchantRepository();
$userRepo = new UserRepository();
$auditService = new AuditLogService();
$configService = new ConfigChangeService();
$merchantId = Auth::merchantId();
$merchant = $merchantRepo->find($merchantId);
$user = $userRepo->find(Auth::id());

$activeTab = $_GET['tab'] ?? 'profile';
$validTabs = ['profile', 'business', 'bank', 'apikey', 'payment', 'notifications', 'whatsapp', 'password'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'profile';

require_once base_path('app/Controllers/MerchantController.php');
$merchantController = new MerchantController();

if (is_post()) {
    Auth::verifyCsrf();
    $tab = $_POST['_tab'] ?? $activeTab;

    if ($tab === 'profile') {
        $updates = ['name' => sanitize($_POST['name'] ?? '')];
        $userRepo->update(Auth::id(), $updates + ['updated_at' => now()]);
        $merchantRepo->update($merchantId, [
            'owner_name' => sanitize($_POST['name'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'updated_at' => now(),
        ]);
        $_SESSION['user_name'] = sanitize($_POST['name'] ?? $user['name']);
        flash('success', 'Profil berhasil disimpan.');

    } elseif ($tab === 'business') {
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

    } elseif ($tab === 'apikey') {
        if (($_POST['_action'] ?? '') === 'regenerate') {
            $password = $_POST['confirm_password'] ?? '';
            if (!$configService->verifyPassword(Auth::id(), $password)) {
                flash('error', 'Password tidak valid. Verifikasi gagal.');
                redirect('/merchant/settings.php?tab=apikey');
            }
            $requireApproval = setting('require_approval_api_key', '1') === '1';
            if ($requireApproval) {
                $result = $configService->requestChange([
                    'merchant_id' => $merchantId,
                    'change_type' => 'api_key_regenerate',
                    'old_value' => mask_api_key($merchant['api_key'] ?? ''),
                    'new_value' => '(akan di-generate otomatis setelah approved)',
                    'reason' => sanitize($_POST['reason'] ?? 'Regenerate API Key'),
                    'requested_by' => Auth::id(),
                    'requested_by_role' => Auth::role(),
                ]);
            } else {
                $result = $merchantController->regenerateApiKey();
            }
            flash($result['success'] ? 'success' : 'error', $result['message']);
        }
        redirect('/merchant/settings.php?tab=apikey');

    } elseif ($tab === 'payment') {
        $merchantRepo->update($merchantId, [
            'payment_expiry_minutes' => (int)($_POST['payment_expiry_minutes'] ?? 60),
            'default_redirect_url' => sanitize($_POST['default_redirect_url'] ?? ''),
            'thank_you_message' => sanitize($_POST['thank_you_message'] ?? ''),
            'updated_at' => now(),
        ]);
        flash('success', 'Pengaturan pembayaran berhasil disimpan.');

    } elseif ($tab === 'notifications') {
        $merchantRepo->update($merchantId, [
            'notif_email_payment' => isset($_POST['notif_email_payment']) ? '1' : '0',
            'notif_email_withdrawal' => isset($_POST['notif_email_withdrawal']) ? '1' : '0',
            'notif_wa_payment' => isset($_POST['notif_wa_payment']) ? '1' : '0',
            'notif_wa_number' => sanitize($_POST['notif_wa_number'] ?? ''),
            'updated_at' => now(),
        ]);
        flash('success', 'Preferensi notifikasi berhasil disimpan.');

    } elseif ($tab === 'whatsapp') {
        // Handle test action vs save
        if (($_POST['wa_action'] ?? '') === 'test') {
            $result = $merchantController->testWa($_POST['test_phone'] ?? '');
            flash($result['success'] ? 'success' : 'error', $result['message']);
        } else {
            $result = $merchantController->saveWaConfig($_POST);
            flash($result['success'] ? 'success' : 'error', $result['message']);
        }

    } elseif ($tab === 'password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['password_confirm'] ?? '';

        if (empty($currentPw) || empty($newPw)) {
            flash('error', 'Semua field password harus diisi.');
        } elseif (strlen($newPw) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
        } elseif ($newPw !== $confirmPw) {
            flash('error', 'Konfirmasi password tidak cocok.');
        } elseif (!password_verify($currentPw, $user['password_hash'])) {
            flash('error', 'Password saat ini salah.');
        } else {
            $userRepo->update(Auth::id(), [
                'password_hash' => password_hash($newPw, PASSWORD_DEFAULT),
                'updated_at' => now(),
            ]);
            $auditService->log(Auth::id(), Auth::role(), $merchantId, 'password_changed', 'Password updated', []);
            flash('success', 'Password berhasil diubah.');
        }
    }

    redirect('/merchant/settings.php?tab=' . $tab);
}

// Reload data after POST
$merchant = $merchantRepo->find($merchantId);
$user = $userRepo->find(Auth::id());

// WhatsApp config for the active project
$waConfig = $merchantController->getWaConfig();

// Pending API key regenerate status (for apikey tab)
$pendingChanges = $configService->getPendingByMerchant($merchantId);
$hasPendingKey = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'api_key_regenerate'));

// Bank list
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
        <?php
        $tabs = [
            'profile' => 'Profil',
            'business' => 'Bisnis',
            'bank' => 'Rekening Bank',
            'apikey' => 'API Key',
            'payment' => 'Pembayaran',
            'notifications' => 'Notifikasi',
            'whatsapp' => 'Integrasi WhatsApp',
            'password' => 'Password',
        ];
        foreach ($tabs as $key => $label): ?>
        <a href="?tab=<?= $key ?>" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $activeTab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="max-w-2xl">
<div class="bg-white rounded-xl border border-slate-200 p-6">


<?php if ($activeTab === 'profile'): ?>
<!-- ============ TAB: PROFIL ============ -->
<h3 class="text-lg font-semibold text-slate-800 mb-4">Profil Akun</h3>
<form method="POST" action="?tab=profile" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="profile">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
        <input type="text" name="name" value="<?= e($user['name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">No. Telepon</label>
        <input type="text" name="phone" value="<?= e($merchant['phone'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
        <input type="email" value="<?= e($user['email'] ?? '') ?>" disabled class="w-full px-4 py-2.5 border border-slate-200 bg-slate-50 rounded-lg text-sm text-slate-500">
        <p class="text-xs text-slate-400 mt-1">Email tidak dapat diubah.</p>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
        <input type="text" value="<?= ucfirst(str_replace('_', ' ', $user['role'] ?? '')) ?>" disabled class="w-full px-4 py-2.5 border border-slate-200 bg-slate-50 rounded-lg text-sm text-slate-500">
    </div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan Profil</button>
</form>

<?php elseif ($activeTab === 'business'): ?>
<!-- ============ TAB: BISNIS ============ -->
<h3 class="text-lg font-semibold text-slate-800 mb-4">Informasi Bisnis</h3>
<form method="POST" action="?tab=business" class="space-y-4">
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
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
</form>

<?php elseif ($activeTab === 'bank'): ?>
<!-- ============ TAB: REKENING BANK ============ -->
<h3 class="text-lg font-semibold text-slate-800 mb-2">Informasi Rekening Bank</h3>
<p class="text-sm text-slate-500 mb-4">Rekening ini digunakan untuk pencairan dana (withdrawal).</p>
<form method="POST" action="?tab=bank" class="space-y-4">
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
            <input type="text" name="bank_account_number" value="<?= e($merchant['bank_account_number'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
        <div><label class="block text-sm font-medium text-slate-700 mb-1">Atas Nama</label>
            <input type="text" name="bank_account_name" value="<?= e($merchant['bank_account_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
    </div>
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Cabang <span class="text-slate-400">(opsional)</span></label>
        <input type="text" name="bank_branch" value="<?= e($merchant['bank_branch'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan Rekening</button>
</form>


<?php elseif ($activeTab === 'apikey'): ?>
<!-- ============ TAB: API KEY ============ -->
<h3 class="text-lg font-semibold text-slate-800 mb-2">API Key</h3>
<p class="text-sm text-slate-500 mb-4">API key untuk proyek aktif <strong><?= e($merchant['business_name'] ?? '') ?></strong>. Gunakan untuk autentikasi request API. <strong>Jangan bagikan ke pihak lain.</strong></p>

<div class="flex items-center gap-2 mb-4">
    <input type="text" id="apiKeyField" value="<?= e($merchant['api_key'] ?? '') ?>" readonly autocomplete="off" class="flex-1 px-4 py-3 bg-slate-900 text-emerald-400 font-mono text-sm rounded-lg border-0">
    <button type="button" onclick="copyApiKey()" class="px-4 py-3 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 whitespace-nowrap">Copy</button>
</div>
<p class="text-xs text-slate-400 mb-4">Masked: <?= mask_api_key($merchant['api_key'] ?? '') ?></p>

<div class="p-4 bg-slate-50 border border-slate-200 rounded-lg mb-6 text-sm space-y-2">
    <div><span class="text-xs text-slate-500">Base URL:</span><code class="block mt-1 px-3 py-2 bg-white border border-slate-200 rounded text-xs font-mono"><?= e(app_url('api/v1/')) ?></code></div>
    <div><span class="text-xs text-slate-500">Header:</span><code class="block mt-1 px-3 py-2 bg-white border border-slate-200 rounded text-xs font-mono">Authorization: Bearer YOUR_API_KEY</code></div>
    <a href="/merchant/integration.php" class="inline-block text-xs text-blue-600 font-medium hover:text-blue-700">Lihat Dokumentasi Lengkap &rarr;</a>
</div>

<div class="pt-4 border-t border-slate-200">
    <h4 class="text-sm font-semibold text-slate-800 mb-3">Regenerate API Key</h4>
    <?php if ($hasPendingKey): ?>
    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
        <p class="text-xs text-amber-700 font-medium">Regenerasi API key sedang menunggu persetujuan admin.</p>
    </div>
    <?php else: ?>
    <form method="POST" action="?tab=apikey" class="space-y-3 max-w-md">
        <?= csrf_field() ?>
        <input type="hidden" name="_tab" value="apikey">
        <input type="hidden" name="_action" value="regenerate">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Alasan <span class="text-slate-400">(opsional)</span></label>
            <input type="text" name="reason" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" placeholder="Compromised, rotasi rutin, dll.">
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
            <input type="password" name="confirm_password" required autocomplete="off" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" placeholder="Masukkan password Anda">
        </div>
        <button type="submit" onclick="return confirm('API key lama tidak berlaku setelah disetujui. Lanjutkan?')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 border border-red-200">
            Ajukan Regenerate API Key
        </button>
    </form>
    <?php endif; ?>
</div>

<script>
function copyApiKey() {
    var f = document.getElementById('apiKeyField');
    navigator.clipboard.writeText(f.value).then(function(){ if (typeof showToast === 'function') showToast('API key disalin!'); });
}
</script>

<?php elseif ($activeTab === 'payment'): ?>
<!-- ============ TAB: PEMBAYARAN ============ -->
<h3 class="text-lg font-semibold text-slate-800 mb-2">Pengaturan Pembayaran</h3>
<p class="text-sm text-slate-500 mb-4">Konfigurasi default untuk pembayaran baru.</p>
<form method="POST" action="?tab=payment" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="payment">
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Expiry (menit)</label>
        <input type="number" name="payment_expiry_minutes" value="<?= e($merchant['payment_expiry_minutes'] ?? 60) ?>" min="5" max="1440" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        <p class="text-xs text-slate-400 mt-1">Berapa menit sebelum payment link expired. Default: 60.</p></div>
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Default Redirect URL</label>
        <input type="url" name="default_redirect_url" value="<?= e($merchant['default_redirect_url'] ?? $merchant['redirect_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/thank-you">
        <p class="text-xs text-slate-400 mt-1">Customer diarahkan ke sini setelah bayar.</p></div>
    <div><label class="block text-sm font-medium text-slate-700 mb-1">Pesan Thank You</label>
        <textarea name="thank_you_message" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Terima kasih..."><?= e($merchant['thank_you_message'] ?? '') ?></textarea></div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
</form>

<?php elseif ($activeTab === 'notifications'): ?>
<!-- ============ TAB: NOTIFIKASI ============ -->
<h3 class="text-lg font-semibold text-slate-800 mb-2">Preferensi Notifikasi</h3>
<p class="text-sm text-slate-500 mb-4">Atur kapan dan bagaimana Anda menerima notifikasi.</p>
<form method="POST" action="?tab=notifications" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="notifications">
    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
        <p class="text-sm font-medium text-slate-700">Email</p>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="notif_email_payment" value="1" <?= ($merchant['notif_email_payment'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Email saat pembayaran berhasil</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="notif_email_withdrawal" value="1" <?= ($merchant['notif_email_withdrawal'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Email saat withdrawal diproses</span>
        </label>
    </div>
    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
        <p class="text-sm font-medium text-slate-700">WhatsApp</p>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="notif_wa_payment" value="1" <?= ($merchant['notif_wa_payment'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">WA saat pembayaran berhasil</span>
        </label>
        <div><label class="block text-xs text-slate-500 mb-1">Nomor WhatsApp</label>
            <input type="text" name="notif_wa_number" value="<?= e($merchant['notif_wa_number'] ?? $merchant['phone'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="08123456789">
        </div>
    </div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
</form>

<?php elseif ($activeTab === 'whatsapp'): ?>
<!-- ============ TAB: INTEGRASI WHATSAPP ============ -->
<h3 class="text-lg font-semibold text-slate-800 mb-2">Integrasi WhatsApp</h3>
<p class="text-sm text-slate-500 mb-4">Konfigurasi API WhatsApp untuk proyek <strong><?= e($merchant['business_name'] ?? '') ?></strong>. Setiap proyek punya integrasi terpisah.</p>

<?php if (!empty($waConfig['total_sent'])): ?>
<div class="mb-4 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600">
    Total pesan terkirim: <strong><?= number_format((int)$waConfig['total_sent']) ?></strong>
    <?php if (!empty($waConfig['last_sent_at'])): ?> &middot; Terakhir: <?= format_date($waConfig['last_sent_at'], 'd/m/Y H:i') ?><?php endif; ?>
    <?php if (!empty($waConfig['last_error'])): ?><br><span class="text-red-500">Error terakhir: <?= e($waConfig['last_error']) ?></span><?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" action="?tab=whatsapp" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="whatsapp">

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Provider</label>
        <select name="provider" id="waProvider" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm" onchange="updateProviderHint()">
            <?php
            $providers = ['fonnte' => 'Fonnte', 'wablas' => 'Wablas', 'zenziva' => 'Zenziva', 'custom' => 'Custom API'];
            foreach ($providers as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($waConfig['provider'] ?? 'fonnte') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">API URL</label>
        <input type="url" name="api_url" value="<?= e($waConfig['api_url'] ?? 'https://api.fonnte.com/send') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://api.fonnte.com/send">
        <p class="text-xs text-slate-400 mt-1" id="providerHint">Endpoint API dari provider WA Anda.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">API Key / Token</label>
            <input type="text" name="api_key" value="<?= e($waConfig['api_key'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="Token dari provider">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">API Secret <span class="text-slate-400">(Zenziva/opsional)</span></label>
            <input type="text" name="api_secret" value="<?= e($waConfig['api_secret'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="Passkey (jika perlu)">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Pengirim <span class="text-slate-400">(opsional)</span></label>
            <input type="text" name="sender_number" value="<?= e($waConfig['sender_number'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="628xxx">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Admin Notif <span class="text-slate-400">(opsional)</span></label>
            <input type="text" name="notify_admin_number" value="<?= e($waConfig['notify_admin_number'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="628xxx (WA masuk saat ada bayaran)">
        </div>
    </div>

    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" <?= (int)($waConfig['is_active'] ?? 1) === 1 ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Aktifkan integrasi WhatsApp</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="notify_on_payment" value="1" <?= (int)($waConfig['notify_on_payment'] ?? 1) === 1 ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Kirim WA ke customer saat pembayaran berhasil</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="notify_on_withdrawal" value="1" <?= (int)($waConfig['notify_on_withdrawal'] ?? 0) === 1 ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Kirim WA saat withdrawal diproses</span>
        </label>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Template Pesan Pembayaran</label>
        <textarea name="message_template_payment" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"><?= e($waConfig['message_template_payment'] ?? 'Halo {customer}! Pembayaran order *{order_id}* sebesar *{amount}* telah *{status}*. Terima kasih telah bertransaksi di {project}.') ?></textarea>
        <p class="text-xs text-slate-400 mt-1">Variabel: <code>{customer}</code>, <code>{order_id}</code>, <code>{amount}</code>, <code>{net}</code>, <code>{status}</code>, <code>{project}</code></p>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan Konfigurasi</button>
    </div>
</form>

<!-- Test WA -->
<div class="mt-6 pt-6 border-t border-slate-200">
    <h4 class="text-sm font-semibold text-slate-800 mb-2">Test Kirim WhatsApp</h4>
    <form method="POST" action="?tab=whatsapp" class="flex gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="_tab" value="whatsapp">
        <input type="hidden" name="wa_action" value="test">
        <input type="text" name="test_phone" class="flex-1 px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Nomor tujuan test, cth: 08123456789" required>
        <button type="submit" class="px-4 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 whitespace-nowrap">Test Kirim</button>
    </form>
    <p class="text-xs text-slate-400 mt-2">Simpan konfigurasi terlebih dahulu sebelum melakukan test.</p>
</div>

<script>
function updateProviderHint() {
    var p = document.getElementById('waProvider').value;
    var hints = {
        'fonnte': 'Endpoint: https://api.fonnte.com/send — Token dari dashboard Fonnte.',
        'wablas': 'Endpoint: https://domain-wablas.com — Token dari dashboard Wablas.',
        'zenziva': 'Endpoint Zenziva — isi Userkey di API Key & Passkey di API Secret.',
        'custom': 'Endpoint custom Anda. Body JSON: {to, message, sender}, header Bearer API Key.'
    };
    document.getElementById('providerHint').textContent = hints[p] || '';
}
updateProviderHint();
</script>

<?php elseif ($activeTab === 'password'): ?>
<!-- ============ TAB: PASSWORD ============ -->
<h3 class="text-lg font-semibold text-slate-800 mb-2">Ubah Password</h3>
<p class="text-sm text-slate-500 mb-4">Pastikan password baru minimal 8 karakter dan berbeda dari yang lama.</p>
<form method="POST" action="?tab=password" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="password">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Password Saat Ini</label>
        <input type="password" name="current_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Masukkan password saat ini">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Password Baru</label>
        <input type="password" name="new_password" required minlength="8" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Minimal 8 karakter">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password Baru</label>
        <input type="password" name="password_confirm" required minlength="8" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Ulangi password baru">
    </div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Ubah Password</button>
</form>

<?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
