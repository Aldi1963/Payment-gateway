<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireAdmin();

require_once base_path('app/Controllers/AdminController.php');
$controller = new AdminController();
$logs = $controller->auditLogs();
$pagination = paginate($logs, (int)($_GET['page'] ?? 1), 30);

$pageTitle = 'Log & Verifikasi';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>

<!-- Tab Navigation: Audit | Webhook Logs | Config Verify -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <a href="/admin/audit-logs.php" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 border-blue-600 text-blue-600">Audit Logs</a>
        <a href="/admin/webhook-logs.php" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">Webhook Logs</a>
        <a href="/admin/config-changes.php" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">Config Verify</a>
    </nav>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400"><p>Belum ada audit log.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Action</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Description</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Actor</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">IP</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Waktu</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $log): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700"><?= e($log['action']) ?></span></td>
                <td class="px-6 py-3 text-slate-600 max-w-sm truncate"><?= e($log['description']) ?></td>
                <td class="px-6 py-3 text-xs text-slate-500"><?= e($log['actor_role'] ?? 'system') ?></td>
                <td class="px-6 py-3 font-mono text-xs text-slate-400"><?= e($log['ip'] ?? '-') ?></td>
                <td class="px-6 py-3 text-xs text-slate-500"><?= format_date($log['created_at'], 'd/m H:i:s') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between">
        <p class="text-sm text-slate-500">Halaman <?= $pagination['current_page'] ?> dari <?= $pagination['total_pages'] ?></p>
        <div class="flex gap-1">
            <?php if ($pagination['has_prev']): ?><a href="?page=<?= $pagination['current_page']-1 ?>" class="px-3 py-1 rounded text-sm text-slate-600 hover:bg-slate-100">&laquo;</a><?php endif; ?>
            <?php if ($pagination['has_next']): ?><a href="?page=<?= $pagination['current_page']+1 ?>" class="px-3 py-1 rounded text-sm text-slate-600 hover:bg-slate-100">&raquo;</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
