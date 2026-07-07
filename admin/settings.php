<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireRole(['super_admin', 'admin']);

require_once base_path('app/Repositories/SettingRepository.php');
require_once base_path('app/Services/AuditLogService.php');
$settingRepo = new SettingRepository();
$auditService = new AuditLogService();

if (is_post()) {
    Auth::verifyCsrf();
    $settingRepo->set('default_fee_type', $_POST['default_fee_type'] ?? 'percentage');
    $settingRepo->set('default_fee_value', (float)($_POST['default_fee_value'] ?? 0.7));
    $settingRepo->set('default_fee_flat', (float)($_POST['default_fee_flat'] ?? 0));
    $settingRepo->set('min_withdrawal', (int)($_POST['min_withdrawal'] ?? 10000));
    $settingRepo->set('global_webhook_url', sanitize($_POST['global_webhook_url'] ?? ''));
    $settingRepo->set('app_name', sanitize($_POST['app_name'] ?? 'PayGate Pro'));

    $auditService->log(Auth::id(), Auth::role(), null, 'settings_changed', 'Global settings updated', []);
    flash('success', 'Pengaturan berhasil disimpan.');
    redirect('/admin/settings.php');
}

$settings = $settingRepo->getAllSettings();
$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-6">Pengaturan Global</h3>
        
        <form method="POST" class="space-y-5">
            <?= csrf_field() ?>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Aplikasi</label>
                <input type="text" name="app_name" value="<?= e($settings['app_name'] ?? 'PayGate Pro') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Default Fee Type</label>
                    <select name="default_fee_type" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                        <option value="percentage" <?= ($settings['default_fee_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                        <option value="flat" <?= ($settings['default_fee_type'] ?? '') === 'flat' ? 'selected' : '' ?>>Flat</option>
                        <option value="hybrid" <?= ($settings['default_fee_type'] ?? '') === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fee Value</label>
                    <input type="number" name="default_fee_value" step="0.01" value="<?= e($settings['default_fee_value'] ?? 0.7) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fee Flat (hybrid)</label>
                    <input type="number" name="default_fee_flat" value="<?= e($settings['default_fee_flat'] ?? 0) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Minimal Withdrawal</label>
                <input type="number" name="min_withdrawal" value="<?= e($settings['min_withdrawal'] ?? 10000) ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Global Webhook URL</label>
                <input type="url" name="global_webhook_url" value="<?= e($settings['global_webhook_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://...">
                <p class="text-xs text-slate-400 mt-1">Fallback webhook jika merchant tidak set webhook sendiri.</p>
            </div>

            <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                Simpan Pengaturan
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
