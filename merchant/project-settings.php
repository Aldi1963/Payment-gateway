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

$pageTitle = 'Pengaturan Proyek';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>


<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm mb-6">
    <a href="/merchant/projects.php" class="text-slate-500 hover:text-blue-600 transition-colors flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
        Proyek
    </a>
    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-slate-800 font-medium truncate"><?= e($project['business_name']) ?></span>
</nav>

<!-- Project Header Card -->
<div class="bg-white rounded-2xl border border-slate-200 p-6 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
            <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center text-white font-bold text-xl flex-shrink-0 shadow-lg shadow-blue-500/20">
                <?= strtoupper(substr($project['business_name'], 0, 1)) ?>
            </div>
            <div class="min-w-0">
                <h3 class="text-xl font-bold text-slate-900 truncate"><?= e($project['business_name']) ?></h3>
                <div class="flex items-center gap-3 mt-1">
                    <span class="text-xs text-slate-500 font-mono bg-slate-50 px-2 py-0.5 rounded"><?= e($project['slug'] ?? '') ?></span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-medium <?= ($project['mode'] ?? 'sandbox') === 'production' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= ($project['mode'] ?? 'sandbox') === 'production' ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                        <?= ucfirst($project['mode'] ?? 'sandbox') ?>
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-medium <?= status_badge_class($project['status']) ?>">
                        <?= ucfirst($project['status']) ?>
                    </span>
                </div>
            </div>
        </div>
        <a href="/merchant/settings.php?tab=apikey" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-slate-50 rounded-xl hover:bg-slate-100 transition-colors border border-slate-200 whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            API Key & WhatsApp
        </a>
    </div>
</div>


<!-- Settings Grid -->
<div class="max-w-3xl space-y-6">

    <!-- ===== Section 1: Nama Proyek ===== -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-slate-800">Identitas Proyek</h4>
                    <p class="text-xs text-slate-500">Nama yang ditampilkan di halaman pembayaran</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <form method="POST" action="?id=<?= e($projectId) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="update_general">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Nama Proyek</label>
                    <input type="text" name="business_name" value="<?= e($project['business_name']) ?>" required maxlength="255" class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors">
                    <p class="text-xs text-slate-400 mt-1.5">Slug: <code class="px-1.5 py-0.5 bg-slate-100 rounded text-slate-600"><?= e($project['slug'] ?? '') ?></code> &mdash; otomatis, tidak bisa diubah</p>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors shadow-sm">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- ===== Section 2: Webhook & Redirect ===== -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-slate-800">Webhook & Redirect</h4>
                    <p class="text-xs text-slate-500">URL notifikasi pembayaran dan redirect pelanggan</p>
                </div>
            </div>
        </div>
        <div class="p-6 space-y-5">
            <!-- Current URL Values -->
            <div class="grid sm:grid-cols-2 gap-3">
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Webhook URL</label>
                        <?php if ($hasPendingWebhook): ?>
                        <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full font-medium">
                            <span class="w-1 h-1 bg-amber-500 rounded-full animate-pulse"></span>
                            Pending
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm font-mono text-slate-700 break-all leading-relaxed"><?= e($project['webhook_url'] ?: '— belum diatur') ?></p>
                </div>
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Redirect URL</label>
                        <?php if ($hasPendingRedirect): ?>
                        <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full font-medium">
                            <span class="w-1 h-1 bg-amber-500 rounded-full animate-pulse"></span>
                            Pending
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm font-mono text-slate-700 break-all leading-relaxed"><?= e($project['redirect_url'] ?: '— belum diatur') ?></p>
                </div>
            </div>

            <!-- Webhook Signing Secret -->
            <div class="p-4 bg-gradient-to-r from-slate-50 to-slate-100/50 rounded-xl border border-slate-200">
                <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide block mb-2">Webhook Signing Secret</label>
                <div class="flex items-center gap-2">
                    <div class="flex-1 relative">
                        <input type="password" id="signingSecret" value="<?= e($project['api_key'] ?? '') ?>" readonly autocomplete="off" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm font-mono text-slate-700 pr-10">
                        <button type="button" onclick="toggleSecret()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <svg id="eyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                    <button type="button" onclick="copySigningSecret()" class="px-4 py-2.5 bg-white text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 border border-slate-200 transition-colors whitespace-nowrap">
                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Copy
                    </button>
                </div>
                <p class="text-xs text-slate-400 mt-2">Gunakan secret ini untuk memverifikasi header <code class="px-1 py-0.5 bg-slate-200 rounded text-slate-600">X-Signature</code> pada webhook.</p>
            </div>


            <!-- Divider -->
            <div class="border-t border-slate-100 pt-5">
                <h5 class="text-sm font-semibold text-slate-700 mb-4">Ubah URL</h5>

                <!-- Change Webhook URL -->
                <?php if (!$hasPendingWebhook): ?>
                <form method="POST" action="?id=<?= e($projectId) ?>" class="p-4 bg-slate-50 rounded-xl border border-slate-100 space-y-3 mb-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="request_change">
                    <input type="hidden" name="change_type" value="webhook_url">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Webhook URL Baru</label>
                        <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="https://yourdomain.com/webhook">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Konfirmasi Password</label>
                        <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm" placeholder="Masukkan password Anda">
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-slate-400">Perubahan memerlukan persetujuan admin.</p>
                        <button type="submit" onclick="return confirm('Ajukan perubahan webhook URL?')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-xs font-semibold hover:bg-blue-700 transition-colors">Ajukan Perubahan</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                        <p class="text-xs text-amber-800 font-medium">Perubahan webhook URL sedang menunggu persetujuan admin.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Change Redirect URL -->
                <?php if (!$hasPendingRedirect): ?>
                <form method="POST" action="?id=<?= e($projectId) ?>" class="p-4 bg-slate-50 rounded-xl border border-slate-100 space-y-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="request_change">
                    <input type="hidden" name="change_type" value="redirect_url">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Redirect URL Baru <span class="text-slate-400 font-normal">(opsional)</span></label>
                        <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="https://yourdomain.com/success">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Konfirmasi Password</label>
                        <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm" placeholder="Masukkan password Anda">
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-slate-400">Perubahan memerlukan persetujuan admin.</p>
                        <button type="submit" onclick="return confirm('Ajukan perubahan redirect URL?')" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-xs font-semibold hover:bg-slate-800 transition-colors">Ajukan Perubahan</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                        <p class="text-xs text-amber-800 font-medium">Perubahan redirect URL sedang menunggu persetujuan admin.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links -->
            <div class="flex items-center gap-4 pt-4 border-t border-slate-100">
                <a href="/merchant/integration.php?tab=test" class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Test Webhook
                </a>
                <a href="/merchant/webhook-logs.php" class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Webhook Logs
                </a>
            </div>
        </div>
    </div>


    <!-- ===== Section 3: IP Whitelist ===== -->
    <div class="bg-white rounded-2xl border-2 <?= $ipEmpty ? 'border-amber-200' : 'border-slate-200' ?> overflow-hidden">
        <div class="px-6 py-4 border-b <?= $ipEmpty ? 'border-amber-100 bg-amber-50/50' : 'border-slate-100 bg-slate-50/50' ?>">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 <?= $ipEmpty ? 'bg-amber-100' : 'bg-emerald-100' ?> rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 <?= $ipEmpty ? 'text-amber-600' : 'text-emerald-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <h4 class="text-sm font-semibold text-slate-800">IP Whitelist</h4>
                        <?php if ($ipEmpty): ?>
                        <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full font-semibold uppercase">
                            Belum Dikonfigurasi
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full font-semibold uppercase">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Aktif
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-500">Batasi akses API hanya dari IP tertentu</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <?php if ($ipEmpty): ?>
            <div class="mb-5 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                <div class="flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <div class="text-xs text-amber-800">
                        <p class="font-semibold">Keamanan Rendah</p>
                        <p class="mt-0.5">Saat ini semua IP dapat mengakses API. Tambahkan IP server Anda untuk keamanan maksimal.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" action="?id=<?= e($projectId) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="update_ip_whitelist">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Daftar IP yang Diizinkan</label>
                    <textarea name="ip_whitelist" rows="4" class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors placeholder:text-slate-400" placeholder="103.10.10.1&#10;103.10.10.2/24&#10;2001:db8::/32"><?= e($project['ip_whitelist'] ?? '') ?></textarea>
                    <p class="text-xs text-slate-400 mt-1.5">Satu IP per baris. Mendukung IPv4, IPv6, dan notasi CIDR.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl text-sm" placeholder="Masukkan password untuk konfirmasi">
                </div>
                <div class="flex justify-end">
                    <button type="submit" onclick="return confirm('Simpan perubahan IP Whitelist?')" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors shadow-sm">
                        Simpan IP Whitelist
                    </button>
                </div>
            </form>
        </div>
    </div>

</div><!-- end max-w-3xl -->


<!-- JavaScript -->
<script>
function toggleSecret() {
    var f = document.getElementById('signingSecret');
    f.type = f.type === 'password' ? 'text' : 'password';
}
function copySigningSecret() {
    var f = document.getElementById('signingSecret');
    navigator.clipboard.writeText(f.value).then(function(){
        if (typeof showToast === 'function') showToast('Signing secret berhasil disalin!');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
