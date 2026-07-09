<?php
/**
 * Pengaturan Proyek - simpel & mobile-friendly
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
require_once base_path('app/Services/ConfigChangeService.php');

$controller = new MerchantController();
$configService = new ConfigChangeService();
$projectService = new ProjectService();

$projectId = $_GET['id'] ?? '';
if (empty($projectId)) {
    flash('error', 'ID proyek tidak valid.');
    redirect('/merchant/projects.php');
}

if (!$projectService->userOwns(Auth::id(), $projectId)) {
    flash('error', 'Akses ditolak.');
    redirect('/merchant/projects.php');
}

$project = $projectService->find($projectId);
if (!$project) {
    flash('error', 'Proyek tidak ditemukan.');
    redirect('/merchant/projects.php');
}

$pendingChanges = $configService->getPendingByMerchant($projectId);
$hasPendingWebhook = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'webhook_url'));
$hasPendingRedirect = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'redirect_url'));

if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? '';

    if ($action === 'update_general') {
        $result = $projectService->update(Auth::id(), $projectId, [
            'name' => $_POST['business_name'] ?? '',
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}");
    }

    if ($action === 'request_change') {
        $password = $_POST['confirm_password'] ?? '';
        if (!$configService->verifyPassword(Auth::id(), $password)) {
            flash('error', 'Password tidak valid.');
            redirect("/merchant/project-settings.php?id={$projectId}");
        }
        $changeType = $_POST['change_type'] ?? '';
        $oldValueMap = [
            'webhook_url' => $project['webhook_url'] ?? '',
            'redirect_url' => $project['redirect_url'] ?? '',
        ];
        $result = $configService->requestChange([
            'merchant_id' => $projectId,
            'change_type' => $changeType,
            'old_value' => $oldValueMap[$changeType] ?? '',
            'new_value' => sanitize($_POST['new_value'] ?? ''),
            'reason' => sanitize($_POST['reason'] ?? ''),
            'requested_by' => Auth::id(),
            'requested_by_role' => Auth::role(),
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}");
    }

    if ($action === 'update_ip_whitelist') {
        $password = $_POST['confirm_password'] ?? '';
        if (!$configService->verifyPassword(Auth::id(), $password)) {
            flash('error', 'Password tidak valid.');
            redirect("/merchant/project-settings.php?id={$projectId}");
        }
        $result = $projectService->update(Auth::id(), $projectId, [
            'ip_whitelist' => $_POST['ip_whitelist'] ?? '',
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}");
    }

    if ($action === 'cancel_change') {
        $changeId = $_POST['change_id'] ?? '';
        $result = $configService->cancel($changeId, $projectId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}");
    }
}

$ipEmpty = trim($project['ip_whitelist'] ?? '') === '';
$isProduction = ($project['mode'] ?? 'sandbox') === 'production';

$pageTitle = 'Pengaturan Proyek';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<!-- Back link -->
<a href="/merchant/projects.php" class="inline-flex items-center gap-1 text-sm text-slate-500 mb-4 active:text-slate-700">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Kembali
</a>

<!-- Project Header -->
<div class="flex items-center gap-3 mb-6">
    <div class="w-11 h-11 rounded-xl <?= $isProduction ? 'bg-emerald-600' : 'bg-slate-800' ?> flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
        <?= strtoupper(mb_substr($project['business_name'], 0, 2)) ?>
    </div>
    <div class="min-w-0 flex-1">
        <h3 class="text-base font-bold text-slate-900 truncate"><?= e($project['business_name']) ?></h3>
        <div class="flex items-center gap-2 mt-0.5">
            <span class="text-[11px] px-1.5 py-0.5 rounded font-medium <?= $isProduction ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                <?= $isProduction ? 'Production' : 'Sandbox' ?>
            </span>
            <span class="text-[11px] text-slate-400 font-mono truncate"><?= e($project['slug'] ?? '') ?></span>
        </div>
    </div>
</div>


<!-- Section: Nama Proyek -->
<div class="bg-white rounded-xl border border-slate-200 mb-4 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
        <h4 class="text-sm font-semibold text-slate-800">Nama Proyek</h4>
    </div>
    <form method="POST" action="?id=<?= e($projectId) ?>" class="p-4 space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="update_general">
        <input type="text" name="business_name" value="<?= e($project['business_name']) ?>" required maxlength="255" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
        <p class="text-[11px] text-slate-400">Slug: <code class="px-1 py-0.5 bg-slate-100 rounded text-[11px]"><?= e($project['slug'] ?? '') ?></code> (otomatis)</p>
        <button type="submit" class="w-full py-2.5 text-sm font-medium text-white bg-slate-900 rounded-lg active:bg-slate-800 transition-colors">Simpan</button>
    </form>
</div>

<!-- Section: Webhook -->
<div class="bg-white rounded-xl border border-slate-200 mb-4 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
        <h4 class="text-sm font-semibold text-slate-800">Webhook & Redirect</h4>
    </div>
    <div class="p-4 space-y-4">
        <!-- Current Values -->
        <div class="space-y-3">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Webhook URL</label>
                    <?php if ($hasPendingWebhook): ?>
                    <span class="text-[10px] px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded font-medium">Pending</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-slate-700 font-mono break-all bg-slate-50 px-3 py-2 rounded-lg border border-slate-100"><?= e($project['webhook_url'] ?: '— belum diatur') ?></p>
            </div>
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Redirect URL</label>
                    <?php if ($hasPendingRedirect): ?>
                    <span class="text-[10px] px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded font-medium">Pending</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-slate-700 font-mono break-all bg-slate-50 px-3 py-2 rounded-lg border border-slate-100"><?= e($project['redirect_url'] ?: '— belum diatur') ?></p>
            </div>
        </div>

        <!-- Signing Secret -->
        <div class="pt-3 border-t border-slate-100">
            <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block mb-1.5">Signing Secret</label>
            <div class="flex gap-2">
                <input type="password" id="signingSecret" value="<?= e($project['api_key'] ?? '') ?>" readonly class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-mono text-slate-600 min-w-0">
                <button type="button" onclick="toggleSecret()" class="px-3 py-2 text-slate-500 bg-slate-50 border border-slate-200 rounded-lg active:bg-slate-100" title="Tampilkan/sembunyikan">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
                <button type="button" onclick="copySecret()" class="px-3 py-2 text-slate-500 bg-slate-50 border border-slate-200 rounded-lg active:bg-slate-100" title="Salin">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                </button>
            </div>
            <p class="text-[11px] text-slate-400 mt-1.5">Untuk verifikasi header <code class="px-1 bg-slate-100 rounded">X-Signature</code>.</p>
        </div>


        <!-- Change URL Forms -->
        <div class="pt-3 border-t border-slate-100 space-y-3">
            <p class="text-xs font-medium text-slate-600">Ubah URL</p>

            <?php if (!$hasPendingWebhook): ?>
            <form method="POST" action="?id=<?= e($projectId) ?>" class="space-y-2.5 p-3 bg-slate-50 rounded-lg border border-slate-100">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="request_change">
                <input type="hidden" name="change_type" value="webhook_url">
                <label class="text-xs text-slate-600 font-medium">Webhook URL Baru</label>
                <input type="url" name="new_value" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="https://domain.com/webhook">
                <input type="password" name="confirm_password" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Konfirmasi password">
                <button type="submit" onclick="return confirm('Ajukan perubahan?')" class="w-full py-2 text-xs font-medium text-white bg-blue-600 rounded-lg active:bg-blue-700">Ajukan Perubahan</button>
            </form>
            <?php else: ?>
            <div class="p-3 bg-amber-50 rounded-lg border border-amber-200">
                <p class="text-[11px] text-amber-800 font-medium">Perubahan webhook URL menunggu persetujuan admin.</p>
            </div>
            <?php endif; ?>

            <?php if (!$hasPendingRedirect): ?>
            <form method="POST" action="?id=<?= e($projectId) ?>" class="space-y-2.5 p-3 bg-slate-50 rounded-lg border border-slate-100">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="request_change">
                <input type="hidden" name="change_type" value="redirect_url">
                <label class="text-xs text-slate-600 font-medium">Redirect URL Baru</label>
                <input type="url" name="new_value" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="https://domain.com/success">
                <input type="password" name="confirm_password" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Konfirmasi password">
                <button type="submit" onclick="return confirm('Ajukan perubahan?')" class="w-full py-2 text-xs font-medium text-slate-700 bg-slate-200 rounded-lg active:bg-slate-300">Ajukan Perubahan</button>
            </form>
            <?php else: ?>
            <div class="p-3 bg-amber-50 rounded-lg border border-amber-200">
                <p class="text-[11px] text-amber-800 font-medium">Perubahan redirect URL menunggu persetujuan admin.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- Section: IP Whitelist -->
<div class="bg-white rounded-xl border <?= $ipEmpty ? 'border-amber-200' : 'border-slate-200' ?> mb-4 overflow-hidden">
    <div class="px-4 py-3 border-b <?= $ipEmpty ? 'border-amber-100' : 'border-slate-100' ?>">
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-slate-800">IP Whitelist</h4>
            <?php if (!$ipEmpty): ?>
            <span class="text-[10px] px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-full font-semibold">Aktif</span>
            <?php else: ?>
            <span class="text-[10px] px-2 py-0.5 bg-amber-50 text-amber-700 rounded-full font-semibold">Belum diatur</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="p-4 space-y-3">
        <?php if ($ipEmpty): ?>
        <div class="p-3 bg-amber-50 rounded-lg">
            <p class="text-[11px] text-amber-800">Semua IP dapat mengakses API Anda. Tambahkan IP server untuk keamanan maksimal.</p>
        </div>
        <?php endif; ?>

        <form method="POST" action="?id=<?= e($projectId) ?>" class="space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="update_ip_whitelist">
            <div>
                <label class="text-xs text-slate-600 font-medium block mb-1">Daftar IP</label>
                <textarea name="ip_whitelist" rows="3" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="103.10.10.1&#10;103.10.10.2/24"><?= e($project['ip_whitelist'] ?? '') ?></textarea>
                <p class="text-[11px] text-slate-400 mt-1">Satu IP per baris. Mendukung CIDR.</p>
            </div>
            <input type="password" name="confirm_password" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Konfirmasi password">
            <button type="submit" onclick="return confirm('Simpan IP Whitelist?')" class="w-full py-2.5 text-sm font-medium text-white bg-slate-900 rounded-lg active:bg-slate-800 transition-colors">Simpan IP Whitelist</button>
        </form>
    </div>
</div>

<!-- Quick Links -->
<div class="flex gap-3 mb-8">
    <a href="/merchant/integration.php?tab=test" class="flex-1 flex items-center justify-center gap-1.5 py-2.5 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg border border-blue-100 active:bg-blue-100">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z"/></svg>
        Test Webhook
    </a>
    <a href="/merchant/webhook-logs.php" class="flex-1 flex items-center justify-center gap-1.5 py-2.5 text-xs font-medium text-slate-600 bg-slate-50 rounded-lg border border-slate-200 active:bg-slate-100">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        Webhook Logs
    </a>
</div>

<script>
function toggleSecret(){
    var f=document.getElementById('signingSecret');
    f.type=f.type==='password'?'text':'password';
}
function copySecret(){
    var f=document.getElementById('signingSecret');
    navigator.clipboard.writeText(f.value).then(function(){
        if(typeof showToast==='function')showToast('Disalin!');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
