<?php
/**
 * Proyek - Daftar & Kelola Proyek
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();

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

$pageTitle = 'Proyek';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<!-- Page Title -->
<div class="mb-5">
    <h3 class="text-lg font-bold text-slate-900">Proyek</h3>
    <p class="text-[13px] text-slate-500 mt-0.5">Kelola toko dan proyek pembayaran Anda</p>
</div>

<?php if (!$isMigrated): ?>
<div class="mb-5 p-3 bg-amber-50 border border-amber-200 rounded-lg">
    <p class="text-xs text-amber-800 font-medium">Migrasi database diperlukan untuk fitur multi-proyek.</p>
    <code class="mt-1 inline-block text-[11px] text-amber-700 font-mono">php scripts/migrate.php</code>
</div>
<?php endif; ?>

<!-- Create Button -->
<?php if ($isMigrated): ?>
<button onclick="openCreateModal()" class="w-full mb-5 flex items-center justify-center gap-2 px-4 py-3 bg-slate-900 text-white rounded-xl text-sm font-medium active:scale-[0.98] transition-transform">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
    Buat Proyek Baru
</button>
<?php endif; ?>

<!-- Projects List -->
<?php if (empty($projects)): ?>
<div class="text-center py-16 px-4">
    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
    </div>
    <p class="text-sm font-medium text-slate-700">Belum ada proyek</p>
    <p class="text-xs text-slate-500 mt-1">Buat proyek pertama Anda untuk mulai menerima pembayaran.</p>
</div>
<?php else: ?>

<div class="space-y-3">
<?php foreach ($projects as $p):
    $isActive = $p['id'] === $activeId;
    $isProduction = ($p['mode'] ?? 'sandbox') === 'production';
?>
    <div class="bg-white rounded-xl border <?= $isActive ? 'border-blue-200 shadow-sm shadow-blue-100' : 'border-slate-200' ?> p-4 active:bg-slate-50 transition-colors">
        <!-- Row 1: Avatar + Name + Status -->
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg <?= $isActive ? 'bg-blue-600' : 'bg-slate-700' ?> flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                <?= strtoupper(mb_substr($p['business_name'], 0, 2)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <h4 class="text-sm font-semibold text-slate-900 truncate"><?= e($p['business_name']) ?></h4>
                    <?php if ($isActive): ?>
                    <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></span>
                    <?php endif; ?>
                </div>
                <p class="text-[11px] text-slate-400 font-mono truncate mt-0.5"><?= e($p['slug']) ?></p>
            </div>
        </div>

        <!-- Row 2: Badges -->
        <div class="flex items-center gap-2 mt-3 flex-wrap">
            <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-md font-medium <?= $isProduction ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                <span class="w-1.5 h-1.5 rounded-full <?= $isProduction ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                <?= $isProduction ? 'Production' : 'Sandbox' ?>
            </span>
            <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-md font-medium <?php
                echo match($p['status']) {
                    'active' => 'bg-green-50 text-green-700',
                    'pending' => 'bg-amber-50 text-amber-700',
                    'suspended' => 'bg-red-50 text-red-700',
                    default => 'bg-slate-100 text-slate-600',
                };
            ?>">
                <?php echo match($p['status']) {
                    'active' => 'Aktif',
                    'pending' => 'Pending',
                    'suspended' => 'Suspended',
                    'rejected' => 'Ditolak',
                    default => ucfirst($p['status']),
                }; ?>
            </span>
            <?php if ($isActive): ?>
            <span class="text-[11px] px-2 py-0.5 rounded-md font-medium bg-blue-50 text-blue-700">Dipilih</span>
            <?php endif; ?>
        </div>

        <!-- Row 3: Actions -->
        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100">
            <a href="/merchant/project-settings.php?id=<?= e($p['id']) ?>" class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-medium text-slate-700 bg-slate-50 rounded-lg border border-slate-200 active:bg-slate-100 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.212-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Pengaturan
            </a>
            <?php if (!$isActive): ?>
            <form method="POST" class="flex-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="switch_project">
                <input type="hidden" name="merchant_id" value="<?= e($p['id']) ?>">
                <button type="submit" class="w-full flex items-center justify-center gap-1.5 py-2 text-xs font-medium text-blue-700 bg-blue-50 rounded-lg border border-blue-200 active:bg-blue-100 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                    Aktifkan
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($p['rejection_reason']) && $p['status'] === 'rejected'): ?>
        <p class="text-[11px] text-red-600 mt-2 bg-red-50 p-2 rounded-lg"><?= e($p['rejection_reason']) ?></p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>



<!-- Create Project Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/40" onclick="closeCreateModal()"></div>
    <div class="absolute inset-x-0 bottom-0 sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:max-w-md sm:w-full">
        <div class="bg-white rounded-t-2xl sm:rounded-2xl max-h-[85vh] overflow-y-auto shadow-xl">
            <!-- Header -->
            <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-white rounded-t-2xl">
                <h3 class="text-base font-bold text-slate-900">Buat Proyek Baru</h3>
                <button onclick="closeCreateModal()" class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Form -->
            <form method="POST" class="p-5 space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_project">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Toko / Proyek *</label>
                    <input type="text" name="name" required maxlength="255" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors" placeholder="Contoh: Toko Baju Keren">
                    <p class="text-[11px] text-slate-400 mt-1">Ditampilkan di halaman pembayaran pelanggan.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Webhook URL <span class="text-slate-400 font-normal">(opsional)</span></label>
                    <input type="url" name="webhook_url" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors" placeholder="https://example.com/webhook">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">IP Whitelist <span class="text-slate-400 font-normal">(opsional)</span></label>
                    <textarea name="ip_whitelist" rows="2" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-colors" placeholder="103.10.10.1"></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Satu IP per baris. Kosongkan = semua IP diizinkan.</p>
                </div>

                <!-- Warning -->
                <div class="p-3 bg-red-50 rounded-lg">
                    <p class="text-[11px] text-red-700 leading-relaxed"><span class="font-semibold">Dilarang:</span> judi online, ponzi, scam, pornografi. Pelanggaran = akun suspended & saldo hangus.</p>
                </div>

                <div class="p-3 bg-blue-50 rounded-lg">
                    <p class="text-[11px] text-blue-700">Proyek baru akan berstatus <strong>Pending</strong> sampai disetujui admin.</p>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeCreateModal()" class="flex-1 py-2.5 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg active:bg-slate-200 transition-colors">Batal</button>
                    <button type="submit" class="flex-1 py-2.5 text-sm font-medium text-white bg-slate-900 rounded-lg active:bg-slate-800 transition-colors">Buat</button>
                </div>
            </form>
        </div>
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
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCreateModal(); });
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
