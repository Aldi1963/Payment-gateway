<?php
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

$pageTitle = 'Proyek';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';

// Status label + badge helper
function project_status_label(string $status): string {
    return match($status) {
        'active' => 'Aktif',
        'pending' => 'Menunggu Verifikasi',
        'suspended' => 'Ditangguhkan',
        'rejected' => 'Ditolak',
        default => ucfirst($status),
    };
}
?>

<?php if (!$isMigrated): ?>
<!-- Migration warning banner -->
<div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
    <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div>
            <p class="text-sm font-semibold text-amber-800">Database belum dimigrasi</p>
            <p class="text-sm text-amber-700 mt-1">Fitur multi-proyek membutuhkan migrasi database. Saat ini aplikasi berjalan dalam mode toko tunggal (legacy). Jalankan perintah berikut di server:</p>
            <pre class="mt-2 px-3 py-2 bg-amber-100 text-amber-900 rounded text-xs font-mono">php scripts/migrate.php</pre>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Header + Create button -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h3 class="text-xl font-semibold text-slate-800">Proyek</h3>
        <p class="text-sm text-slate-500 mt-1">Kelola beberapa toko/proyek dalam satu akun. Tiap proyek punya API key, webhook, dan integrasi WhatsApp sendiri.</p>
    </div>
    <?php if ($isMigrated): ?>
    <button onclick="openCreateModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 whitespace-nowrap">
        + Buat Proyek
    </button>
    <?php else: ?>
    <button disabled title="Jalankan migrasi database terlebih dahulu" class="px-4 py-2 bg-slate-300 text-white rounded-lg text-sm font-medium cursor-not-allowed whitespace-nowrap">
        + Buat Proyek
    </button>
    <?php endif; ?>
</div>

<!-- Projects Table -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($projects)): ?>
    <div class="p-12 text-center text-slate-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0v-4a1 1 0 011-1h2a1 1 0 011 1v4"/></svg>
        <p>Belum ada proyek. Klik <strong>Buat Proyek</strong> untuk memulai.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-6 py-3 text-left font-medium">Nama</th>
                    <th class="px-6 py-3 text-left font-medium">Slug</th>
                    <th class="px-6 py-3 text-left font-medium">Status</th>
                    <th class="px-6 py-3 text-left font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($projects as $p): ?>
                <tr class="hover:bg-slate-50 <?= $p['id'] === $activeId ? 'bg-blue-50/50' : '' ?>">
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-800"><?= e($p['business_name']) ?></span>
                            <?php if ($p['id'] === $activeId): ?>
                            <span class="text-[10px] px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded font-medium">AKTIF</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($p['rejection_reason']) && $p['status'] === 'rejected'): ?>
                        <p class="text-xs text-red-500 mt-0.5">Alasan: <?= e($p['rejection_reason']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 font-mono text-xs text-slate-600"><?= e($p['slug']) ?></td>
                    <td class="px-6 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($p['status']) ?>">
                            <?= project_status_label($p['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-3">
                        <?php if ($p['id'] !== $activeId): ?>
                        <form method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="switch_project">
                            <input type="hidden" name="merchant_id" value="<?= e($p['id']) ?>">
                            <button type="submit" class="text-blue-600 hover:text-blue-700 text-xs font-medium">Pilih</button>
                        </form>
                        <?php else: ?>
                        <span class="text-xs text-slate-400">Sedang aktif</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Create Project Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeCreateModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 sticky top-0 bg-white">
            <h3 class="text-lg font-semibold text-slate-800">Buat Proyek</h3>
            <button onclick="closeCreateModal()" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_project">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Toko <span class="text-red-500">*</span></label>
                <input type="text" name="name" required maxlength="255" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Contoh: Toko Baju Keren">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Webhook URL <span class="text-slate-400">(opsional)</span></label>
                <input type="url" name="webhook_url" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="https://...">
                <p class="text-xs text-slate-400 mt-1">URL untuk menerima notifikasi pembayaran.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Whitelist IP <span class="text-slate-400">(opsional)</span></label>
                <textarea name="ip_whitelist" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 font-mono" placeholder="103.10.10.1&#10;103.10.10.2/24"></textarea>
                <p class="text-xs text-slate-400 mt-1">Satu IP per baris (mendukung CIDR). Kosongkan untuk mengizinkan semua IP.</p>
            </div>

            <!-- Compliance notice -->
            <div class="p-4 bg-red-50 border border-red-100 rounded-lg text-xs text-red-700 leading-relaxed">
                Kami tidak mengizinkan pembayaran untuk hal-hal ilegal seperti:
                <ul class="list-disc list-inside mt-1 space-y-0.5">
                    <li>judi online, game poker/gaple, dan sejenisnya</li>
                    <li>ponzi, investasi bodong, dan sejenisnya</li>
                    <li>scam, penipuan, dll</li>
                    <li>pornografi dan sejenisnya</li>
                    <li>kegiatan terlarang lain berdasarkan hukum negara dan agama</li>
                </ul>
                <p class="mt-2">Pelanggaran dapat berakibat akun tersuspend, hingga <strong>saldo hangus atau tidak dapat ditarik</strong>.</p>
            </div>

            <div class="p-3 bg-amber-50 border border-amber-100 rounded-lg text-xs text-amber-700">
                Proyek baru akan berstatus <strong>Menunggu Verifikasi</strong> hingga disetujui admin sebelum bisa menerima pembayaran.
            </div>

            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeCreateModal()" class="flex-1 px-4 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">Batal</button>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() { document.getElementById('createModal').classList.remove('hidden'); }
function closeCreateModal() { document.getElementById('createModal').classList.add('hidden'); }
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
