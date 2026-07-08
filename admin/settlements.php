<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireRole(['super_admin', 'admin', 'finance']);

require_once base_path('app/Controllers/AdminController.php');
require_once base_path('app/Repositories/MerchantRepository.php');
$controller = new AdminController();
$merchantRepo = new MerchantRepository();

if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $result = $controller->createSettlement($_POST['merchant_id'] ?? '', $_POST['period'] ?? '');
        flash($result['success'] ? 'success' : 'error', $result['message']);
    } else {
        $result = $controller->processSettlement($_POST['settlement_id'] ?? '', $action);
        flash($result['success'] ? 'success' : 'error', $result['message']);
    }
    redirect('/admin/settlements.php');
}

$settlements = $controller->settlements();
$merchants = $merchantRepo->findActive();
$pagination = paginate($settlements, (int)($_GET['page'] ?? 1));

$pageTitle = 'Dana';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>

<!-- Tab Navigation: Withdrawals | Settlements -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <a href="/admin/withdrawals.php" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">Withdrawals</a>
        <a href="/admin/settlements.php" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 border-blue-600 text-blue-600">Settlements</a>
    </nav>
</div>

<!-- Create Settlement -->
<div class="bg-white rounded-xl border border-slate-200 p-6 mb-6">
    <h3 class="text-sm font-semibold text-slate-800 mb-3">Buat Settlement Baru</h3>
    <form method="POST" class="flex flex-wrap gap-3 items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Merchant</label>
            <select name="merchant_id" required class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                <option value="">Pilih Merchant</option>
                <?php foreach ($merchants as $m): ?>
                <option value="<?= $m['id'] ?>"><?= e($m['business_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Periode</label>
            <input type="month" name="period" value="<?= date('Y-m') ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Buat Settlement</button>
    </form>
</div>

<!-- Settlements Table -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400"><p>Belum ada settlement.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Periode</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Tx</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Gross</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Fee</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Net</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $s): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3 font-medium"><?= e($s['period']) ?></td>
                <td class="px-6 py-3"><?= $s['total_transactions'] ?></td>
                <td class="px-6 py-3"><?= format_currency($s['total_gross']) ?></td>
                <td class="px-6 py-3 text-slate-500"><?= format_currency($s['total_fee']) ?></td>
                <td class="px-6 py-3 font-medium text-emerald-600"><?= format_currency($s['total_net']) ?></td>
                <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($s['status']) ?>"><?= $s['status'] ?></span></td>
                <td class="px-6 py-3">
                    <?php if ($s['status'] === 'PENDING'): ?>
                    <form method="POST" class="inline"><?= csrf_field() ?><input type="hidden" name="settlement_id" value="<?= $s['id'] ?>"><input type="hidden" name="action" value="approve"><button class="text-xs text-blue-600 hover:text-blue-700 font-medium">Approve</button></form>
                    <?php elseif ($s['status'] === 'APPROVED'): ?>
                    <form method="POST" class="inline"><?= csrf_field() ?><input type="hidden" name="settlement_id" value="<?= $s['id'] ?>"><input type="hidden" name="action" value="complete"><button class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Complete</button></form>
                    <?php else: ?><span class="text-xs text-slate-400">-</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
