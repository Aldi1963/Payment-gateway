<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();

if (is_post() && ($_POST['action'] ?? '') === 'cancel') {
    Auth::verifyCsrf();
    $result = $controller->cancelWithdrawal($_POST['withdrawal_id'] ?? '');
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect('/merchant/withdraw-history.php');
}

$withdrawals = $controller->withdrawalHistory();
$pagination = paginate($withdrawals, (int)($_GET['page'] ?? 1));

$pageTitle = 'Riwayat Penarikan';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="flex justify-end mb-4">
    <a href="/merchant/withdraw.php" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">+ Tarik Dana</a>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400"><p>Belum ada riwayat penarikan.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Amount</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Bank</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Rekening</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Tanggal</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $wd): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3 font-medium"><?= format_currency($wd['amount']) ?></td>
                <td class="px-6 py-3"><?= e($wd['bank_name']) ?></td>
                <td class="px-6 py-3 text-xs"><p><?= e($wd['account_number']) ?></p><p class="text-slate-400"><?= e($wd['account_name']) ?></p></td>
                <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($wd['status']) ?>"><?= $wd['status'] ?></span></td>
                <td class="px-6 py-3 text-xs text-slate-500"><?= format_date($wd['created_at'], 'd/m/Y H:i') ?></td>
                <td class="px-6 py-3">
                    <?php if ($wd['status'] === 'PENDING'): ?>
                    <form method="POST" onsubmit="return confirm('Batalkan penarikan ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="withdrawal_id" value="<?= $wd['id'] ?>">
                        <button class="text-xs text-red-600 hover:text-red-700 font-medium">Batalkan</button>
                    </form>
                    <?php else: ?><span class="text-xs text-slate-400">-</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
