<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireRole(['super_admin', 'admin']);

require_once base_path('app/Services/FeeService.php');
require_once base_path('app/Services/AuditLogService.php');
require_once base_path('app/Repositories/TransactionRepository.php');

$feeService = new FeeService();
$auditService = new AuditLogService();

// Handle POST actions
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? '';

    if ($action === 'create_rule') {
        $config = buildConfigFromPost($_POST);
        $result = $feeService->createRule([
            'name' => $_POST['name'] ?? '',
            'rule_type' => $_POST['rule_type'] ?? 'transaction',
            'fee_type' => $_POST['fee_type'] ?? 'flat',
            'min_amount' => (int)($_POST['min_amount'] ?? 0),
            'max_amount' => (int)($_POST['max_amount'] ?? 0),
            'config' => $config,
            'priority' => (int)($_POST['priority'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
            'description' => $_POST['description'] ?? '',
            'merchant_id' => $_POST['merchant_id'] ?? null,
        ]);


        if ($result['success']) {
            $auditService->log(Auth::id(), Auth::role(), null, 'fee_rule_created',
                "Fee rule created: {$_POST['name']} [{$_POST['fee_type']}]",
                ['rule_id' => $result['rule']['id'], 'config' => $config]);
        }
        flash($result['success'] ? 'success' : 'error', $result['message']);

    } elseif ($action === 'update_rule') {
        $ruleId = $_POST['rule_id'] ?? '';
        $oldRule = $feeService->getRule($ruleId);
        $config = buildConfigFromPost($_POST);
        $result = $feeService->updateRule($ruleId, [
            'name' => $_POST['name'] ?? '',
            'fee_type' => $_POST['fee_type'] ?? 'flat',
            'min_amount' => (int)($_POST['min_amount'] ?? 0),
            'max_amount' => (int)($_POST['max_amount'] ?? 0),
            'config' => $config,
            'priority' => (int)($_POST['priority'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
            'description' => $_POST['description'] ?? '',
        ]);
        if ($result['success']) {
            $auditService->log(Auth::id(), Auth::role(), null, 'fee_rule_updated',
                "Fee rule updated: " . ($_POST['name'] ?? $ruleId),
                ['rule_id' => $ruleId, 'old' => $oldRule, 'new_config' => $config]);
        }
        flash($result['success'] ? 'success' : 'error', $result['message']);


    } elseif ($action === 'delete_rule') {
        $ruleId = $_POST['rule_id'] ?? '';
        $oldRule = $feeService->getRule($ruleId);
        $result = $feeService->deleteRule($ruleId);
        if ($result['success']) {
            $auditService->log(Auth::id(), Auth::role(), null, 'fee_rule_deleted',
                "Fee rule deleted: " . ($oldRule['name'] ?? $ruleId),
                ['rule_id' => $ruleId, 'old_rule' => $oldRule]);
        }
        flash($result['success'] ? 'success' : 'error', $result['message']);

    } elseif ($action === 'toggle_rule') {
        $ruleId = $_POST['rule_id'] ?? '';
        $result = $feeService->toggleRule($ruleId);
        $auditService->log(Auth::id(), Auth::role(), null, 'fee_rule_toggled',
            "Fee rule toggled: {$ruleId}", ['rule_id' => $ruleId]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
    }

    redirect('/admin/fee-management.php?tab=' . ($_POST['_tab'] ?? 'transaction'));
}

// Build config array from POST data
function buildConfigFromPost(array $post): array {
    $feeType = $post['fee_type'] ?? 'flat';
    return match($feeType) {
        'flat' => ['amount' => (int)($post['cfg_amount'] ?? 0)],
        'percentage' => [
            'percentage' => (float)($post['cfg_percentage'] ?? 0),
            'min_fee' => (int)($post['cfg_min_fee'] ?? 0),
            'max_fee' => (int)($post['cfg_max_fee'] ?? 0),
        ],
        'random' => [
            'min_fee' => (int)($post['cfg_min_fee'] ?? 0),
            'max_fee' => (int)($post['cfg_max_fee'] ?? 0),
            'step' => (int)($post['cfg_step'] ?? 1),
        ],
        'hybrid' => [
            'percentage' => (float)($post['cfg_percentage'] ?? 0),
            'flat_amount' => (int)($post['cfg_flat_amount'] ?? 0),
            'min_fee' => (int)($post['cfg_min_fee'] ?? 0),
            'max_fee' => (int)($post['cfg_max_fee'] ?? 0),
        ],
        'tier' => ['tiers' => json_decode($post['cfg_tiers_json'] ?? '[]', true) ?: []],
        default => [],
    };
}


// Simulation AJAX handler
if (isset($_GET['simulate'])) {
    $amount = (int)($_GET['amount'] ?? 0);
    $type = $_GET['type'] ?? 'transaction';
    $mid = $_GET['merchant_id'] ?? null;
    $result = $feeService->simulate($amount, $type, $mid ?: null);
    json_response($result);
}

// Page data
$activeTab = $_GET['tab'] ?? 'transaction';
$txRules = $feeService->getRules('transaction');
$wdRules = $feeService->getRules('withdrawal');
$stRules = $feeService->getRules('settlement');
$stats = $feeService->getStats();

// Fee revenue stats from transactions
$txRepo = new TransactionRepository();
$allTx = $txRepo->findAll();
$feeToday = 0; $feeMonth = 0; $feeTotal = 0;
$today = date('Y-m-d'); $month = date('Y-m');
foreach ($allTx as $tx) {
    if (($tx['status'] ?? '') !== 'PAID') continue;
    $fee = (int)($tx['fee'] ?? 0);
    $feeTotal += $fee;
    if (str_starts_with($tx['paid_at'] ?? $tx['created_at'] ?? '', $today)) $feeToday += $fee;
    if (str_starts_with($tx['paid_at'] ?? $tx['created_at'] ?? '', $month)) $feeMonth += $fee;
}

$pageTitle = 'Fee Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>


<!-- Fee Revenue Stats -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Fee Hari Ini</p>
        <p class="text-xl font-bold text-emerald-600"><?= format_currency($feeToday) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Fee Bulan Ini</p>
        <p class="text-xl font-bold text-blue-600"><?= format_currency($feeMonth) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Total Fee Keseluruhan</p>
        <p class="text-xl font-bold text-slate-800"><?= format_currency($feeTotal) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Active Rules</p>
        <p class="text-xl font-bold text-slate-800"><?= $stats['active_rules'] ?> <span class="text-sm font-normal text-slate-400">/ <?= $stats['total_rules'] ?></span></p>
    </div>
</div>

<!-- Tabs -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <?php foreach (['transaction'=>'Fee Transaksi','withdrawal'=>'Fee Withdrawal','settlement'=>'Fee Settlement','simulate'=>'Simulasi'] as $tk=>$tl): ?>
        <a href="?tab=<?= $tk ?>" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 <?= $activeTab === $tk ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            <?= $tl ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>


<?php if ($activeTab === 'simulate'): ?>
<!-- SIMULATION TAB -->
<div class="max-w-2xl">
<div class="bg-white rounded-xl border border-slate-200 p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-4">Simulasi Perhitungan Fee</h3>
    <p class="text-sm text-slate-500 mb-4">Preview fee sebelum diterapkan. Masukkan nominal untuk melihat fee yang dikenakan.</p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <div>
            <label class="block text-xs text-slate-500 mb-1">Tipe</label>
            <select id="simType" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                <option value="transaction">Transaksi</option>
                <option value="withdrawal">Withdrawal</option>
                <option value="settlement">Settlement</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1">Nominal (Rp)</label>
            <input type="number" id="simAmount" value="100000" min="1" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
        <div class="flex items-end">
            <button onclick="runSimulation()" class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Hitung Fee</button>
        </div>
    </div>
    <div id="simResult" class="hidden p-4 bg-slate-50 border border-slate-200 rounded-lg">
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-slate-500">Gross Amount:</span> <strong id="simGross">-</strong></div>
            <div><span class="text-slate-500">Fee Amount:</span> <strong id="simFee" class="text-red-600">-</strong></div>
            <div><span class="text-slate-500">Net Amount:</span> <strong id="simNet" class="text-emerald-600">-</strong></div>
            <div><span class="text-slate-500">Fee Type:</span> <strong id="simFeeType">-</strong></div>
            <div><span class="text-slate-500">Fee %:</span> <strong id="simPct">-</strong></div>
            <div><span class="text-slate-500">Matched Rule:</span> <span id="simRule" class="text-xs">-</span></div>
        </div>
    </div>
    <!-- Batch simulation -->
    <div class="border-t border-slate-200 mt-6 pt-4">
        <h4 class="text-sm font-semibold text-slate-700 mb-3">Simulasi Batch</h4>
        <button onclick="runBatchSimulation()" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-xs mb-3">Jalankan Batch</button>
        <div id="batchResult" class="overflow-x-auto"></div>
    </div>
</div>
</div>
<script>
async function runSimulation() {
    const type = document.getElementById('simType').value;
    const amount = document.getElementById('simAmount').value;
    const res = await fetch(`/admin/fee-management.php?simulate=1&type=${type}&amount=${amount}`);
    const data = await res.json();
    document.getElementById('simResult').classList.remove('hidden');
    document.getElementById('simGross').textContent = 'Rp ' + parseInt(data.amount).toLocaleString('id-ID');
    document.getElementById('simFee').textContent = 'Rp ' + parseInt(data.fee).toLocaleString('id-ID');
    document.getElementById('simNet').textContent = 'Rp ' + parseInt(data.net_amount).toLocaleString('id-ID');
    document.getElementById('simFeeType').textContent = data.fee_type;
    document.getElementById('simPct').textContent = data.fee_percentage + '%';
    document.getElementById('simRule').textContent = data.rule_id ? data.rule_id.substring(0,8)+'...' : 'Default';
}
async function runBatchSimulation() {
    const type = document.getElementById('simType').value;
    const amounts = [10000,50000,100000,250000,499999,500000,1000000,2500000,5000000];
    let html = '<table class="w-full text-xs"><thead class="bg-slate-100"><tr><th class="px-3 py-2 text-left">Amount</th><th class="px-3 py-2 text-left">Fee</th><th class="px-3 py-2 text-left">Net</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">%</th></tr></thead><tbody>';
    for (const amt of amounts) {
        const res = await fetch(`/admin/fee-management.php?simulate=1&type=${type}&amount=${amt}`);
        const d = await res.json();
        html += `<tr class="border-t border-slate-100"><td class="px-3 py-2">Rp ${amt.toLocaleString('id-ID')}</td><td class="px-3 py-2 text-red-600">Rp ${d.fee.toLocaleString('id-ID')}</td><td class="px-3 py-2 text-emerald-600">Rp ${d.net_amount.toLocaleString('id-ID')}</td><td class="px-3 py-2">${d.fee_type}</td><td class="px-3 py-2">${d.fee_percentage}%</td></tr>`;
    }
    html += '</tbody></table>';
    document.getElementById('batchResult').innerHTML = html;
}
</script>


<?php else: ?>
<!-- RULES LIST TAB -->
<?php
$currentRules = match($activeTab) {
    'withdrawal' => $wdRules,
    'settlement' => $stRules,
    default => $txRules,
};
$ruleTypeLabel = match($activeTab) {
    'withdrawal' => 'Withdrawal',
    'settlement' => 'Settlement',
    default => 'Transaksi',
};
?>

<div class="flex items-center justify-between mb-4">
    <h3 class="text-sm font-semibold text-slate-800">Fee Rules: <?= $ruleTypeLabel ?> (<?= count($currentRules) ?>)</h3>
    <button onclick="document.getElementById('createRuleModal').classList.remove('hidden')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">+ Tambah Rule</button>
</div>

<!-- Rules Table -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
<?php if (empty($currentRules)): ?>
    <div class="p-12 text-center text-slate-400">
        <p class="text-sm">Belum ada fee rule untuk <?= $ruleTypeLabel ?>.</p>
        <p class="text-xs mt-1">Sistem akan menggunakan default fee dari Settings.</p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Prioritas</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Nama</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Tipe Fee</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Range Amount</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Config</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($currentRules as $rule): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3"><span class="inline-flex items-center justify-center w-7 h-7 bg-blue-100 text-blue-700 rounded-full text-xs font-bold"><?= $rule['priority'] ?></span></td>
                <td class="px-4 py-3">
                    <p class="font-medium text-slate-800"><?= e($rule['name']) ?></p>
                    <?php if (!empty($rule['merchant_id'])): ?><span class="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded">Merchant</span><?php endif; ?>
                    <?php if (!empty($rule['description'])): ?><p class="text-xs text-slate-400 mt-0.5"><?= e(truncate($rule['description'], 40)) ?></p><?php endif; ?>
                </td>

                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                        <?= match($rule['fee_type']) { 'flat'=>'bg-slate-100 text-slate-700','percentage'=>'bg-blue-100 text-blue-700','random'=>'bg-amber-100 text-amber-700','hybrid'=>'bg-purple-100 text-purple-700','tier'=>'bg-emerald-100 text-emerald-700',default=>'bg-slate-100 text-slate-600' } ?>">
                        <?= ucfirst($rule['fee_type']) ?>
                    </span>
                </td>
                <td class="px-4 py-3 text-xs text-slate-600 font-mono">
                    <?php
                    $min = $rule['min_amount'] ?? 0;
                    $max = $rule['max_amount'] ?? 0;
                    if ($min && $max) echo format_currency($min) . ' - ' . format_currency($max);
                    elseif ($min) echo '≥ ' . format_currency($min);
                    elseif ($max) echo '≤ ' . format_currency($max);
                    else echo 'Semua nominal';
                    ?>
                </td>
                <td class="px-4 py-3 text-xs text-slate-600">
                    <?php
                    $cfg = $rule['config'] ?? [];
                    echo match($rule['fee_type']) {
                        'flat' => format_currency($cfg['amount'] ?? 0),
                        'percentage' => ($cfg['percentage'] ?? 0) . '%' . ($cfg['min_fee'] ? ' (min '.format_currency($cfg['min_fee']).')' : ''),
                        'random' => format_currency($cfg['min_fee'] ?? 0) . ' - ' . format_currency($cfg['max_fee'] ?? 0) . ($cfg['step'] > 1 ? ' step '.format_currency($cfg['step']) : ''),
                        'hybrid' => ($cfg['percentage'] ?? 0) . '% + ' . format_currency($cfg['flat_amount'] ?? 0),
                        'tier' => count($cfg['tiers'] ?? []) . ' tiers',
                        default => '-',
                    };
                    ?>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $rule['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                        <?= ucfirst($rule['status']) ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <form method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="toggle_rule">
                            <input type="hidden" name="_tab" value="<?= $activeTab ?>">
                            <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                            <button class="text-xs font-medium <?= $rule['status'] === 'active' ? 'text-amber-600' : 'text-emerald-600' ?>"><?= $rule['status'] === 'active' ? 'Off' : 'On' ?></button>
                        </form>
                        <button onclick="editRule(<?= e(json_encode($rule)) ?>)" class="text-blue-600 text-xs font-medium">Edit</button>
                        <form method="POST" class="inline" onsubmit="return confirm('Hapus rule ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_action" value="delete_rule">
                            <input type="hidden" name="_tab" value="<?= $activeTab ?>">
                            <input type="hidden" name="rule_id" value="<?= $rule['id'] ?>">
                            <button class="text-red-500 text-xs font-medium">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php endif; ?>


<!-- Create Rule Modal -->
<div id="createRuleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="this.parentElement.classList.add('hidden')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Tambah Fee Rule</h3>
        <form method="POST" class="space-y-4" id="createForm">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="create_rule">
            <input type="hidden" name="_tab" value="<?= $activeTab ?>">
            <input type="hidden" name="rule_type" value="<?= $activeTab !== 'simulate' ? $activeTab : 'transaction' ?>">

            <div><label class="block text-sm font-medium text-slate-700 mb-1">Nama Rule</label>
                <input type="text" name="name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="e.g. Random Fee Kecil"></div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Tipe Fee</label>
                    <select name="fee_type" id="createFeeType" onchange="toggleFeeConfig('create')" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                        <option value="flat">Flat</option>
                        <option value="percentage">Percentage</option>
                        <option value="random">Random</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="tier">Tier</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Prioritas</label>
                    <input type="number" name="priority" value="10" min="1" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-500 mb-1">Min Amount (Rp)</label>
                    <input type="number" name="min_amount" value="0" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="0 = tanpa batas bawah"></div>
                <div><label class="block text-xs text-slate-500 mb-1">Max Amount (Rp)</label>
                    <input type="number" name="max_amount" value="0" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="0 = tanpa batas atas"></div>
            </div>


            <!-- Dynamic config fields -->
            <div id="createCfgFlat"><label class="block text-xs text-slate-500 mb-1">Nominal Fee (Rp)</label>
                <input type="number" name="cfg_amount" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="1000"></div>

            <div id="createCfgPercentage" class="hidden space-y-3">
                <div><label class="block text-xs text-slate-500 mb-1">Percentage (%)</label>
                    <input type="number" name="cfg_percentage" step="0.01" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="0.7"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs text-slate-500 mb-1">Min Fee (Rp)</label>
                        <input type="number" name="cfg_min_fee" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="0"></div>
                    <div><label class="block text-xs text-slate-500 mb-1">Max Fee (Rp)</label>
                        <input type="number" name="cfg_max_fee" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="0"></div>
                </div>
            </div>

            <div id="createCfgRandom" class="hidden space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="block text-xs text-slate-500 mb-1">Min Fee (Rp)</label>
                        <input type="number" name="cfg_min_fee" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="200"></div>
                    <div><label class="block text-xs text-slate-500 mb-1">Max Fee (Rp)</label>
                        <input type="number" name="cfg_max_fee" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="400"></div>
                    <div><label class="block text-xs text-slate-500 mb-1">Kelipatan (Rp)</label>
                        <input type="number" name="cfg_step" value="10" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="10"></div>
                </div>
                <p class="text-xs text-slate-400">Fee akan diacak menggunakan random_int() antara Min - Max dengan kelipatan yang ditentukan.</p>
            </div>

            <div id="createCfgHybrid" class="hidden space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs text-slate-500 mb-1">Percentage (%)</label>
                        <input type="number" name="cfg_percentage" step="0.01" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                    <div><label class="block text-xs text-slate-500 mb-1">Flat Amount (Rp)</label>
                        <input type="number" name="cfg_flat_amount" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs text-slate-500 mb-1">Min Fee</label>
                        <input type="number" name="cfg_min_fee" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                    <div><label class="block text-xs text-slate-500 mb-1">Max Fee</label>
                        <input type="number" name="cfg_max_fee" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                </div>
            </div>

            <div id="createCfgTier" class="hidden">
                <label class="block text-xs text-slate-500 mb-1">Tiers (JSON)</label>
                <textarea name="cfg_tiers_json" rows="4" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-xs font-mono" placeholder='[{"min_amount":0,"type":"random","min_fee":200,"max_fee":400,"step":10},{"min_amount":500000,"type":"percentage","percentage":0.7}]'></textarea>
                <p class="text-xs text-slate-400 mt-1">Format JSON array. Setiap tier: min_amount, type (flat/percentage/random), dan konfigurasinya.</p>
            </div>


            <div><label class="block text-xs text-slate-500 mb-1">Deskripsi (opsional)</label>
                <input type="text" name="description" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Keterangan rule"></div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Buat Rule</button>
                <button type="button" onclick="document.getElementById('createRuleModal').classList.add('hidden')" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Rule Modal -->
<div id="editRuleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="this.parentElement.classList.add('hidden')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Edit Fee Rule</h3>
        <form method="POST" class="space-y-4" id="editForm">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="update_rule">
            <input type="hidden" name="_tab" value="<?= $activeTab ?>">
            <input type="hidden" name="rule_id" id="editRuleId">

            <div><label class="block text-sm font-medium text-slate-700 mb-1">Nama Rule</label>
                <input type="text" name="name" id="editName" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Tipe Fee</label>
                    <select name="fee_type" id="editFeeType" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                        <option value="flat">Flat</option>
                        <option value="percentage">Percentage</option>
                        <option value="random">Random</option>
                        <option value="hybrid">Hybrid</option>
                        <option value="tier">Tier</option>
                    </select></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Prioritas</label>
                    <input type="number" name="priority" id="editPriority" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-500 mb-1">Min Amount</label>
                    <input type="number" name="min_amount" id="editMinAmt" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
                <div><label class="block text-xs text-slate-500 mb-1">Max Amount</label>
                    <input type="number" name="max_amount" id="editMaxAmt" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            </div>

            <!-- Simplified: all config fields shown, fill relevant ones -->
            <div class="p-3 bg-slate-50 rounded-lg space-y-3">
                <p class="text-xs font-medium text-slate-600">Konfigurasi (isi sesuai tipe fee)</p>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs text-slate-500">Amount/Flat (Rp)</label><input type="number" name="cfg_amount" id="editCfgAmount" class="w-full px-3 py-2 border border-slate-300 rounded text-sm"></div>
                    <div><label class="block text-xs text-slate-500">Percentage (%)</label><input type="number" name="cfg_percentage" id="editCfgPct" step="0.01" class="w-full px-3 py-2 border border-slate-300 rounded text-sm"></div>
                    <div><label class="block text-xs text-slate-500">Min Fee (Rp)</label><input type="number" name="cfg_min_fee" id="editCfgMinFee" class="w-full px-3 py-2 border border-slate-300 rounded text-sm"></div>
                    <div><label class="block text-xs text-slate-500">Max Fee (Rp)</label><input type="number" name="cfg_max_fee" id="editCfgMaxFee" class="w-full px-3 py-2 border border-slate-300 rounded text-sm"></div>
                    <div><label class="block text-xs text-slate-500">Flat Amount (hybrid)</label><input type="number" name="cfg_flat_amount" id="editCfgFlat" class="w-full px-3 py-2 border border-slate-300 rounded text-sm"></div>
                    <div><label class="block text-xs text-slate-500">Step/Kelipatan</label><input type="number" name="cfg_step" id="editCfgStep" class="w-full px-3 py-2 border border-slate-300 rounded text-sm"></div>
                </div>
                <div><label class="block text-xs text-slate-500">Tiers JSON (tier type only)</label>
                    <textarea name="cfg_tiers_json" id="editCfgTiers" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded text-xs font-mono"></textarea></div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs text-slate-500 mb-1">Status</label>
                    <select name="status" id="editStatus" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                        <option value="active">Active</option><option value="inactive">Inactive</option>
                    </select></div>
                <div><label class="block text-xs text-slate-500 mb-1">Deskripsi</label>
                    <input type="text" name="description" id="editDesc" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
                <button type="button" onclick="document.getElementById('editRuleModal').classList.add('hidden')" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm">Batal</button>
            </div>
        </form>
    </div>
</div>


<script>
function toggleFeeConfig(prefix) {
    const type = document.getElementById(prefix + 'FeeType').value;
    const sections = ['Flat','Percentage','Random','Hybrid','Tier'];
    sections.forEach(s => {
        const el = document.getElementById(prefix + 'Cfg' + s);
        if (el) el.classList.toggle('hidden', s.toLowerCase() !== type);
    });
}

function editRule(rule) {
    document.getElementById('editRuleModal').classList.remove('hidden');
    document.getElementById('editRuleId').value = rule.id;
    document.getElementById('editName').value = rule.name;
    document.getElementById('editFeeType').value = rule.fee_type;
    document.getElementById('editPriority').value = rule.priority;
    document.getElementById('editMinAmt').value = rule.min_amount || 0;
    document.getElementById('editMaxAmt').value = rule.max_amount || 0;
    document.getElementById('editStatus').value = rule.status;
    document.getElementById('editDesc').value = rule.description || '';
    // Config
    const cfg = rule.config || {};
    document.getElementById('editCfgAmount').value = cfg.amount || 0;
    document.getElementById('editCfgPct').value = cfg.percentage || 0;
    document.getElementById('editCfgMinFee').value = cfg.min_fee || 0;
    document.getElementById('editCfgMaxFee').value = cfg.max_fee || 0;
    document.getElementById('editCfgFlat').value = cfg.flat_amount || 0;
    document.getElementById('editCfgStep').value = cfg.step || 1;
    document.getElementById('editCfgTiers').value = cfg.tiers ? JSON.stringify(cfg.tiers, null, 2) : '';
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
