<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireAdmin();

require_once base_path('app/Controllers/AdminController.php');
$controller = new AdminController();

// Handle POST actions
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';
    $merchantId = $_POST['merchant_id'] ?? '';
    
    if ($action === 'update_status') {
        $result = $controller->updateMerchantStatus($merchantId, $_POST['status'] ?? '');
        flash($result['success'] ? 'success' : 'error', $result['message']);
    } elseif ($action === 'update_fee') {
        $result = $controller->updateMerchantFee($merchantId, $_POST);
        flash($result['success'] ? 'success' : 'error', $result['message']);
    }
    redirect('/admin/merchants.php');
}

$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

$merchants = $controller->merchants($filters);
$pagination = paginate($merchants, (int)($_GET['page'] ?? 1));

$pageTitle = 'Merchants';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>

<!-- Toolbar -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex items-center gap-2 w-full sm:w-auto">
        <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Cari merchant..." class="px-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-full sm:w-64">
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">Semua Status</option>
            <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= ($_GET['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm hover:bg-slate-700">Filter</button>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
        <p>Belum ada merchant terdaftar.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-6 py-3 text-left font-medium">Bisnis</th>
                    <th class="px-6 py-3 text-left font-medium">Owner</th>
                    <th class="px-6 py-3 text-left font-medium">Status</th>
                    <th class="px-6 py-3 text-left font-medium">Fee</th>
                    <th class="px-6 py-3 text-left font-medium">Terdaftar</th>
                    <th class="px-6 py-3 text-left font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($pagination['data'] as $m): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3">
                        <p class="font-medium text-slate-800"><?= e($m['business_name']) ?></p>
                        <p class="text-xs text-slate-400"><?= e($m['email']) ?></p>
                    </td>
                    <td class="px-6 py-3 text-slate-600"><?= e($m['owner_name']) ?></td>
                    <td class="px-6 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($m['status']) ?>">
                            <?= ucfirst($m['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-3 text-slate-600 text-xs">
                        <?= $m['fee_type'] === 'percentage' ? $m['fee_value'] . '%' : ($m['fee_type'] === 'flat' ? format_currency($m['fee_value']) : $m['fee_value'] . '% + ' . format_currency($m['fee_flat'] ?? 0)) ?>
                    </td>
                    <td class="px-6 py-3 text-slate-500 text-xs"><?= format_date($m['created_at'], 'd/m/Y') ?></td>
                    <td class="px-6 py-3">
                        <button onclick="openMerchantModal('<?= e($m['id']) ?>', '<?= e($m['business_name']) ?>', '<?= e($m['status']) ?>', '<?= e($m['fee_type']) ?>', '<?= e($m['fee_value']) ?>', '<?= e($m['fee_flat'] ?? 0) ?>')" class="text-blue-600 hover:text-blue-700 text-xs font-medium">Kelola</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between">
        <p class="text-sm text-slate-500">Menampilkan <?= count($pagination['data']) ?> dari <?= $pagination['total'] ?> merchant</p>
        <div class="flex gap-1">
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <a href="?page=<?= $i ?>&<?= http_build_query(array_filter(['status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? ''])) ?>" class="px-3 py-1 rounded text-sm <?= $i === $pagination['current_page'] ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Merchant Modal -->
<div id="merchantModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeMerchantModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4" id="modalTitle">Kelola Merchant</h3>
        
        <!-- Status Form -->
        <form method="POST" class="mb-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="merchant_id" id="modalMerchantId">
            <label class="block text-sm font-medium text-slate-700 mb-1">Ubah Status</label>
            <div class="flex gap-2">
                <select name="status" id="modalStatus" class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="pending">Pending</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="rejected">Rejected</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Simpan</button>
            </div>
        </form>

        <!-- Fee Form -->
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_fee">
            <input type="hidden" name="merchant_id" id="modalMerchantId2">
            <label class="block text-sm font-medium text-slate-700 mb-1">Atur Fee</label>
            <div class="grid grid-cols-3 gap-2 mb-2">
                <select name="fee_type" id="modalFeeType" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    <option value="percentage">Percentage</option>
                    <option value="flat">Flat</option>
                    <option value="hybrid">Hybrid</option>
                </select>
                <input type="number" name="fee_value" id="modalFeeValue" step="0.01" class="px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Nilai">
                <input type="number" name="fee_flat" id="modalFeeFlat" step="1" class="px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Flat (hybrid)">
            </div>
            <button type="submit" class="w-full px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">Update Fee</button>
        </form>

        <button onclick="closeMerchantModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>

<script>
function openMerchantModal(id, name, status, feeType, feeValue, feeFlat) {
    document.getElementById('merchantModal').classList.remove('hidden');
    document.getElementById('modalTitle').textContent = 'Kelola: ' + name;
    document.getElementById('modalMerchantId').value = id;
    document.getElementById('modalMerchantId2').value = id;
    document.getElementById('modalStatus').value = status;
    document.getElementById('modalFeeType').value = feeType;
    document.getElementById('modalFeeValue').value = feeValue;
    document.getElementById('modalFeeFlat').value = feeFlat;
}
function closeMerchantModal() {
    document.getElementById('merchantModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
