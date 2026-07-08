<?php
/**
 * Project Settings - Pengaturan inti per proyek
 *
 * Fokus pada 3 hal utama:
 *   1. Nama Projek
 *   2. Webhook URL (+ Redirect URL)
 *   3. IP Whitelist (wajib untuk keamanan)
 *
 * API Key & WhatsApp diatur terpusat di halaman Pengaturan (settings.php).
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
require_once base_path('app/Services/ConfigChangeService.php');

$controller = new MerchantController();
$configService = new ConfigChangeService();
$projectService = new ProjectService();

// Get project by ID parameter
$projectId = $_GET['id'] ?? '';
if (empty($projectId)) {
    flash('error', 'ID proyek tidak valid.');
    redirect('/merchant/projects.php');
}

// Verify ownership
if (!$projectService->userOwns(Auth::id(), $projectId)) {
    flash('error', 'Akses ditolak.');
    redirect('/merchant/projects.php');
}

$project = $projectService->find($projectId);
if (!$project) {
    flash('error', 'Proyek tidak ditemukan.');
    redirect('/merchant/projects.php');
}

// Load pending changes (webhook / redirect approval flow)
$pendingChanges = $configService->getPendingByMerchant($projectId);
$hasPendingWebhook = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'webhook_url'));
$hasPendingRedirect = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'redirect_url'));


// Handle POST actions
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? $_POST['action'] ?? '';

    // --- Update project name (direct) ---
    if ($action === 'update_general') {
        $result = $projectService->update(Auth::id(), $projectId, [
            'name' => $_POST['business_name'] ?? '',
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}");
    }

    // --- Request webhook/redirect URL change (admin approval) ---
    if ($action === 'request_change') {
        $password = $_POST['confirm_password'] ?? '';
        if (!$configService->verifyPassword(Auth::id(), $password)) {
            flash('error', 'Password tidak valid. Verifikasi gagal.');
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

    // --- Update IP Whitelist (direct, requires password) ---
    if ($action === 'update_ip_whitelist') {
        $password = $_POST['confirm_password'] ?? '';
        if (!$configService->verifyPassword(Auth::id(), $password)) {
            flash('error', 'Password tidak valid. Verifikasi gagal.');
            redirect("/merchant/project-settings.php?id={$projectId}");
        }
        $result = $projectService->update(Auth::id(), $projectId, [
            'ip_whitelist' => $_POST['ip_whitelist'] ?? '',
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}");
    }

    // --- Cancel pending change ---
    if ($action === 'cancel_change') {
        $changeId = $_POST['change_id'] ?? '';
        $result = $configService->cancel($changeId, $projectId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}");
    }
}

$ipEmpty = trim($project['ip_whitelist'] ?? '') === '';

$pageTitle = 'Pengaturan Proyek: ' . ($project['business_name'] ?? '');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>


<!-- Breadcrumb -->
<div class="flex items-center gap-2 text-sm text-slate-500 mb-4">
    <a href="/merchant/projects.php" class="hover:text-slate-700">Proyek</a>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-slate-800 font-medium"><?= e($project['business_name']) ?></span>
</div>

<!-- Project Header -->
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center text-white font-bold">
            <?= strtoupper(substr($project['business_name'], 0, 1)) ?>
        </div>
        <div>
            <h3 class="text-lg font-semibold text-slate-800"><?= e($project['business_name']) ?></h3>
            <p class="text-xs text-slate-500 font-mono"><?= e($project['slug'] ?? '') ?></p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= ($project['mode'] ?? 'sandbox') === 'production' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
            <?= ucfirst($project['mode'] ?? 'sandbox') ?>
        </span>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= status_badge_class($project['status']) ?>">
            <?= ucfirst($project['status']) ?>
        </span>
    </div>
</div>

<!-- Info banner: API key & WhatsApp diatur di Pengaturan -->
<div class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-lg">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-slate-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p class="text-sm text-slate-600"><strong>API Key</strong> dan <strong>Integrasi WhatsApp</strong> kini diatur terpusat di halaman <a href="/merchant/settings.php?tab=apikey" class="text-blue-600 font-medium hover:underline">Pengaturan</a>. Halaman ini fokus pada konfigurasi inti proyek.</p>
    </div>
</div>

<div class="max-w-2xl space-y-6">
    <!-- ===== 1. Nama Projek ===== -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-xs font-bold">1</span>
            Nama Projek
        </h4>
        <form method="POST" action="?id=<?= e($projectId) ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="update_general">
            <div>
                <input type="text" name="business_name" value="<?= e($project['business_name']) ?>" required maxlength="255" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                <p class="text-xs text-slate-400 mt-1">Slug: <span class="font-mono"><?= e($project['slug'] ?? '') ?></span> (otomatis, tidak bisa diubah)</p>
            </div>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan Nama</button>
        </form>
    </div>


    <!-- ===== 2. Webhook URL ===== -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <span class="w-6 h-6 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-xs font-bold">2</span>
            Webhook URL
        </h4>

        <!-- Current values -->
        <div class="space-y-3 mb-5">
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-medium text-slate-500">Webhook URL saat ini</label>
                    <?php if ($hasPendingWebhook): ?><span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded">Menunggu approval</span><?php endif; ?>
                </div>
                <p class="text-sm font-mono text-slate-700 break-all"><?= e($project['webhook_url'] ?: '(belum diatur)') ?></p>
            </div>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-medium text-slate-500">Redirect URL saat ini</label>
                    <?php if ($hasPendingRedirect): ?><span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded">Menunggu approval</span><?php endif; ?>
                </div>
                <p class="text-sm font-mono text-slate-700 break-all"><?= e($project['redirect_url'] ?: '(belum diatur)') ?></p>
            </div>
        </div>

        <!-- Change Webhook URL -->
        <?php if (!$hasPendingWebhook): ?>
        <form method="POST" action="?id=<?= e($projectId) ?>" class="space-y-3 pb-5 mb-5 border-b border-slate-100">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="request_change">
            <input type="hidden" name="change_type" value="webhook_url">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Ubah Webhook URL</label>
                <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/webhook">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" onclick="return confirm('Ajukan perubahan webhook URL?')" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Ajukan Perubahan</button>
            <p class="text-xs text-slate-400">Perubahan webhook URL perlu persetujuan admin demi keamanan.</p>
        </form>
        <?php else: ?>
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg mb-5">
            <p class="text-xs text-amber-700 font-medium">Perubahan webhook URL sedang menunggu persetujuan admin.</p>
        </div>
        <?php endif; ?>

        <!-- Change Redirect URL -->
        <?php if (!$hasPendingRedirect): ?>
        <form method="POST" action="?id=<?= e($projectId) ?>" class="space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="request_change">
            <input type="hidden" name="change_type" value="redirect_url">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Ubah Redirect URL <span class="text-slate-400">(opsional)</span></label>
                <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/success">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" onclick="return confirm('Ajukan perubahan redirect URL?')" class="px-5 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Ajukan Perubahan</button>
        </form>
        <?php else: ?>
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-xs text-amber-700 font-medium">Perubahan redirect URL sedang menunggu persetujuan admin.</p>
        </div>
        <?php endif; ?>

        <div class="mt-5 pt-4 border-t border-slate-100 flex items-center gap-4 text-sm">
            <a href="/merchant/integration.php?tab=test" class="text-blue-600 hover:text-blue-700 font-medium">Test Webhook &rarr;</a>
            <a href="/merchant/webhook-logs.php" class="text-blue-600 hover:text-blue-700 font-medium">Webhook Logs &rarr;</a>
        </div>
    </div>


    <!-- ===== 3. IP Whitelist (wajib) ===== -->
    <div class="bg-white rounded-xl border-2 <?= $ipEmpty ? 'border-amber-300' : 'border-slate-200' ?> p-6">
        <h4 class="text-sm font-semibold text-slate-800 mb-1 flex items-center gap-2">
            <span class="w-6 h-6 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-xs font-bold">3</span>
            IP Whitelist
            <span class="ml-1 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded font-medium">Wajib</span>
        </h4>
        <p class="text-xs text-slate-500 mb-4 ml-8">Hanya IP yang terdaftar yang boleh mengakses API proyek ini. Sangat disarankan untuk keamanan.</p>

        <?php if ($ipEmpty): ?>
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-xs text-amber-800"><strong>Belum diatur.</strong> Saat ini semua IP dapat mengakses API proyek ini. Tambahkan IP server Anda untuk keamanan maksimal.</p>
        </div>
        <?php endif; ?>

        <form method="POST" action="?id=<?= e($projectId) ?>" class="space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="update_ip_whitelist">
            <div>
                <textarea name="ip_whitelist" rows="4" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="103.10.10.1&#10;103.10.10.2/24"><?= e($project['ip_whitelist'] ?? '') ?></textarea>
                <p class="text-xs text-slate-400 mt-1">Satu IP per baris (mendukung CIDR). Kosongkan hanya jika benar-benar perlu mengizinkan semua IP.</p>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Masukkan password untuk konfirmasi">
            </div>
            <button type="submit" onclick="return confirm('Simpan perubahan IP Whitelist?')" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan IP Whitelist</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
