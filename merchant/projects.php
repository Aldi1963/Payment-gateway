<?php
/**
 * Proyek - Daftar & Kelola Proyek
 * 
 * Halaman ini hanya untuk:
 * - Melihat daftar proyek
 * - Membuat proyek baru
 * - Switch proyek aktif
 * 
 * Semua konfigurasi proyek (webhook, API, WA, dll) ada di project-settings.php
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();

// Handle POST actions
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_project') {
        $result = $controller->createProject([
            'name' => $_POST['name'] ?? '',
            'webhook_url' => $_POST['webhook_url'] ?? '',
            'ip_whitelist' => $_POST['ip_whitelist'] ?? '',
            'phone' => $_POST['phone'] ?? '',
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
    } elseif ($action === 'switch_project') {
        $result = $controller->switchProject($_POST['merchant_id'] ?? '');
        flash($result['success'] ? 'success' : 'error', $result['message']);
    }
    redirect('/merchant/projects.php');
}

$projects = $controller->listProjects();
$activeId = Auth::merchantId();
$isMigrated = $controller->projectsMigrated();

// Stats
$totalProjects = count($projects);
$activeProjects = count(array_filter($projects, fn($p) => $p['status'] === 'active'));
$pendingProjects = count(array_filter($projects, fn($p) => $p['status'] === 'pending'));
$productionProjects = count(array_filter($projects, fn($p) => ($p['mode'] ?? 'sandbox') === 'production'));

$pageTitle = 'Proyek';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';

// Status label helper
function project_status_label(string $status): string {
    return match($status) {
        'active' => 'Aktif',
        'pending' => 'Menunggu Verifikasi',
        'suspended' => 'Ditangguhkan',
        'rejected' => 'Ditolak',
        default => ucfirst($status),
    };
}

function project_status_icon(string $status): string {
    return match($status) {
        'active' => '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
        'pending' => '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>',
        'suspended' => '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM7 8a1 1 0 012 0v4a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v4a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        default => '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
    };
}
?>


<?php if (!$isMigrated): ?>
<div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-xl">
    <div class="flex items-start gap-3">
        <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-amber-800">Migrasi Database Diperlukan</p>
            <p class="text-sm text-amber-700 mt-1">Fitur multi-proyek membutuhkan migrasi database. Saat ini berjalan dalam mode toko tunggal.</p>
            <code class="mt-2 inline-block px-3 py-1.5 bg-amber-100 text-amber-900 rounded-lg text-xs font-mono">php scripts/migrate.php</code>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h3 class="text-2xl font-bold text-slate-900">Proyek</h3>
        <p class="text-sm text-slate-500 mt-1">Kelola toko dan proyek pembayaran Anda</p>
    </div>
    <?php if ($isMigrated): ?>
    <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl text-sm font-semibold hover:from-blue-700 hover:to-indigo-700 shadow-lg shadow-blue-500/25 transition-all hover:shadow-blue-500/40 whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Buat Proyek
    </button>
    <?php else: ?>
    <button disabled title="Jalankan migrasi database terlebih dahulu" class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-200 text-slate-400 rounded-xl text-sm font-semibold cursor-not-allowed whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Buat Proyek
    </button>
    <?php endif; ?>
</div>


<!-- Stats Overview -->
<?php if ($totalProjects > 0): ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0v-4a1 1 0 011-1h2a1 1 0 011 1v4"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-slate-900"><?= $totalProjects ?></p>
                <p class="text-xs text-slate-500">Total Proyek</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-emerald-600"><?= $activeProjects ?></p>
                <p class="text-xs text-slate-500">Aktif</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-amber-600"><?= $pendingProjects ?></p>
                <p class="text-xs text-slate-500">Pending</p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
            <div>
                <p class="text-2xl font-bold text-indigo-600"><?= $productionProjects ?></p>
                <p class="text-xs text-slate-500">Production</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Projects List -->
<?php if (empty($projects)): ?>
<div class="bg-white rounded-2xl border border-slate-200 p-12 text-center">
    <div class="w-20 h-20 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
        <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0v-4a1 1 0 011-1h2a1 1 0 011 1v4"/></svg>
    </div>
    <h4 class="text-lg font-semibold text-slate-800 mb-2">Belum Ada Proyek</h4>
    <p class="text-sm text-slate-500 mb-6 max-w-sm mx-auto">Mulai dengan membuat proyek pertama Anda. Setiap proyek memiliki API key, webhook, dan konfigurasi sendiri.</p>
    <?php if ($isMigrated): ?>
    <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Buat Proyek Pertama
    </button>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="grid gap-4">
    <?php foreach ($projects as $p): ?>
    <div class="group bg-white rounded-xl border <?= $p['id'] === $activeId ? 'border-blue-200 ring-2 ring-blue-100' : 'border-slate-200 hover:border-slate-300' ?> transition-all duration-200 overflow-hidden">
        <div class="p-5">
            <div class="flex items-start justify-between gap-4">
                <!-- Left: Project info -->
                <div class="flex items-start gap-4 min-w-0 flex-1">
                    <div class="w-12 h-12 bg-gradient-to-br <?= $p['id'] === $activeId ? 'from-blue-500 to-indigo-600' : 'from-slate-600 to-slate-700' ?> rounded-xl flex items-center justify-center text-white font-bold text-lg flex-shrink-0 shadow-sm">
                        <?= strtoupper(substr($p['business_name'], 0, 1)) ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h4 class="text-base font-semibold text-slate-900 truncate"><?= e($p['business_name']) ?></h4>
                            <?php if ($p['id'] === $activeId): ?>
                            <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full font-semibold uppercase tracking-wide">
                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse"></span>
                                Aktif
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-slate-500 font-mono mt-1"><?= e($p['slug']) ?></p>
                        <?php if (!empty($p['rejection_reason']) && $p['status'] === 'rejected'): ?>
                        <p class="text-xs text-red-500 mt-1.5 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <?= e($p['rejection_reason']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>


                <!-- Right: Badges + Actions -->
                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium <?= ($p['mode'] ?? 'sandbox') === 'production' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-50 text-slate-600 border border-slate-200' ?>">
                            <?php if (($p['mode'] ?? 'sandbox') === 'production'): ?>
                            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                            <?php else: ?>
                            <span class="w-1.5 h-1.5 bg-slate-400 rounded-full"></span>
                            <?php endif; ?>
                            <?= ucfirst($p['mode'] ?? 'sandbox') ?>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium <?= status_badge_class($p['status']) ?>">
                            <?= project_status_icon($p['status']) ?>
                            <?= project_status_label($p['status']) ?>
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="/merchant/project-settings.php?id=<?= e($p['id']) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors border border-slate-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Pengaturan
                        </a>
                        <?php if ($p['id'] !== $activeId): ?>
                        <form method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="switch_project">
                            <input type="hidden" name="merchant_id" value="<?= e($p['id']) ?>">
                            <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors border border-blue-200">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                Aktifkan
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>


<!-- Create Project Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeCreateModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto border border-slate-200">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100 sticky top-0 bg-white/95 backdrop-blur-sm z-10 rounded-t-2xl">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Buat Proyek Baru</h3>
                <p class="text-xs text-slate-500 mt-0.5">Isi detail proyek di bawah ini</p>
            </div>
            <button onclick="closeCreateModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Modal Body -->
        <form method="POST" class="p-6 space-y-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_project">

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Toko / Proyek <span class="text-red-500">*</span></label>
                <input type="text" name="name" required maxlength="255" class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors placeholder:text-slate-400" placeholder="Contoh: Toko Baju Keren">
                <p class="text-xs text-slate-400 mt-1.5">Nama ini akan ditampilkan pada halaman pembayaran pelanggan.</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Webhook URL <span class="text-slate-400 font-normal">(opsional)</span></label>
                <input type="url" name="webhook_url" class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors placeholder:text-slate-400 font-mono" placeholder="https://example.com/webhook">
                <p class="text-xs text-slate-400 mt-1.5">Notifikasi pembayaran akan dikirim ke URL ini. Bisa diatur nanti.</p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Whitelist IP <span class="text-slate-400 font-normal">(opsional)</span></label>
                <textarea name="ip_whitelist" rows="2" class="w-full px-4 py-3 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors font-mono placeholder:text-slate-400" placeholder="103.10.10.1&#10;103.10.10.2/24"></textarea>
                <p class="text-xs text-slate-400 mt-1.5">Satu IP per baris. Kosongkan untuk mengizinkan semua IP.</p>
            </div>


            <!-- Compliance notice -->
            <div class="p-4 bg-red-50 border border-red-100 rounded-xl">
                <div class="flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <div class="text-xs text-red-700 leading-relaxed">
                        <p class="font-semibold mb-1">Aktivitas Terlarang</p>
                        <p>Kami tidak mengizinkan pembayaran untuk judi online, ponzi/investasi bodong, scam, pornografi, dan kegiatan terlarang lainnya. Pelanggaran berakibat <strong>akun tersuspend dan saldo hangus</strong>.</p>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl">
                <div class="flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    <p class="text-xs text-blue-700">Proyek baru akan berstatus <strong>Menunggu Verifikasi</strong> sampai disetujui admin.</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeCreateModal()" class="flex-1 px-4 py-3 bg-slate-100 text-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-200 transition-colors border border-slate-200">Batal</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl text-sm font-semibold hover:from-blue-700 hover:to-indigo-700 shadow-lg shadow-blue-500/25 transition-all">Buat Proyek</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCreateModal();
});
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
