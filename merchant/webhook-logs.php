<?php
/**
 * Webhook Logs - Monitoring Delivery
 * 
 * Menampilkan riwayat pengiriman webhook untuk proyek aktif:
 * - Status delivery (success, pending, failed)
 * - HTTP response code & waktu respons
 * - Manual retry untuk yang gagal
 * - Detail payload & attempts
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Repositories/WebhookRepository.php');
require_once base_path('app/Repositories/WebhookRetryRepository.php');

$merchantId = Auth::merchantId();
$webhookRepo = new WebhookRepository();
$retryRepo = new WebhookRetryRepository();

// Handle manual retry
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'retry' && !empty($_POST['retry_id'])) {
        $retryId = $_POST['retry_id'] ?? '';
        // Reset status to pending for re-processing
        $retryRepo->update($retryId, [
            'status' => 'pending',
            'next_retry_at' => now(),
            'updated_at' => now(),
        ]);
        flash('success', 'Webhook dijadwalkan untuk retry.');
        redirect('/merchant/webhook-logs.php');
    }
}

// Get webhook events and retries for current merchant
$webhookEvents = $webhookRepo->findByMerchant($merchantId);
$webhookRetries = $retryRepo->findByMerchant($merchantId, 100);

// Pagination (simple)
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$total = count($webhookRetries);
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;
$pagedRetries = array_slice($webhookRetries, $offset, $perPage);

$pageTitle = 'Webhook Logs';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>


<!-- Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h3 class="text-xl font-semibold text-slate-800">Webhook Logs</h3>
        <p class="text-sm text-slate-500 mt-1">Monitor pengiriman webhook untuk proyek aktif. Webhook yang gagal bisa di-retry manual.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="/merchant/integration.php?tab=test" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 whitespace-nowrap">Test Webhook</a>
        <a href="/merchant/project-settings.php?id=<?= e($merchantId) ?>&tab=webhook" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200 whitespace-nowrap">Webhook Settings</a>
    </div>
</div>

<!-- Stats Summary -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
    <?php
    $totalDeliveries = count($webhookRetries);
    $successCount = count(array_filter($webhookRetries, fn($r) => ($r['status'] ?? '') === 'delivered'));
    $pendingCount = count(array_filter($webhookRetries, fn($r) => ($r['status'] ?? '') === 'pending'));
    $failedCount = count(array_filter($webhookRetries, fn($r) => ($r['status'] ?? '') === 'failed'));
    ?>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Total</p>
        <p class="text-2xl font-bold text-slate-800"><?= $totalDeliveries ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Berhasil</p>
        <p class="text-2xl font-bold text-emerald-600"><?= $successCount ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Pending</p>
        <p class="text-2xl font-bold text-amber-600"><?= $pendingCount ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Gagal</p>
        <p class="text-2xl font-bold text-red-600"><?= $failedCount ?></p>
    </div>
</div>


<!-- Webhook Deliveries Table -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagedRetries)): ?>
    <div class="p-12 text-center text-slate-400">
        <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
        <p class="text-sm">Belum ada webhook delivery. <a href="/merchant/integration.php?tab=test" class="text-blue-600 font-medium hover:underline">Kirim test webhook</a> untuk memulai.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="px-4 py-3 text-left font-medium">Status</th>
                    <th class="px-4 py-3 text-left font-medium">Event</th>
                    <th class="px-4 py-3 text-left font-medium">URL</th>
                    <th class="px-4 py-3 text-left font-medium">HTTP</th>
                    <th class="px-4 py-3 text-left font-medium">Attempts</th>
                    <th class="px-4 py-3 text-left font-medium">Waktu</th>
                    <th class="px-4 py-3 text-right font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($pagedRetries as $retry): ?>
                <?php
                    $status = $retry['status'] ?? 'unknown';
                    $statusClass = match($status) {
                        'delivered' => 'bg-emerald-100 text-emerald-800',
                        'pending' => 'bg-amber-100 text-amber-800',
                        'failed' => 'bg-red-100 text-red-800',
                        default => 'bg-slate-100 text-slate-600',
                    };
                    $payload = is_array($retry['payload'] ?? null) ? $retry['payload'] : json_decode($retry['payload'] ?? '{}', true);
                    $event = $payload['event'] ?? '-';
                    $url = $retry['webhook_url'] ?? $retry['url'] ?? '-';
                    $httpCode = $retry['last_http_code'] ?? $retry['http_code'] ?? '-';
                    $attempts = $retry['attempt_count'] ?? $retry['attempts'] ?? 0;
                    $maxAttempts = $retry['max_attempts'] ?? 5;
                    $createdAt = $retry['created_at'] ?? '';
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statusClass ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <code class="text-xs px-1.5 py-0.5 bg-slate-100 rounded"><?= e($event) ?></code>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-mono text-slate-600 truncate block max-w-[200px]" title="<?= e($url) ?>"><?= e($url) ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <?php if (is_numeric($httpCode) && $httpCode >= 200 && $httpCode < 300): ?>
                        <span class="text-xs font-mono text-emerald-700 font-medium"><?= $httpCode ?></span>
                        <?php elseif (is_numeric($httpCode) && $httpCode > 0): ?>
                        <span class="text-xs font-mono text-red-700 font-medium"><?= $httpCode ?></span>
                        <?php else: ?>
                        <span class="text-xs text-slate-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs text-slate-600"><?= $attempts ?>/<?= $maxAttempts ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs text-slate-500"><?= $createdAt ? format_date($createdAt) : '-' ?></span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="toggleDetail('detail-<?= e($retry['id'] ?? '') ?>')" class="text-xs text-blue-600 hover:text-blue-700 font-medium">Detail</button>
                            <?php if ($status === 'failed'): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Retry webhook ini?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="retry">
                                <input type="hidden" name="retry_id" value="<?= e($retry['id'] ?? '') ?>">
                                <button type="submit" class="text-xs text-amber-600 hover:text-amber-700 font-medium">Retry</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <!-- Detail Row (hidden) -->
                <tr id="detail-<?= e($retry['id'] ?? '') ?>" class="hidden bg-slate-50">
                    <td colspan="7" class="px-4 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-medium text-slate-500 mb-1">Payload:</p>
                                <pre class="text-xs font-mono bg-slate-900 text-emerald-300 rounded-lg p-3 overflow-x-auto max-h-32"><?= e(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-slate-500 mb-1">Attempts Log:</p>
                                <?php
                                $attemptsLog = is_array($retry['attempts_log'] ?? null) ? $retry['attempts_log'] : json_decode($retry['attempts_log'] ?? '[]', true);
                                if (!empty($attemptsLog)): ?>
                                <div class="space-y-1 max-h-32 overflow-y-auto">
                                    <?php foreach ($attemptsLog as $i => $attempt): ?>
                                    <div class="text-xs p-2 bg-white rounded border border-slate-200">
                                        <span class="font-medium">#<?= $i + 1 ?></span>
                                        <span class="ml-2 <?= ($attempt['http_code'] ?? 0) >= 200 && ($attempt['http_code'] ?? 0) < 300 ? 'text-emerald-600' : 'text-red-600' ?>">
                                            HTTP <?= $attempt['http_code'] ?? '-' ?>
                                        </span>
                                        <span class="ml-2 text-slate-400"><?= $attempt['time'] ?? $attempt['attempted_at'] ?? '' ?></span>
                                        <?php if (!empty($attempt['error'])): ?>
                                        <p class="text-red-500 mt-0.5"><?= e($attempt['error']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-xs text-slate-400">Belum ada attempt log.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between">
        <p class="text-xs text-slate-500">
            Menampilkan <?= $offset + 1 ?>-<?= min($offset + $perPage, $total) ?> dari <?= $total ?> delivery
        </p>
        <div class="flex gap-1">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100 border border-slate-200">&laquo; Prev</a>
            <?php endif; ?>
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="?page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-xs font-medium <?= $i === $page ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100 border border-slate-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100 border border-slate-200">Next &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function toggleDetail(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
