<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireRole(['super_admin', 'admin', 'finance']);

require_once base_path('app/Controllers/AdminController.php');
$controller = new AdminController();

if (is_post()) {
    Auth::verifyCsrf();
    $wdId = $_POST['withdrawal_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $note = sanitize($_POST['admin_note'] ?? '');
    $result = $controller->processWithdrawal($wdId, $action, $note);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect('/admin/withdrawals.php');
}

$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
$withdrawals = $controller->withdrawals($filters);
$pagination = paginate($withdrawals, (int)($_GET['page'] ?? 1));

$pageTitle = 'Dana';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>

<!-- Tab Navigation: Withdrawals | Settlements -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <a href="/admin/withdrawals.php" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 border-blue-600 text-blue-600">Withdrawals</a>
        <a href="/admin/settlements.php" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">Settlements</a>
    </nav>
</div>

<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex items-center gap-2">
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">Semua Status</option>
            <?php foreach (['PENDING','REVIEWING','APPROVED','PROCESSING','SUCCESS','FAILED','REJECTED','CANCELED'] as $s): ?>
            <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm">Filter</button>
    </form>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400"><p>Tidak ada withdrawal.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">ID</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Amount</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Bank</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Tanggal</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $wd): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-3 font-mono text-xs"><?= substr($wd['id'], 0, 8) ?>...</td>
                    <td class="px-6 py-3 font-medium"><?= format_currency($wd['amount']) ?></td>
                    <td class="px-6 py-3">
                        <p class="text-slate-800"><?= e($wd['bank_name']) ?></p>
                        <p class="text-xs text-slate-400"><?= e($wd['account_number']) ?> - <?= e($wd['account_name']) ?></p>
                    </td>
                    <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($wd['status']) ?>"><?= $wd['status'] ?></span></td>
                    <td class="px-6 py-3 text-slate-500 text-xs"><?= format_date($wd['created_at'], 'd/m/Y H:i') ?></td>
                    <td class="px-6 py-3">
                        <?php if (in_array($wd['status'], ['PENDING', 'REVIEWING'])): ?>
                        <div class="flex gap-1">
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="withdrawal_id" value="<?= $wd['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-xs hover:bg-emerald-200">Approve</button>
                            </form>
                            <form method="POST" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="withdrawal_id" value="<?= $wd['id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">Reject</button>
                            </form>
                        </div>
                        <?php elseif ($wd['status'] === 'APPROVED'): ?>
                        <form method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="withdrawal_id" value="<?= $wd['id'] ?>">
                            <input type="hidden" name="action" value="success">
                            <button class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200">Mark Success</button>
                        </form>
                        <?php else: ?>
                        <span class="text-xs text-slate-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
