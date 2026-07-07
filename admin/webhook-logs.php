<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireAdmin();

require_once base_path('app/Controllers/AdminController.php');
$controller = new AdminController();
$logs = $controller->webhookLogs();
$pagination = paginate($logs, (int)($_GET['page'] ?? 1));

$pageTitle = 'Webhook Logs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <p>Belum ada webhook event.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Message</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">IP</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Waktu</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Payload</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $log): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $log['status'] === 'success' ? 'bg-emerald-100 text-emerald-800' : ($log['status'] === 'invalid_signature' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') ?>">
                        <?= e($log['status']) ?>
                    </span>
                </td>
                <td class="px-6 py-3 text-slate-600 max-w-xs truncate"><?= e($log['message']) ?></td>
                <td class="px-6 py-3 font-mono text-xs text-slate-500"><?= e($log['ip']) ?></td>
                <td class="px-6 py-3 text-xs text-slate-500"><?= format_date($log['created_at'], 'd/m H:i:s') ?></td>
                <td class="px-6 py-3">
                    <button onclick="showPayload(this)" data-payload="<?= e($log['payload']) ?>" class="text-xs text-blue-600 hover:text-blue-700 font-medium">View</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Payload Modal -->
<div id="payloadModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="document.getElementById('payloadModal').classList.add('hidden')"></div>
    <div class="relative bg-slate-900 rounded-2xl shadow-xl w-full max-w-2xl max-h-[70vh] overflow-auto p-6">
        <h3 class="text-sm font-medium text-slate-400 mb-3">Webhook Payload</h3>
        <pre id="payloadContent" class="text-sm text-emerald-400 font-mono whitespace-pre-wrap break-all"></pre>
        <button onclick="document.getElementById('payloadModal').classList.add('hidden')" class="absolute top-4 right-4 text-slate-500 hover:text-slate-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>

<script>
function showPayload(btn) {
    const payload = btn.dataset.payload;
    try {
        const formatted = JSON.stringify(JSON.parse(payload), null, 2);
        document.getElementById('payloadContent').textContent = formatted;
    } catch(e) {
        document.getElementById('payloadContent').textContent = payload;
    }
    document.getElementById('payloadModal').classList.remove('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
