<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireRole(['super_admin', 'admin']);

require_once base_path('app/Repositories/SettingRepository.php');
require_once base_path('app/Services/AuditLogService.php');
$settingRepo = new SettingRepository();
$auditService = new AuditLogService();

// Handle form submission
if (is_post()) {
    Auth::verifyCsrf();
    $tab = $_POST['_tab'] ?? 'general';
    
    if ($tab === 'general') {
        $settingRepo->set('app_name', sanitize($_POST['app_name'] ?? 'Clipku Pay'));
        $settingRepo->set('app_url', sanitize($_POST['app_url'] ?? ''));
        $settingRepo->set('app_description', sanitize($_POST['app_description'] ?? ''));
        $settingRepo->set('app_logo_url', sanitize($_POST['app_logo_url'] ?? ''));
        $settingRepo->set('timezone', sanitize($_POST['timezone'] ?? 'Asia/Jakarta'));
        $settingRepo->set('per_page', (int)($_POST['per_page'] ?? 20));
        $settingRepo->set('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
    } elseif ($tab === 'gateway') {
        // AldiQRIS
        $settingRepo->set('aldiqris_base_url', sanitize($_POST['aldiqris_base_url'] ?? ''));
        $settingRepo->set('aldiqris_api_key', $_POST['aldiqris_api_key'] ?? '');
        $settingRepo->set('aldiqris_timeout', (int)($_POST['aldiqris_timeout'] ?? 30));
        $settingRepo->set('aldiqris_ssl_verify', isset($_POST['aldiqris_ssl_verify']) ? '1' : '0');
        $settingRepo->set('aldiqris_endpoint', sanitize($_POST['aldiqris_endpoint'] ?? '/api/trx'));
        
        // Midtrans
        $settingRepo->set('channel_midtrans_enabled', isset($_POST['channel_midtrans_enabled']) ? '1' : '0');
        $settingRepo->set('midtrans_server_key', $_POST['midtrans_server_key'] ?? '');
        $settingRepo->set('midtrans_client_key', $_POST['midtrans_client_key'] ?? '');
        $settingRepo->set('midtrans_is_production', $_POST['midtrans_is_production'] ?? '0');
        $settingRepo->set('midtrans_expiry_minutes', (int)($_POST['midtrans_expiry_minutes'] ?? 60));
        
        // Midtrans payment methods
        $midtransMethods = ['credit_card','bca_va','bni_va','bri_va','mandiri_bill','permata_va','cimb_va','gopay','shopeepay','qris','alfamart','indomaret','akulaku','kredivo'];
        foreach ($midtransMethods as $method) {
            $settingRepo->set("midtrans_{$method}_enabled", isset($_POST["midtrans_{$method}_enabled"]) ? '1' : '0');
        }
    } elseif ($tab === 'fees') {
        $settingRepo->set('default_fee_type', $_POST['default_fee_type'] ?? 'percentage');
        $settingRepo->set('default_fee_value', (float)($_POST['default_fee_value'] ?? 0.7));
        $settingRepo->set('default_fee_flat', (float)($_POST['default_fee_flat'] ?? 0));
        $settingRepo->set('min_transaction_amount', (int)($_POST['min_transaction_amount'] ?? 1000));
        $settingRepo->set('max_transaction_amount', (int)($_POST['max_transaction_amount'] ?? 50000000));

        // Per-channel service fee ("biaya admin"): QRIS / VA / E-Wallet (each separate)
        foreach (['qris', 'va', 'ewallet'] as $grp) {
            $ctype = $_POST["fee_{$grp}_type"] ?? '';
            if (!in_array($ctype, ['', 'percentage', 'flat', 'hybrid'], true)) $ctype = '';
            $settingRepo->set("fee_{$grp}_type", $ctype);
            $settingRepo->set("fee_{$grp}_value", (float)($_POST["fee_{$grp}_value"] ?? 0));
            $settingRepo->set("fee_{$grp}_flat", (float)($_POST["fee_{$grp}_flat"] ?? 0));
        }

        // Per-method Midtrans PROVIDER fee (biaya Midtrans saja; biaya layanan diatur per channel)
        $mtFeeMethods = ['bca_va','bni_va','bri_va','permata_va','cimb_va','mandiri_bill','gopay','shopeepay','qris'];
        foreach ($mtFeeMethods as $mm) {
            $settingRepo->set("mtfee_{$mm}_prov_flat", (float)($_POST["mtfee_{$mm}_prov_flat"] ?? 0));
            $settingRepo->set("mtfee_{$mm}_prov_pct",  (float)($_POST["mtfee_{$mm}_prov_pct"] ?? 0));
        }
    } elseif ($tab === 'withdrawal') {
        $settingRepo->set('min_withdrawal', (int)($_POST['min_withdrawal'] ?? 10000));
        $settingRepo->set('max_withdrawal', (int)($_POST['max_withdrawal'] ?? 100000000));
        $settingRepo->set('withdrawal_fee_type', $_POST['withdrawal_fee_type'] ?? 'flat');
        $settingRepo->set('withdrawal_fee_value', (float)($_POST['withdrawal_fee_value'] ?? 0));
        $settingRepo->set('auto_approve_withdrawal', isset($_POST['auto_approve_withdrawal']) ? '1' : '0');
        $settingRepo->set('withdrawal_schedule', sanitize($_POST['withdrawal_schedule'] ?? 'manual'));

        // Save bank list
        $banks = array_filter(array_map('trim', explode("\n", $_POST['bank_list'] ?? '')));
        $settingRepo->set('bank_list', $banks);
    } elseif ($tab === 'webhook') {
        $settingRepo->set('global_webhook_url', sanitize($_POST['global_webhook_url'] ?? ''));
        $settingRepo->set('webhook_signature_header', sanitize($_POST['webhook_signature_header'] ?? 'X-Signature'));
        $settingRepo->set('webhook_hash_algo', sanitize($_POST['webhook_hash_algo'] ?? 'sha256'));
        $settingRepo->set('webhook_max_payload_size', (int)($_POST['webhook_max_payload_size'] ?? 65536));
        $settingRepo->set('webhook_retry_enabled', isset($_POST['webhook_retry_enabled']) ? '1' : '0');
        $settingRepo->set('webhook_retry_count', (int)($_POST['webhook_retry_count'] ?? 3));
    } elseif ($tab === 'settlement') {
        $settingRepo->set('auto_settle', isset($_POST['auto_settle']) ? '1' : '0');
        $settingRepo->set('settle_delay_hours', (int)($_POST['settle_delay_hours'] ?? 24));
        $settingRepo->set('min_settlement_amount', (int)($_POST['min_settlement_amount'] ?? 50000));
        $settingRepo->set('settlement_schedule', sanitize($_POST['settlement_schedule'] ?? 'manual'));
    } elseif ($tab === 'security') {
        $settingRepo->set('login_max_attempts', (int)($_POST['login_max_attempts'] ?? 5));
        $settingRepo->set('login_lockout_time', (int)($_POST['login_lockout_time'] ?? 900));
        $settingRepo->set('session_lifetime', (int)($_POST['session_lifetime'] ?? 7200));
        $settingRepo->set('password_min_length', (int)($_POST['password_min_length'] ?? 8));
        $settingRepo->set('force_https', isset($_POST['force_https']) ? '1' : '0');
        $settingRepo->set('allowed_ips', sanitize($_POST['allowed_ips'] ?? ''));
        
        // Cloudflare Turnstile
        $settingRepo->set('turnstile_enabled', isset($_POST['turnstile_enabled']) ? '1' : '0');
        $settingRepo->set('turnstile_site_key', sanitize($_POST['turnstile_site_key'] ?? ''));
        $settingRepo->set('turnstile_secret_key', $_POST['turnstile_secret_key'] ?? '');
        
        // Google OAuth
        $settingRepo->set('google_login_enabled', isset($_POST['google_login_enabled']) ? '1' : '0');
        $settingRepo->set('google_client_id', sanitize($_POST['google_client_id'] ?? ''));
        $settingRepo->set('google_client_secret', $_POST['google_client_secret'] ?? '');
        $settingRepo->set('google_redirect_uri', sanitize($_POST['google_redirect_uri'] ?? ''));
    } elseif ($tab === 'notifications') {
        // SMTP Settings
        $settingRepo->set('smtp_host', sanitize($_POST['smtp_host'] ?? ''));
        $settingRepo->set('smtp_port', (int)($_POST['smtp_port'] ?? 587));
        $settingRepo->set('smtp_username', sanitize($_POST['smtp_username'] ?? ''));
        $settingRepo->set('smtp_password', $_POST['smtp_password'] ?? '');
        $settingRepo->set('smtp_encryption', sanitize($_POST['smtp_encryption'] ?? 'tls'));
        
        $settingRepo->set('notif_email_enabled', isset($_POST['notif_email_enabled']) ? '1' : '0');
        $settingRepo->set('notif_email_from', sanitize($_POST['notif_email_from'] ?? ''));
        $settingRepo->set('notif_wa_enabled', isset($_POST['notif_wa_enabled']) ? '1' : '0');
        $settingRepo->set('notif_wa_api_url', sanitize($_POST['notif_wa_api_url'] ?? ''));
        $settingRepo->set('notif_wa_api_key', $_POST['notif_wa_api_key'] ?? '');
        $settingRepo->set('notif_on_payment', isset($_POST['notif_on_payment']) ? '1' : '0');
        $settingRepo->set('notif_on_withdrawal', isset($_POST['notif_on_withdrawal']) ? '1' : '0');
    }

    $auditService->log(Auth::id(), Auth::role(), null, 'settings_changed', "Settings tab [{$tab}] updated", ['tab' => $tab]);
    flash('success', 'Pengaturan berhasil disimpan.');
    redirect('/admin/settings.php?tab=' . $tab);
}

$s = $settingRepo->getAllSettings();
$activeTab = $_GET['tab'] ?? 'general';
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>


<!-- Tab Navigation -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <?php
        $tabs = [
            'general' => 'Umum',
            'gateway' => 'Gateway API',
            'fees' => 'Fee & Transaksi',
            'withdrawal' => 'Withdrawal',
            'webhook' => 'Webhook',
            'settlement' => 'Settlement',
            'security' => 'Keamanan',
            'notifications' => 'Notifikasi',
        ];
        foreach ($tabs as $tabKey => $tabLabel): ?>
        <a href="?tab=<?= $tabKey ?>" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $activeTab === $tabKey ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
            <?= $tabLabel ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="max-w-3xl">
<div class="bg-white rounded-xl border border-slate-200 p-6">


<?php if ($activeTab === 'general'): ?>
<!-- GENERAL SETTINGS -->
<h3 class="text-lg font-semibold text-slate-800 mb-6">Pengaturan Umum</h3>
<form method="POST" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="general">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nama Aplikasi</label>
            <input type="text" name="app_name" value="<?= e($s['app_name'] ?? 'Clipku Pay') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">App URL</label>
            <input type="url" name="app_url" value="<?= e($s['app_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi Aplikasi</label>
        <textarea name="app_description" rows="2" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"><?= e($s['app_description'] ?? '') ?></textarea>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Logo URL</label>
            <input type="url" name="app_logo_url" value="<?= e($s['app_logo_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://...logo.png">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Timezone</label>
            <select name="timezone" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                <?php foreach (['Asia/Jakarta','Asia/Makassar','Asia/Jayapura','UTC'] as $tz): ?>
                <option value="<?= $tz ?>" <?= ($s['timezone'] ?? 'Asia/Jakarta') === $tz ? 'selected' : '' ?>><?= $tz ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Item Per Halaman</label>
            <input type="number" name="per_page" value="<?= e($s['per_page'] ?? 20) ?>" min="5" max="100" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="maintenance_mode" value="1" <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
                <span class="text-sm text-slate-700">Mode Maintenance</span>
            </label>
        </div>
    </div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
</form>

<?php elseif ($activeTab === 'gateway'): ?>
<!-- GATEWAY API SETTINGS -->
<h3 class="text-lg font-semibold text-slate-800 mb-2">Payment Gateway</h3>
<p class="text-sm text-slate-500 mb-6">Konfigurasi koneksi ke provider pembayaran.</p>
<form method="POST" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="gateway">

    <!-- AldiQRIS Section -->
    <div class="p-5 bg-blue-50 border border-blue-200 rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-blue-900">Provider QRIS</h4>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Primary</span>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Base URL</label>
                <input type="url" name="aldiqris_base_url" value="<?= e($s['aldiqris_base_url'] ?? 'https://aldiqris.pages.dev') ?>" class="w-full px-3 py-2 border border-blue-200 rounded-lg text-sm bg-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">API Key</label>
                <input type="password" name="aldiqris_api_key" value="<?= e($s['aldiqris_api_key'] ?? '') ?>" class="w-full px-3 py-2 border border-blue-200 rounded-lg text-sm font-mono bg-white" placeholder="API key provider QRIS">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Endpoint</label>
                    <input type="text" name="aldiqris_endpoint" value="<?= e($s['aldiqris_endpoint'] ?? '/api/trx') ?>" class="w-full px-3 py-2 border border-blue-200 rounded-lg text-sm font-mono bg-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Timeout (s)</label>
                    <input type="number" name="aldiqris_timeout" value="<?= e($s['aldiqris_timeout'] ?? 30) ?>" min="5" max="120" class="w-full px-3 py-2 border border-blue-200 rounded-lg text-sm bg-white">
                </div>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="aldiqris_ssl_verify" value="1" <?= ($s['aldiqris_ssl_verify'] ?? '1') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
                <span class="text-xs text-slate-700">SSL Verification (aktifkan di production)</span>
            </label>
        </div>
    </div>

    <!-- Midtrans Section -->
    <div class="p-5 bg-indigo-50 border border-indigo-200 rounded-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <h4 class="text-sm font-bold text-indigo-900">Provider VA & E-Wallet</h4>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="channel_midtrans_enabled" value="1" <?= ($s['channel_midtrans_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-indigo-600">
                <span class="text-xs font-medium text-indigo-700">Aktif</span>
            </label>
        </div>
        <div class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Server Key</label>
                    <input type="password" name="midtrans_server_key" value="<?= e($s['midtrans_server_key'] ?? '') ?>" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm font-mono bg-white" placeholder="SB-Mid-server-xxx...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Client Key</label>
                    <input type="password" name="midtrans_client_key" value="<?= e($s['midtrans_client_key'] ?? '') ?>" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm font-mono bg-white" placeholder="SB-Mid-client-xxx...">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Environment</label>
                    <select name="midtrans_is_production" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm bg-white">
                        <option value="0" <?= ($s['midtrans_is_production'] ?? '0') === '0' ? 'selected' : '' ?>>Sandbox (Testing)</option>
                        <option value="1" <?= ($s['midtrans_is_production'] ?? '0') === '1' ? 'selected' : '' ?>>Production (Live)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">Expiry (menit)</label>
                    <input type="number" name="midtrans_expiry_minutes" value="<?= e($s['midtrans_expiry_minutes'] ?? 60) ?>" min="5" max="1440" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm bg-white">
                </div>
            </div>

            <!-- Payment Methods Toggle -->
            <div class="pt-3 border-t border-indigo-100">
                <p class="text-xs font-medium text-slate-700 mb-2">Metode Pembayaran Aktif:</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <?php
                    $midtransMethods = [
                        'credit_card' => 'Kartu Kredit/Debit',
                        'bca_va' => 'VA BCA',
                        'bni_va' => 'VA BNI',
                        'bri_va' => 'VA BRI',
                        'mandiri_bill' => 'Mandiri Bill',
                        'permata_va' => 'VA Permata',
                        'cimb_va' => 'VA CIMB',
                        'gopay' => 'GoPay',
                        'shopeepay' => 'ShopeePay',
                        'qris' => 'QRIS',
                        'alfamart' => 'Alfamart',
                        'indomaret' => 'Indomaret',
                        'akulaku' => 'Akulaku',
                        'kredivo' => 'Kredivo',
                    ];
                    foreach ($midtransMethods as $code => $label): ?>
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" name="midtrans_<?= $code ?>_enabled" value="1" <?= ($s["midtrans_{$code}_enabled"] ?? '1') === '1' ? 'checked' : '' ?> class="w-3.5 h-3.5 rounded border-slate-300 text-indigo-600">
                        <span class="text-xs text-slate-700"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="p-3 bg-indigo-100/50 rounded-lg">
                <p class="text-xs text-indigo-700"><strong>Webhook:</strong> Set notification URL di dashboard provider ke: <code class="font-mono bg-white px-1 rounded"><?= e(app_url('webhook.php')) ?></code></p>
            </div>
        </div>
    </div>

    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan Semua Gateway</button>
</form>


<?php elseif ($activeTab === 'fees'): ?>
<!-- FEE & TRANSACTION SETTINGS — Simplified -->
<h3 class="text-base font-semibold text-slate-800 mb-1">Fee & Transaksi</h3>
<p class="text-xs text-slate-500 mb-5">Atur biaya layanan dan limit transaksi.</p>
<form method="POST" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="fees">

    <!-- 1. Biaya Layanan per Channel -->
    <div>
        <p class="text-sm font-semibold text-slate-700 mb-2">Biaya Layanan per Channel</p>
        <p class="text-[11px] text-slate-500 mb-3">Fee dipotong dari merchant. Pilih "—" untuk gunakan Default.</p>
        <?php
        $feeChannels = ['qris' => ['QRIS','AldiQRIS'], 'va' => ['Virtual Account','Midtrans VA'], 'ewallet' => ['E-Wallet','GoPay/ShopeePay']];
        foreach ($feeChannels as $grp => [$label, $desc]):
            $ctype = $s["fee_{$grp}_type"] ?? '';
            $cval  = $s["fee_{$grp}_value"] ?? '';
            $cflat = $s["fee_{$grp}_flat"] ?? '';
        ?>
        <div class="p-3 mb-2 bg-slate-50 rounded-lg border border-slate-100">
            <p class="text-xs font-medium text-slate-800 mb-0.5"><?= $label ?> <span class="text-slate-400 font-normal">(<?= $desc ?>)</span></p>
            <div class="grid grid-cols-3 gap-2 mt-2">
                <select name="fee_<?= $grp ?>_type" class="px-2 py-1.5 border border-slate-300 rounded text-[11px]">
                    <option value="" <?= $ctype === '' ? 'selected' : '' ?>>— Default</option>
                    <option value="percentage" <?= $ctype === 'percentage' ? 'selected' : '' ?>>%</option>
                    <option value="flat" <?= $ctype === 'flat' ? 'selected' : '' ?>>Rp</option>
                    <option value="hybrid" <?= $ctype === 'hybrid' ? 'selected' : '' ?>>% + Rp</option>
                </select>
                <input type="number" step="0.01" min="0" name="fee_<?= $grp ?>_value" value="<?= e($cval) ?>" placeholder="Nilai" class="px-2 py-1.5 border border-slate-300 rounded text-[11px]">
                <input type="number" step="1" min="0" name="fee_<?= $grp ?>_flat" value="<?= e($cflat) ?>" placeholder="Flat" class="px-2 py-1.5 border border-slate-300 rounded text-[11px]">
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 2. Limit -->
    <div class="grid grid-cols-2 gap-3">
        <div><label class="text-xs text-slate-600 font-medium block mb-1">Min (Rp)</label>
            <input type="number" name="min_transaction_amount" value="<?= e($s['min_transaction_amount'] ?? 1000) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
        <div><label class="text-xs text-slate-600 font-medium block mb-1">Max (Rp)</label>
            <input type="number" name="max_transaction_amount" value="<?= e($s['max_transaction_amount'] ?? 50000000) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
    </div>

    <!-- 3. Default Fee — collapsed -->
    <details class="border border-slate-200 rounded-lg"><summary class="px-3 py-2.5 cursor-pointer text-xs font-medium text-slate-600 hover:bg-slate-50">Default Fee (Fallback) ▾</summary>
        <div class="px-3 pb-3 pt-2 border-t border-slate-100">
            <p class="text-[11px] text-slate-400 mb-2">Dipakai bila channel di atas = "—".</p>
            <div class="grid grid-cols-3 gap-2">
                <select name="default_fee_type" class="px-2 py-1.5 border border-slate-300 rounded text-[11px]">
                    <option value="percentage" <?= ($s['default_fee_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>%</option>
                    <option value="flat" <?= ($s['default_fee_type'] ?? '') === 'flat' ? 'selected' : '' ?>>Rp</option>
                    <option value="hybrid" <?= ($s['default_fee_type'] ?? '') === 'hybrid' ? 'selected' : '' ?>>% + Rp</option>
                </select>
                <input type="number" name="default_fee_value" step="0.01" value="<?= e($s['default_fee_value'] ?? 0.7) ?>" class="px-2 py-1.5 border border-slate-300 rounded text-[11px]" placeholder="Value">
                <input type="number" name="default_fee_flat" value="<?= e($s['default_fee_flat'] ?? 0) ?>" class="px-2 py-1.5 border border-slate-300 rounded text-[11px]" placeholder="Flat">
            </div>
        </div>
    </details>

    <!-- 4. Provider Midtrans — collapsed -->
    <details class="border border-slate-200 rounded-lg"><summary class="px-3 py-2.5 cursor-pointer text-xs font-medium text-slate-600 hover:bg-slate-50">Biaya Provider Midtrans (Lanjutan) ▾</summary>
        <div class="px-3 pb-3 pt-2 border-t border-slate-100">
            <p class="text-[11px] text-slate-400 mb-2">Total = Biaya Layanan + biaya ini. Kosongkan = 0.</p>
            <div class="space-y-1.5">
                <?php
                $mtFeeRows = ['bca_va'=>'BCA VA','bni_va'=>'BNI VA','bri_va'=>'BRI VA','permata_va'=>'Permata','cimb_va'=>'CIMB','mandiri_bill'=>'Mandiri','gopay'=>'GoPay','shopeepay'=>'ShopeePay','qris'=>'QRIS(MT)'];
                foreach ($mtFeeRows as $mm => $label): ?>
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] text-slate-600 w-20 flex-shrink-0"><?= $label ?></span>
                    <input type="number" step="1" min="0" name="mtfee_<?= $mm ?>_prov_flat" value="<?= e($s["mtfee_{$mm}_prov_flat"] ?? '') ?>" placeholder="Rp" class="flex-1 px-2 py-1 border border-slate-300 rounded text-[11px]">
                    <input type="number" step="0.01" min="0" name="mtfee_<?= $mm ?>_prov_pct" value="<?= e($s["mtfee_{$mm}_prov_pct"] ?? '') ?>" placeholder="%" class="w-14 px-2 py-1 border border-slate-300 rounded text-[11px]">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </details>

    <!-- 5. Simulator -->
    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
        <p class="text-xs font-semibold text-emerald-800 mb-2">Simulasi Cepat</p>
        <div class="flex gap-2 mb-2">
            <input type="number" id="feeSimAmount" value="100000" class="flex-1 px-3 py-2 border border-emerald-200 rounded-lg text-sm bg-white" placeholder="Nominal">
            <button type="button" onclick="runFeeSim()" class="px-3 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold active:bg-emerald-700">Hitung</button>
        </div>
        <div id="feeSimResult" class="text-[11px] bg-white rounded border border-emerald-100 divide-y divide-emerald-50 hidden"></div>
    </div>
    <script>
    function runFeeSim(){var a=parseInt(document.getElementById('feeSimAmount').value||'0',10);if(isNaN(a)||a<0)a=0;function n(nm){var el=document.querySelector('[name="'+nm+'"]');return el?(parseFloat(el.value)||0):0;}function v(nm){var el=document.querySelector('[name="'+nm+'"]');return el?el.value:'';}function rp(x){return'Rp '+Math.round(x).toLocaleString('id-ID');}function calc(t,val,fl,amt){if(t==='flat')return Math.round(val);if(t==='percentage')return Math.round(amt*val/100);if(t==='hybrid')return Math.round(amt*val/100)+fl;return 0;}var dt=v('default_fee_type'),dv=n('default_fee_value'),df=n('default_fee_flat');function ch(g,amt){var t=v('fee_'+g+'_type');if(t==='')return calc(dt,dv,df,amt);return calc(t,n('fee_'+g+'_value'),n('fee_'+g+'_flat'),amt);}var r='<div class="flex justify-between px-2 py-1"><span>QRIS</span><b>'+rp(ch('qris',a))+'</b></div>';var ms={'bca_va':['BCA VA','va'],'bni_va':['BNI VA','va'],'bri_va':['BRI VA','va'],'gopay':['GoPay','ewallet'],'shopeepay':['ShopeePay','ewallet'],'qris':['QRIS(MT)','qris']};for(var m in ms){var g=ms[m][1],pf=ch(g,a),pv=Math.round(a*n('mtfee_'+m+'_prov_pct')/100)+n('mtfee_'+m+'_prov_flat');r+='<div class="flex justify-between px-2 py-1"><span>'+ms[m][0]+'</span><b>'+rp(pf+pv)+'</b></div>';}var b=document.getElementById('feeSimResult');b.innerHTML=r;b.classList.remove('hidden');}
    </script>
    <button type="submit" class="w-full py-2.5 bg-slate-900 text-white rounded-lg text-sm font-medium active:bg-slate-800">Simpan</button>
</form>

<?php elseif ($activeTab === 'withdrawal'): ?>
<!-- WITHDRAWAL SETTINGS -->
<h3 class="text-lg font-semibold text-slate-800 mb-6">Pengaturan Withdrawal</h3>
<form method="POST" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="withdrawal">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Minimum Withdrawal (Rp)</label>
            <input type="number" name="min_withdrawal" value="<?= e($s['min_withdrawal'] ?? 10000) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Maksimum Withdrawal (Rp)</label>
            <input type="number" name="max_withdrawal" value="<?= e($s['max_withdrawal'] ?? 100000000) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fee Withdrawal (tipe)</label>
            <select name="withdrawal_fee_type" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                <option value="none" <?= ($s['withdrawal_fee_type'] ?? 'none') === 'none' ? 'selected' : '' ?>>Tanpa Fee</option>
                <option value="flat" <?= ($s['withdrawal_fee_type'] ?? '') === 'flat' ? 'selected' : '' ?>>Flat (Rp)</option>
                <option value="percentage" <?= ($s['withdrawal_fee_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fee Value</label>
            <input type="number" name="withdrawal_fee_value" step="0.01" value="<?= e($s['withdrawal_fee_value'] ?? 0) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Jadwal Pencairan</label>
            <select name="withdrawal_schedule" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                <option value="manual" <?= ($s['withdrawal_schedule'] ?? 'manual') === 'manual' ? 'selected' : '' ?>>Manual (Admin Approve)</option>
                <option value="daily" <?= ($s['withdrawal_schedule'] ?? '') === 'daily' ? 'selected' : '' ?>>Harian</option>
                <option value="weekly" <?= ($s['withdrawal_schedule'] ?? '') === 'weekly' ? 'selected' : '' ?>>Mingguan</option>
            </select>
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="auto_approve_withdrawal" value="1" <?= ($s['auto_approve_withdrawal'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
                <span class="text-sm text-slate-700">Auto-approve withdrawal</span>
            </label>
        </div>
    </div>
    <div class="border-t border-slate-200 pt-4">
        <label class="block text-sm font-medium text-slate-700 mb-1">Daftar Bank yang Tersedia</label>
        <textarea name="bank_list" rows="6" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="Satu bank per baris"><?php
$bankList = $s['bank_list'] ?? ['BCA','BNI','BRI','Mandiri','CIMB Niaga','BSI','Permata','DANA','OVO','GoPay','ShopeePay'];
echo e(is_array($bankList) ? implode("\n", $bankList) : $bankList);
?></textarea>
        <p class="text-xs text-slate-400 mt-1">Satu bank per baris. Akan muncul di dropdown merchant saat withdraw.</p>
    </div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
</form>


<?php elseif ($activeTab === 'webhook'): ?>
<!-- WEBHOOK SETTINGS -->
<h3 class="text-lg font-semibold text-slate-800 mb-6">Pengaturan Webhook</h3>
<form method="POST" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="webhook">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Global Webhook URL (Fallback)</label>
        <input type="url" name="global_webhook_url" value="<?= e($s['global_webhook_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/webhook.php">
        <p class="text-xs text-slate-400 mt-1">Digunakan jika merchant tidak mengatur webhook sendiri.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Signature Header Name</label>
            <input type="text" name="webhook_signature_header" value="<?= e($s['webhook_signature_header'] ?? 'X-Signature') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Hash Algorithm</label>
            <select name="webhook_hash_algo" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                <?php foreach (['sha256','sha512','sha1','md5'] as $algo): ?>
                <option value="<?= $algo ?>" <?= ($s['webhook_hash_algo'] ?? 'sha256') === $algo ? 'selected' : '' ?>><?= strtoupper($algo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Max Payload Size (bytes)</label>
        <input type="number" name="webhook_max_payload_size" value="<?= e($s['webhook_max_payload_size'] ?? 65536) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="webhook_retry_enabled" value="1" <?= ($s['webhook_retry_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Enable Webhook Retry</span>
        </label>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Retry Count</label>
            <input type="number" name="webhook_retry_count" value="<?= e($s['webhook_retry_count'] ?? 3) ?>" min="1" max="10" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
    </div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
</form>

<?php elseif ($activeTab === 'settlement'): ?>
<!-- SETTLEMENT SETTINGS -->
<h3 class="text-lg font-semibold text-slate-800 mb-6">Pengaturan Settlement</h3>
<form method="POST" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="settlement">
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="auto_settle" value="1" <?= ($s['auto_settle'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
        <span class="text-sm text-slate-700">Auto Settlement (proses otomatis setelah delay)</span>
    </label>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Delay Settlement (jam)</label>
            <input type="number" name="settle_delay_hours" value="<?= e($s['settle_delay_hours'] ?? 24) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            <p class="text-xs text-slate-400 mt-1">Berapa jam setelah PAID sebelum auto-settle</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Min Settlement Amount (Rp)</label>
            <input type="number" name="min_settlement_amount" value="<?= e($s['min_settlement_amount'] ?? 50000) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Jadwal Settlement</label>
        <select name="settlement_schedule" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
            <option value="manual" <?= ($s['settlement_schedule'] ?? 'manual') === 'manual' ? 'selected' : '' ?>>Manual</option>
            <option value="daily" <?= ($s['settlement_schedule'] ?? '') === 'daily' ? 'selected' : '' ?>>Harian</option>
            <option value="weekly" <?= ($s['settlement_schedule'] ?? '') === 'weekly' ? 'selected' : '' ?>>Mingguan</option>
            <option value="monthly" <?= ($s['settlement_schedule'] ?? '') === 'monthly' ? 'selected' : '' ?>>Bulanan</option>
        </select>
    </div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
</form>


<?php elseif ($activeTab === 'security'): ?>
<!-- SECURITY SETTINGS -->
<h3 class="text-lg font-semibold text-slate-800 mb-6">Pengaturan Keamanan</h3>
<form method="POST" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="security">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Max Login Attempts</label>
            <input type="number" name="login_max_attempts" value="<?= e($s['login_max_attempts'] ?? 5) ?>" min="3" max="20" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Lockout Time (detik)</label>
            <input type="number" name="login_lockout_time" value="<?= e($s['login_lockout_time'] ?? 900) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            <p class="text-xs text-slate-400 mt-1">900 = 15 menit</p>
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Session Lifetime (detik)</label>
            <input type="number" name="session_lifetime" value="<?= e($s['session_lifetime'] ?? 7200) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            <p class="text-xs text-slate-400 mt-1">7200 = 2 jam</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Password Min Length</label>
            <input type="number" name="password_min_length" value="<?= e($s['password_min_length'] ?? 8) ?>" min="6" max="32" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
    </div>
    <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="force_https" value="1" <?= ($s['force_https'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
        <span class="text-sm text-slate-700">Force HTTPS</span>
    </label>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Allowed IPs (Admin Access)</label>
        <textarea name="allowed_ips" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="Kosongkan = semua IP diizinkan. Satu IP per baris."><?= e($s['allowed_ips'] ?? '') ?></textarea>
    </div>

    <!-- Cloudflare Turnstile (Captcha) -->
    <div class="border-t border-slate-200 pt-5 mt-5">
        <h4 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            Cloudflare Turnstile (Captcha)
        </h4>
        <label class="flex items-center gap-2 cursor-pointer mb-4">
            <input type="checkbox" name="turnstile_enabled" value="1" <?= ($s['turnstile_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Aktifkan Turnstile di halaman Login, Register, Forgot Password</span>
        </label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Site Key</label>
                <input type="text" name="turnstile_site_key" value="<?= e($s['turnstile_site_key'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="0x4AAAAAAA...">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Secret Key</label>
                <input type="password" name="turnstile_secret_key" value="<?= e($s['turnstile_secret_key'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="0x4AAAAAAA...">
            </div>
        </div>
        <p class="text-xs text-slate-400 mt-2">Dapatkan keys di <a href="https://dash.cloudflare.com/turnstile" target="_blank" class="text-blue-500 hover:underline">Cloudflare Dashboard → Turnstile</a></p>
    </div>

    <!-- Google OAuth Login -->
    <div class="border-t border-slate-200 pt-5 mt-5">
        <h4 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-red-500" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            Login dengan Google
        </h4>
        <label class="flex items-center gap-2 cursor-pointer mb-4">
            <input type="checkbox" name="google_login_enabled" value="1" <?= ($s['google_login_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Aktifkan Login dengan Google</span>
        </label>
        <div class="space-y-3">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Google Client ID</label>
                <input type="text" name="google_client_id" value="<?= e($s['google_client_id'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="xxxxxxx.apps.googleusercontent.com">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Google Client Secret</label>
                <input type="password" name="google_client_secret" value="<?= e($s['google_client_secret'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="GOCSPX-...">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Redirect URI</label>
                <input type="text" name="google_redirect_uri" value="<?= e($s['google_redirect_uri'] ?? app_url('auth/google-callback.php')) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="https://pay.clipku.com/auth/google-callback.php">
                <p class="text-xs text-slate-400 mt-1">Set URL ini juga di Google Cloud Console → Authorized redirect URIs</p>
            </div>
        </div>
    </div>

    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
</form>

<?php elseif ($activeTab === 'notifications'): ?>
<!-- NOTIFICATION SETTINGS -->
<h3 class="text-lg font-semibold text-slate-800 mb-6">Pengaturan Notifikasi</h3>
<form method="POST" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="_tab" value="notifications">
    
    <!-- SMTP Configuration -->
    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
        <p class="text-sm font-medium text-slate-700 mb-3">SMTP Email Server</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-slate-500 mb-1">SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= e($s['smtp_host'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="smtp.gmail.com">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Port</label>
                <input type="number" name="smtp_port" value="<?= e($s['smtp_port'] ?? 587) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Username</label>
                <input type="text" name="smtp_username" value="<?= e($s['smtp_username'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="user@gmail.com">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Password</label>
                <input type="password" name="smtp_password" value="<?= e($s['smtp_password'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
        </div>
        <div class="mt-3">
            <label class="block text-xs text-slate-500 mb-1">Encryption</label>
            <select name="smtp_encryption" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                <option value="tls" <?= ($s['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
                <option value="ssl" <?= ($s['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                <option value="" <?= ($s['smtp_encryption'] ?? 'tls') === '' ? 'selected' : '' ?>>None (Port 25)</option>
            </select>
        </div>
    </div>

    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
        <p class="text-sm font-medium text-slate-700 mb-3">Email Notification</p>
        <label class="flex items-center gap-2 cursor-pointer mb-3">
            <input type="checkbox" name="notif_email_enabled" value="1" <?= ($s['notif_email_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Aktifkan Email Notification</span>
        </label>
        <div>
            <label class="block text-xs text-slate-500 mb-1">From Email</label>
            <input type="email" name="notif_email_from" value="<?= e($s['notif_email_from'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="noreply@yourdomain.com">
        </div>
    </div>
    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
        <p class="text-sm font-medium text-slate-700 mb-3">WhatsApp Notification</p>
        <label class="flex items-center gap-2 cursor-pointer mb-3">
            <input type="checkbox" name="notif_wa_enabled" value="1" <?= ($s['notif_wa_enabled'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
            <span class="text-sm text-slate-700">Aktifkan WhatsApp Notification</span>
        </label>
        <div class="space-y-3">
            <div>
                <label class="block text-xs text-slate-500 mb-1">WA Gateway API URL</label>
                <input type="url" name="notif_wa_api_url" value="<?= e($s['notif_wa_api_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://wa-api.example.com/send">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">WA API Key</label>
                <input type="password" name="notif_wa_api_key" value="<?= e($s['notif_wa_api_key'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
        </div>
    </div>
    <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg">
        <p class="text-sm font-medium text-slate-700 mb-3">Trigger Notifikasi</p>
        <div class="space-y-2">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="notif_on_payment" value="1" <?= ($s['notif_on_payment'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
                <span class="text-sm text-slate-700">Saat pembayaran berhasil</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="notif_on_withdrawal" value="1" <?= ($s['notif_on_withdrawal'] ?? '0') === '1' ? 'checked' : '' ?> class="w-4 h-4 rounded border-slate-300 text-blue-600">
                <span class="text-sm text-slate-700">Saat withdrawal request</span>
            </label>
        </div>
    </div>
    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
</form>
<?php endif; ?>

</div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
