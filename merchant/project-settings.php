<?php
/**
 * Project Settings - Pusat konfigurasi per proyek
 * Tabs: General, API, Webhook, WhatsApp
 * 
 * Sumber kebenaran tunggal untuk semua setting proyek.
 * integration.php hanya untuk dokumentasi & testing.
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
require_once base_path('app/Services/ConfigChangeService.php');

$controller = new MerchantController();
$configService = new ConfigChangeService();

// Get project by ID parameter
$projectId = $_GET['id'] ?? '';
if (empty($projectId)) {
    flash('error', 'ID proyek tidak valid.');
    redirect('/merchant/projects.php');
}

// Verify ownership
$projectService = new ProjectService();
if (!$projectService->userOwns(Auth::id(), $projectId)) {
    flash('error', 'Akses ditolak.');
    redirect('/merchant/projects.php');
}

$project = $projectService->find($projectId);
if (!$project) {
    flash('error', 'Proyek tidak ditemukan.');
    redirect('/merchant/projects.php');
}

// Tab navigation
$activeTab = $_GET['tab'] ?? 'general';
$validTabs = ['general', 'api', 'webhook', 'whatsapp'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'general';

// Load WA config for whatsapp tab
$waConfig = null;
if ($activeTab === 'whatsapp') {
    $waConfig = (new WaConfigRepository())->findByMerchant($projectId);
}


// Load pending changes
$pendingChanges = $configService->getPendingByMerchant($projectId);
$hasPendingKey = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'api_key_regenerate'));
$hasPendingWebhook = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'webhook_url'));
$hasPendingRedirect = !empty(array_filter($pendingChanges, fn($c) => $c['change_type'] === 'redirect_url'));

// Handle POST actions
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? $_POST['action'] ?? '';

    // --- General Tab: Update project name ---
    if ($activeTab === 'general' && $action === 'update_general') {
        $result = $projectService->update(Auth::id(), $projectId, [
            'name' => $_POST['business_name'] ?? '',
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}&tab=general");
    }

    // --- API Tab: Regenerate API Key ---
    if ($activeTab === 'api' && $action === 'regenerate') {
        $password = $_POST['confirm_password'] ?? '';
        if (!$configService->verifyPassword(Auth::id(), $password)) {
            flash('error', 'Password tidak valid. Verifikasi gagal.');
            redirect("/merchant/project-settings.php?id={$projectId}&tab=api");
        }
        $requireApproval = setting('require_approval_api_key', '1') === '1';
        if ($requireApproval) {
            $result = $configService->requestChange([
                'merchant_id' => $projectId,
                'change_type' => 'api_key_regenerate',
                'old_value' => mask_api_key($project['api_key']),
                'new_value' => '(akan di-generate otomatis setelah approved)',
                'reason' => sanitize($_POST['reason'] ?? 'Regenerate API Key'),
                'requested_by' => Auth::id(),
                'requested_by_role' => Auth::role(),
            ]);
        } else {
            $result = $projectService->regenerateApiKey(Auth::id(), $projectId);
        }
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}&tab=api");
    }


    // --- Webhook Tab: Request URL change ---
    if ($activeTab === 'webhook' && $action === 'request_change') {
        $password = $_POST['confirm_password'] ?? '';
        if (!$configService->verifyPassword(Auth::id(), $password)) {
            flash('error', 'Password tidak valid. Verifikasi gagal.');
            redirect("/merchant/project-settings.php?id={$projectId}&tab=webhook");
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
        redirect("/merchant/project-settings.php?id={$projectId}&tab=webhook");
    }

    // --- Webhook Tab: Update IP Whitelist ---
    if ($activeTab === 'webhook' && $action === 'update_ip_whitelist') {
        $result = $projectService->update(Auth::id(), $projectId, [
            'ip_whitelist' => $_POST['ip_whitelist'] ?? '',
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}&tab=webhook");
    }

    // --- Webhook Tab: Cancel pending change ---
    if ($action === 'cancel_change') {
        $changeId = $_POST['change_id'] ?? '';
        $result = $configService->cancel($changeId, $projectId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}&tab={$activeTab}");
    }

    // --- WhatsApp Tab: Save WA config ---
    if ($activeTab === 'whatsapp' && $action === 'save_wa_config') {
        // Temporarily switch active merchant context for the controller
        $origMerchantId = $_SESSION['merchant_id'] ?? null;
        $_SESSION['merchant_id'] = $projectId;
        $_SESSION['active_merchant_id'] = $projectId;
        $result = $controller->saveWaConfig($_POST);
        // Restore original
        if ($origMerchantId) {
            $_SESSION['merchant_id'] = $origMerchantId;
            $_SESSION['active_merchant_id'] = $origMerchantId;
        }
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect("/merchant/project-settings.php?id={$projectId}&tab=whatsapp");
    }
}


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

<!-- Tab Navigation -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <?php
        $tabs = [
            'general' => ['label' => 'General', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>'],
            'api' => ['label' => 'API Key', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>'],
            'webhook' => ['label' => 'Webhook', 'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>'],
            'whatsapp' => ['label' => 'WhatsApp', 'icon' => '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a7.96 7.96 0 01-4.106-1.138l-.294-.176-2.866.852.852-2.866-.176-.294A7.96 7.96 0 014 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"/></svg>'],
        ];
        foreach ($tabs as $key => $tab): ?>
        <a href="?id=<?= e($projectId) ?>&tab=<?= $key ?>" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $activeTab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
            <?= $tab['icon'] ?>
            <?= $tab['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>


<?php if ($activeTab === 'general'): ?>
<!-- ============ TAB: GENERAL ============ -->
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Informasi Proyek</h3>
        <form method="POST" action="?id=<?= e($projectId) ?>&tab=general" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="update_general">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Toko</label>
                <input type="text" name="business_name" value="<?= e($project['business_name']) ?>" required maxlength="255" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
                <input type="text" value="<?= e($project['slug'] ?? '') ?>" readonly class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm font-mono text-slate-600">
                <p class="text-xs text-slate-400 mt-1">Slug otomatis dari nama toko, tidak bisa diubah.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mode</label>
                    <input type="text" value="<?= ucfirst($project['mode'] ?? 'sandbox') ?>" readonly class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
                    <p class="text-xs text-slate-400 mt-1">Hubungi admin untuk beralih ke production.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <input type="text" value="<?= ucfirst($project['status'] ?? '') ?>" readonly class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Dibuat</label>
                    <input type="text" value="<?= format_date($project['created_at'] ?? '') ?>" readonly class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Terakhir Update</label>
                    <input type="text" value="<?= format_date($project['updated_at'] ?? '') ?>" readonly class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-600">
                </div>
            </div>
            <div class="pt-4 border-t border-slate-200">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>


<?php elseif ($activeTab === 'api'): ?>
<!-- ============ TAB: API KEY ============ -->
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">API Key</h3>
        <p class="text-sm text-slate-500 mb-4">Gunakan API key ini untuk autentikasi pada semua request API. <strong>Jangan bagikan ke pihak lain.</strong></p>
        
        <div class="flex items-center gap-2 mb-4">
            <input type="text" id="apiKeyField" value="<?= e($project['api_key'] ?? '') ?>" readonly class="flex-1 px-4 py-3 bg-slate-900 text-emerald-400 font-mono text-sm rounded-lg border-0">
            <button onclick="copyToClipboard('apiKeyField')" class="px-4 py-3 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 whitespace-nowrap">Copy</button>
        </div>

        <div class="pt-4 border-t border-slate-200">
            <p class="text-xs text-slate-400 mb-3">Masked: <?= mask_api_key($project['api_key'] ?? '') ?></p>
            
            <?php if ($hasPendingKey): ?>
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <p class="text-xs text-amber-700 font-medium">Regenerasi API key menunggu persetujuan admin.</p>
            </div>
            <?php else: ?>
            <form method="POST" action="?id=<?= e($projectId) ?>&tab=api" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="_action" value="regenerate">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Alasan <span class="text-slate-400">(opsional)</span></label>
                    <input type="text" name="reason" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" placeholder="Compromised, rotasi rutin, dll.">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                    <input type="password" name="confirm_password" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" placeholder="Masukkan password Anda">
                </div>
                <button type="submit" onclick="return confirm('API key lama tidak berlaku setelah disetujui. Lanjutkan?')" class="px-4 py-2 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 border border-red-200">
                    Ajukan Regenerate API Key
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Reference -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-3">Quick Reference</h3>
        <div class="space-y-3 text-sm">
            <div><span class="text-xs text-slate-500">Base URL:</span><code class="block mt-1 px-3 py-2 bg-slate-100 rounded text-xs font-mono"><?= e(app_url('api/index.php')) ?></code></div>
            <div><span class="text-xs text-slate-500">Header:</span><code class="block mt-1 px-3 py-2 bg-slate-100 rounded text-xs font-mono">Authorization: Bearer YOUR_API_KEY</code></div>
        </div>
        <a href="/merchant/integration.php" class="inline-block mt-4 text-xs text-blue-600 font-medium hover:text-blue-700">Lihat Dokumentasi Lengkap &rarr;</a>
    </div>
</div>


<?php elseif ($activeTab === 'webhook'): ?>
<!-- ============ TAB: WEBHOOK ============ -->
<div class="max-w-2xl space-y-6">
    <!-- Current Config -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Konfigurasi Webhook</h3>
        <div class="space-y-4">
            <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-slate-700">Webhook URL</label>
                    <?php if ($hasPendingWebhook): ?><span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded">Pending</span><?php endif; ?>
                </div>
                <p class="text-sm font-mono text-slate-600 break-all"><?= e($project['webhook_url'] ?: '(belum diatur)') ?></p>
            </div>
            <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-slate-700">Redirect URL</label>
                    <?php if ($hasPendingRedirect): ?><span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded">Pending</span><?php endif; ?>
                </div>
                <p class="text-sm font-mono text-slate-600 break-all"><?= e($project['redirect_url'] ?: '(belum diatur)') ?></p>
            </div>
            <div class="p-4 bg-slate-50 rounded-lg border border-slate-200">
                <label class="text-sm font-medium text-slate-700 mb-1 block">IP Whitelist</label>
                <p class="text-sm font-mono text-slate-600 break-all whitespace-pre-line"><?= e($project['ip_whitelist'] ?: '(semua IP diizinkan)') ?></p>
            </div>
        </div>
    </div>

    <!-- Change Webhook URL -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">Ajukan Perubahan URL</h3>
        <?php if (!$hasPendingWebhook): ?>
        <form method="POST" action="?id=<?= e($projectId) ?>&tab=webhook" class="mb-6 pb-6 border-b border-slate-200">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="request_change">
            <input type="hidden" name="change_type" value="webhook_url">
            <div class="mb-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Webhook URL Baru</label>
                <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/webhook">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Alasan</label>
                <input type="text" name="reason" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Migrasi server, update endpoint">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" onclick="return confirm('Ajukan perubahan webhook URL?')" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Ajukan Perubahan</button>
        </form>
        <?php else: ?>
        <div class="mb-6 pb-6 border-b border-slate-200">
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <p class="text-xs text-amber-700 font-medium">Perubahan webhook URL menunggu persetujuan admin.</p>
            </div>
        </div>
        <?php endif; ?>


        <?php if (!$hasPendingRedirect): ?>
        <form method="POST" action="?id=<?= e($projectId) ?>&tab=webhook">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="request_change">
            <input type="hidden" name="change_type" value="redirect_url">
            <div class="mb-3">
                <label class="block text-sm font-medium text-slate-700 mb-1">Redirect URL Baru</label>
                <input type="url" name="new_value" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://yourdomain.com/success">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Alasan</label>
                <input type="text" name="reason" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="mb-3">
                <label class="block text-xs text-slate-500 mb-1">Konfirmasi Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <button type="submit" onclick="return confirm('Ajukan perubahan redirect URL?')" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Ajukan Perubahan</button>
        </form>
        <?php else: ?>
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
            <p class="text-xs text-amber-700 font-medium">Perubahan redirect URL menunggu persetujuan admin.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- IP Whitelist -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">IP Whitelist</h3>
        <form method="POST" action="?id=<?= e($projectId) ?>&tab=webhook">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="update_ip_whitelist">
            <div class="mb-3">
                <textarea name="ip_whitelist" rows="4" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="103.10.10.1&#10;103.10.10.2/24"><?= e($project['ip_whitelist'] ?? '') ?></textarea>
                <p class="text-xs text-slate-400 mt-1">Satu IP per baris (mendukung CIDR). Kosongkan untuk mengizinkan semua IP.</p>
            </div>
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan IP Whitelist</button>
        </form>
    </div>

    <!-- Links -->
    <div class="bg-slate-50 rounded-xl border border-slate-200 p-4">
        <div class="flex items-center gap-4 text-sm">
            <a href="/merchant/integration.php?tab=test" class="text-blue-600 hover:text-blue-700 font-medium">Test Webhook &rarr;</a>
            <a href="/merchant/webhook-logs.php" class="text-blue-600 hover:text-blue-700 font-medium">Lihat Webhook Logs &rarr;</a>
            <a href="/merchant/integration.php?tab=docs" class="text-blue-600 hover:text-blue-700 font-medium">Dokumentasi &rarr;</a>
        </div>
    </div>
</div>


<?php elseif ($activeTab === 'whatsapp'): ?>
<!-- ============ TAB: WHATSAPP ============ -->
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-2">Integrasi WhatsApp</h3>
        <p class="text-sm text-slate-500 mb-6">Konfigurasi notifikasi WhatsApp untuk proyek ini. Notifikasi akan dikirim ke customer dan/atau admin saat pembayaran berhasil.</p>

        <form method="POST" action="?id=<?= e($projectId) ?>&tab=whatsapp" class="space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_wa_config">

            <!-- Provider -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Provider</label>
                <select name="provider" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    <?php
                    $providers = ['fonnte' => 'Fonnte', 'wablas' => 'Wablas', 'zenziva' => 'Zenziva', 'custom' => 'Custom'];
                    foreach ($providers as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($waConfig['provider'] ?? 'fonnte') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- API URL -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">API URL <span class="text-red-500">*</span></label>
                <input type="url" name="api_url" value="<?= e($waConfig['api_url'] ?? '') ?>" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://api.fonnte.com/send">
            </div>

            <!-- API Key -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">API Key <span class="text-red-500">*</span></label>
                <input type="text" name="api_key" value="<?= e($waConfig['api_key'] ?? '') ?>" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono" placeholder="Your WA API key">
            </div>

            <!-- API Secret (optional) -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">API Secret <span class="text-slate-400">(opsional)</span></label>
                <input type="text" name="api_secret" value="<?= e($waConfig['api_secret'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-mono">
            </div>

            <!-- Sender Number -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Pengirim</label>
                <input type="text" name="sender_number" value="<?= e($waConfig['sender_number'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="628xxxxxxxxxx">
            </div>

            <!-- Toggle: Active -->
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                <div>
                    <p class="text-sm font-medium text-slate-700">Aktifkan Notifikasi WA</p>
                    <p class="text-xs text-slate-500">Kirim pesan WhatsApp otomatis</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" <?= ($waConfig['is_active'] ?? 0) ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>


            <!-- Notification events -->
            <div class="space-y-3">
                <p class="text-sm font-medium text-slate-700">Kirim notifikasi saat:</p>
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="notify_on_payment" value="1" <?= ($waConfig['notify_on_payment'] ?? 0) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                    <span class="text-sm text-slate-600">Pembayaran berhasil</span>
                </label>
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="notify_on_withdrawal" value="1" <?= ($waConfig['notify_on_withdrawal'] ?? 0) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                    <span class="text-sm text-slate-600">Withdrawal berhasil</span>
                </label>
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="notify_on_expiry" value="1" <?= ($waConfig['notify_on_expiry'] ?? 0) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                    <span class="text-sm text-slate-600">Pembayaran expired</span>
                </label>
            </div>

            <!-- Admin number -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Admin (untuk notifikasi internal)</label>
                <input type="text" name="notify_admin_number" value="<?= e($waConfig['notify_admin_number'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="628xxxxxxxxxx">
                <p class="text-xs text-slate-400 mt-1">Nomor yang menerima notifikasi internal (penarikan, transaksi besar, dll).</p>
            </div>

            <!-- Message Templates -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Template Pesan Pembayaran</label>
                <textarea name="message_template_payment" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Hai {customer_name}, pembayaran {order_id} sebesar {amount} telah berhasil. Terima kasih!"><?= e($waConfig['message_template_payment'] ?? '') ?></textarea>
                <p class="text-xs text-slate-400 mt-1">Variabel: {customer_name}, {order_id}, {amount}, {status}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Template Pesan Withdrawal</label>
                <textarea name="message_template_withdrawal" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Withdrawal {amount} ke {bank_name} - {account_number} telah diproses."><?= e($waConfig['message_template_withdrawal'] ?? '') ?></textarea>
                <p class="text-xs text-slate-400 mt-1">Variabel: {amount}, {bank_name}, {account_number}, {status}</p>
            </div>

            <div class="pt-4 border-t border-slate-200">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan Konfigurasi WhatsApp</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<script>
function copyToClipboard(id) { navigator.clipboard.writeText(document.getElementById(id).value).then(()=>showToast('Copied!')); }
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
