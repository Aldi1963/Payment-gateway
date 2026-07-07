<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Services/ConfigChangeService.php');
$configService = new ConfigChangeService();
$merchantId = Auth::merchantId();

// Handle cancel
if (is_post()) {
    Auth::verifyCsrf();
    if (($_POST['_action'] ?? '') === 'cancel') {
        $result = $configService->cancel($_POST['change_id'] ?? '', $merchantId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('/merchant/config-changes.php');
    }
}

$allChanges = $configService->getHistoryByMerchant($merchantId);
$pagination = paginate($allChanges, (int)($_GET['page'] ?? 1), 15);

$pageTitle = 'Riwayat Perubahan Konfigurasi';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500"><?= $pagination['total'] ?> total perubahan</p>
        <a href="/merchant/webhook-settings.php" class="text-sm text-blue-600 hover:text-blue-700">&larr; Webhook Settings</a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <?php if (empty($pagination['data'])): ?>
        <div class="p-12 text-center text-slate-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="text-sm">Belum ada riwayat perubahan konfigurasi.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $change): ?>
            <div class="p-4 hover:bg-slate-50">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-medium text-slate-800"><?= e($change['change_label'] ?? $change['change_type']) ?></span>
                            <?php
                            $statusClass = match($change['status']) {
                                'pending' => 'bg-amber-100 text-amber-800',
                                'approved' => 'bg-emerald-100 text-emerald-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                'canceled' => 'bg-slate-100 text-slate-600',
                                'rolled_back' => 'bg-purple-100 text-purple-800',
                                default => 'bg-slate-100 text-slate-600',
                            };
                            ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>">
                                <?= ucfirst(str_replace('_', ' ', $change['status'])) ?>
                            </span>
                            <span class="text-xs text-slate-400">v<?= $change['version'] ?? 1 ?></span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2 text-xs">
                            <div>
                                <span class="text-slate-400">Nilai Lama:</span>
                                <p class="font-mono text-slate-600 truncate"><?= e($change['old_value'] ?: '(kosong)') ?></p>
                            </div>
                            <div>
                                <span class="text-slate-400">Nilai Baru:</span>
                                <p class="font-mono text-slate-800 truncate"><?= e($change['new_value'] ?: '(kosong)') ?></p>
                            </div>
                        </div>

                        <?php if (!empty($change['reason'])): ?>
                        <p class="text-xs text-slate-500 mt-1">Alasan: <?= e($change['reason']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($change['review_note'])): ?>
                        <p class="text-xs mt-1 <?= $change['status'] === 'rejected' ? 'text-red-600' : 'text-emerald-600' ?>">
                            Admin: <?= e($change['review_note']) ?>
                        </p>
                        <?php endif; ?>

                        <p class="text-xs text-slate-400 mt-1">
                            Diajukan: <?= format_date($change['created_at']) ?>
                            <?php if ($change['reviewed_at']): ?> · Ditinjau: <?= format_date($change['reviewed_at']) ?><?php endif; ?>
                        </p>
                    </div>

                    <?php if ($change['status'] === 'pending'): ?>
                    <form method="POST" onsubmit="return confirm('Batalkan perubahan ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="cancel">
                        <input type="hidden" name="change_id" value="<?= e($change['id']) ?>">
                        <button class="px-3 py-1.5 text-xs text-red-600 border border-red-200 rounded-lg hover:bg-red-50">Batalkan</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between">
            <p class="text-xs text-slate-500">Halaman <?= $pagination['current_page'] ?> dari <?= $pagination['total_pages'] ?></p>
            <div class="flex gap-1">
                <?php if ($pagination['has_prev']): ?><a href="?page=<?= $pagination['current_page']-1 ?>" class="px-3 py-1 rounded text-xs hover:bg-slate-100">&laquo;</a><?php endif; ?>
                <?php if ($pagination['has_next']): ?><a href="?page=<?= $pagination['current_page']+1 ?>" class="px-3 py-1 rounded text-xs hover:bg-slate-100">&raquo;</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
