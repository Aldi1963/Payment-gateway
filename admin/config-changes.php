<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireRole(['super_admin', 'admin']);

require_once base_path('app/Services/ConfigChangeService.php');
$configService = new ConfigChangeService();

// Handle POST actions
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? '';
    $changeId = $_POST['change_id'] ?? '';
    $note = sanitize($_POST['review_note'] ?? '');

    if ($action === 'approve') {
        $result = $configService->approve($changeId, Auth::id(), $note);
        flash($result['success'] ? 'success' : 'error', $result['message']);
    } elseif ($action === 'reject') {
        if (empty($note)) {
            flash('error', 'Alasan penolakan wajib diisi.');
        } else {
            $result = $configService->reject($changeId, Auth::id(), $note);
            flash($result['success'] ? 'success' : 'error', $result['message']);
        }
    } elseif ($action === 'rollback') {
        $result = $configService->rollback($changeId, Auth::id(), $note);
        flash($result['success'] ? 'success' : 'error', $result['message']);
    }
    redirect('/admin/config-changes.php');
}

// Filter
$filter = $_GET['status'] ?? 'pending';
$filters = [];
if ($filter !== 'all') $filters['status'] = $filter;

$changes = $configService->getAll($filters);
$pendingCount = $configService->countPending();
$pagination = paginate($changes, (int)($_GET['page'] ?? 1), 15);

$pageTitle = 'Config Changes Verification';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>

<!-- Status Tabs -->
<div class="flex items-center gap-2 mb-6 overflow-x-auto">
    <?php
    $statusTabs = ['pending' => "Pending ({$pendingCount})", 'approved' => 'Approved', 'rejected' => 'Rejected', 'rolled_back' => 'Rolled Back', 'all' => 'Semua'];
    foreach ($statusTabs as $sk => $sl): ?>
    <a href="?status=<?= $sk ?>" class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors <?= $filter === $sk ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
        <?= $sl ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Changes List -->
<div class="space-y-4">
    <?php if (empty($pagination['data'])): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        <p class="text-sm">Tidak ada perubahan konfigurasi<?= $filter !== 'all' ? ' dengan status ' . $filter : '' ?>.</p>
    </div>
    <?php else: ?>
    <?php foreach ($pagination['data'] as $change): ?>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1 min-w-0">
                <!-- Header -->
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <span class="text-sm font-semibold text-slate-800"><?= e($change['change_label']) ?></span>
                    <?php
                    $badgeClass = match($change['status']) {
                        'pending' => 'bg-amber-100 text-amber-800',
                        'approved' => 'bg-emerald-100 text-emerald-800',
                        'rejected' => 'bg-red-100 text-red-800',
                        'canceled' => 'bg-slate-100 text-slate-600',
                        'rolled_back' => 'bg-purple-100 text-purple-800',
                        default => 'bg-slate-100 text-slate-600',
                    };
                    ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $badgeClass ?>"><?= ucfirst(str_replace('_', ' ', $change['status'])) ?></span>
                    <span class="text-xs text-slate-400">v<?= $change['version'] ?? 1 ?></span>
                </div>

                <!-- Merchant Info -->
                <p class="text-xs text-slate-500 mb-3">
                    Merchant: <strong><?= e($change['merchant_name'] ?? 'Unknown') ?></strong>
                    · Diajukan: <?= format_date($change['created_at']) ?>
                    · IP: <span class="font-mono"><?= e($change['ip'] ?? '-') ?></span>
                </p>

                <!-- Value Comparison -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div class="p-3 bg-red-50 border border-red-100 rounded-lg">
                        <p class="text-xs font-medium text-red-600 mb-1">Nilai Lama (Aktif)</p>
                        <p class="text-sm font-mono text-red-800 break-all"><?= e($change['old_value'] ?: '(kosong)') ?></p>
                    </div>
                    <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-lg">
                        <p class="text-xs font-medium text-emerald-600 mb-1">Nilai Baru (Diajukan)</p>
                        <p class="text-sm font-mono text-emerald-800 break-all"><?= e($change['new_value'] ?: '(kosong)') ?></p>
                    </div>
                </div>

                <?php if (!empty($change['reason'])): ?>
                <p class="text-xs text-slate-600 mb-2"><span class="text-slate-400">Alasan merchant:</span> <?= e($change['reason']) ?></p>
                <?php endif; ?>

                <?php if (!empty($change['review_note'])): ?>
                <p class="text-xs <?= $change['status'] === 'rejected' ? 'text-red-600' : 'text-emerald-600' ?> mb-2">
                    <span class="text-slate-400">Admin note:</span> <?= e($change['review_note']) ?>
                </p>
                <?php endif; ?>

                <?php if ($change['reviewed_at']): ?>
                <p class="text-xs text-slate-400">Ditinjau: <?= format_date($change['reviewed_at']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="shrink-0">
                <?php if ($change['status'] === 'pending'): ?>
                <div class="space-y-2 w-48">
                    <!-- Approve Form -->
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="approve">
                        <input type="hidden" name="change_id" value="<?= e($change['id']) ?>">
                        <input type="text" name="review_note" class="w-full px-3 py-1.5 border border-slate-300 rounded text-xs mb-1.5" placeholder="Catatan (opsional)">
                        <button type="submit" onclick="return confirm('Setujui dan terapkan perubahan ini?')" class="w-full px-3 py-2 bg-emerald-600 text-white rounded-lg text-xs font-medium hover:bg-emerald-700">
                            ✓ Approve & Apply
                        </button>
                    </form>
                    <!-- Reject Form -->
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="reject">
                        <input type="hidden" name="change_id" value="<?= e($change['id']) ?>">
                        <input type="text" name="review_note" required class="w-full px-3 py-1.5 border border-slate-300 rounded text-xs mb-1.5" placeholder="Alasan penolakan *">
                        <button type="submit" onclick="return confirm('Tolak perubahan ini?')" class="w-full px-3 py-2 bg-red-50 text-red-600 border border-red-200 rounded-lg text-xs font-medium hover:bg-red-100">
                            ✗ Reject
                        </button>
                    </form>
                </div>

                <?php elseif ($change['status'] === 'approved'): ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="rollback">
                    <input type="hidden" name="change_id" value="<?= e($change['id']) ?>">
                    <input type="text" name="review_note" required class="w-full px-3 py-1.5 border border-slate-300 rounded text-xs mb-1.5 w-40" placeholder="Alasan rollback *">
                    <button type="submit" onclick="return confirm('Rollback ke nilai lama?')" class="w-full px-3 py-2 bg-purple-50 text-purple-600 border border-purple-200 rounded-lg text-xs font-medium hover:bg-purple-100">
                        ↩ Rollback
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500"><?= $pagination['total'] ?> perubahan</p>
        <div class="flex gap-1">
            <?php if ($pagination['has_prev']): ?><a href="?status=<?= $filter ?>&page=<?= $pagination['current_page']-1 ?>" class="px-3 py-1.5 rounded text-sm hover:bg-slate-100">&laquo;</a><?php endif; ?>
            <?php if ($pagination['has_next']): ?><a href="?status=<?= $filter ?>&page=<?= $pagination['current_page']+1 ?>" class="px-3 py-1.5 rounded text-sm hover:bg-slate-100">&raquo;</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
