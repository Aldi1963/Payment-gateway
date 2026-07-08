<?php
/**
 * Pembayaran - Consolidated Page
 * Tabs: Buat Baru, Daftar Pembayaran, Payment Links
 */
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();
$merchant = $controller->getMerchant();

$activeTab = $_GET['tab'] ?? 'list';
$validTabs = ['create', 'list', 'links'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'list';

// Handle create payment POST
if (is_post() && $activeTab === 'create') {
    Auth::verifyCsrf();
    $result = $controller->createPayment($_POST);
    if ($result['success']) {
        flash('success', $result['message']);
        $_SESSION['last_payment_url'] = $result['transaction']['payment_url'] ?? '';
        $_SESSION['last_order_id'] = $result['transaction']['order_id'] ?? '';
        redirect('/merchant/payments.php?tab=list');
    } else {
        flash('error', $result['message']);
    }
}

// Load transactions for list/links tabs
$transactions = [];
$pagination = null;
if ($activeTab === 'list') {
    $filters = [];
    if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
    if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
    $transactions = $controller->transactions($filters);
    $pagination = paginate($transactions, (int)($_GET['page'] ?? 1));
} elseif ($activeTab === 'links') {
    $allTx = $controller->transactions([]);
    $links = array_filter($allTx, fn($tx) => !empty($tx['payment_url']));
    $pagination = paginate(array_values($links), (int)($_GET['page'] ?? 1));
}

$pageTitle = 'Pembayaran';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<!-- Tab Navigation -->
<div class="border-b border-slate-200 mb-6">
    <nav class="flex gap-1 -mb-px overflow-x-auto">
        <?php
        $tabs = ['create' => 'Buat Baru', 'list' => 'Daftar Pembayaran', 'links' => 'Payment Links'];
        foreach ($tabs as $key => $label): ?>
        <a href="?tab=<?= $key ?>" class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors <?= $activeTab === $key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<?php if ($activeTab === 'create'): ?>
<!-- ============ TAB: BUAT BARU ============ -->
<?php if ($merchant['status'] !== 'active'): ?>
<div class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
    Merchant belum aktif. Anda tidak dapat membuat transaksi.
</div>
<?php endif; ?>

<div class="max-w-2xl">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <form method="POST" action="?tab=create" class="space-y-5">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Order ID <span class="text-slate-400">(opsional)</span></label>
                    <input type="text" name="order_id" value="<?= e($_POST['order_id'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Auto-generate jika kosong">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Amount (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" required min="1" value="<?= e($_POST['amount'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="10000">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Pembayaran</label>
                <input type="text" name="link_name" value="<?= e($_POST['link_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Pembayaran Pesanan #123">
            </div>

            <!-- Payment Channel Selection -->
            <div class="border-t border-slate-200 pt-4">
                <p class="text-sm font-medium text-slate-700 mb-3">Metode Pembayaran</p>
                <?php
                $channelManager = PaymentChannelManager::getInstance();
                $availableChannels = $channelManager->getEnabledChannels();
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php if (isset($availableChannels['qris'])): ?>
                    <label class="relative flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:border-blue-300 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 transition-all">
                        <input type="radio" name="payment_channel" value="qris" <?= ($_POST['payment_channel'] ?? 'qris') === 'qris' ? 'checked' : '' ?> class="w-4 h-4 text-blue-600">
                        <div>
                            <p class="text-sm font-medium text-slate-800">QRIS</p>
                            <p class="text-xs text-slate-500">Semua e-wallet & mobile banking</p>
                        </div>
                    </label>
                    <?php endif; ?>
                    <?php if (isset($availableChannels['midtrans'])): ?>
                    <label class="relative flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:border-indigo-300 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 transition-all">
                        <input type="radio" name="payment_channel" value="midtrans" <?= ($_POST['payment_channel'] ?? '') === 'midtrans' ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600">
                        <div>
                            <p class="text-sm font-medium text-slate-800">Midtrans</p>
                            <p class="text-xs text-slate-500">VA, CC, GoPay, ShopeePay, dll</p>
                        </div>
                    </label>
                    <?php endif; ?>
                </div>

                <!-- Midtrans Payment Method (shown when midtrans selected) -->
                <div id="midtransMethodSection" class="mt-3 hidden">
                    <label class="block text-xs text-slate-500 mb-1">Pilih Metode (opsional, kosong = tampilkan semua)</label>
                    <select name="payment_method" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                        <option value="">Semua metode (Snap)</option>
                        <?php if (isset($availableChannels['midtrans'])):
                            $methods = $availableChannels['midtrans']->getSupportedMethods();
                            foreach ($methods as $m): ?>
                        <option value="<?= e($m['code']) ?>" <?= ($_POST['payment_method'] ?? '') === $m['code'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                <script>
                document.querySelectorAll('input[name="payment_channel"]').forEach(r => {
                    r.addEventListener('change', () => {
                        document.getElementById('midtransMethodSection').classList.toggle('hidden', r.value !== 'midtrans' || !r.checked);
                    });
                });
                // Init state
                if (document.querySelector('input[name="payment_channel"][value="midtrans"]:checked')) {
                    document.getElementById('midtransMethodSection').classList.remove('hidden');
                }
                </script>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <p class="text-sm font-medium text-slate-700 mb-3">Informasi Customer</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Nama</label>
                        <input type="text" name="customer_name" value="<?= e($_POST['customer_name'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="John Doe">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">WhatsApp</label>
                        <input type="text" name="customer_wa" value="<?= e($_POST['customer_wa'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="08123456789">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs text-slate-500 mb-1">Email</label>
                        <input type="email" name="customer_email" value="<?= e($_POST['customer_email'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="customer@email.com">
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <p class="text-sm font-medium text-slate-700 mb-3">URL Opsional</p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Webhook URL</label>
                        <input type="url" name="webhook_url" value="<?= e($_POST['webhook_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://domain.com/webhook">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">Redirect URL</label>
                        <input type="url" name="redirect_url" value="<?= e($_POST['redirect_url'] ?? '') ?>" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="https://domain.com/success">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Catatan Internal</label>
                <textarea name="note" rows="2" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Catatan untuk internal..."><?= e($_POST['note'] ?? '') ?></textarea>
            </div>

            <button type="submit" <?= $merchant['status'] !== 'active' ? 'disabled' : '' ?> class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white font-medium py-3 px-4 rounded-lg transition-colors">
                Buat Pembayaran
            </button>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'list'): ?>
<!-- ============ TAB: DAFTAR PEMBAYARAN ============ -->
<?php if (!empty($_SESSION['last_payment_url'])): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">
    <p class="font-medium mb-1">Payment link berhasil dibuat!</p>
    <div class="flex items-center gap-2">
        <input type="text" value="<?= e($_SESSION['last_payment_url']) ?>" readonly class="flex-1 px-3 py-1.5 bg-white border border-blue-200 rounded text-xs font-mono" id="paymentUrlInput">
        <button onclick="copyToClipboard('paymentUrlInput')" class="px-3 py-1.5 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">Copy</button>
    </div>
</div>
<?php unset($_SESSION['last_payment_url'], $_SESSION['last_order_id']); endif; ?>

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex items-center gap-2 flex-wrap">
        <input type="hidden" name="tab" value="list">
        <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Cari order ID..." class="px-4 py-2 border border-slate-300 rounded-lg text-sm w-52">
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">Semua</option>
            <option value="PENDING" <?= ($_GET['status'] ?? '') === 'PENDING' ? 'selected' : '' ?>>Pending</option>
            <option value="PAID" <?= ($_GET['status'] ?? '') === 'PAID' ? 'selected' : '' ?>>Paid</option>
            <option value="FAILED" <?= ($_GET['status'] ?? '') === 'FAILED' ? 'selected' : '' ?>>Failed</option>
            <option value="EXPIRED" <?= ($_GET['status'] ?? '') === 'EXPIRED' ? 'selected' : '' ?>>Expired</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm">Filter</button>
    </form>
    <a href="?tab=create" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">+ Buat Baru</a>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400"><p>Belum ada transaksi.</p></div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Order ID</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Customer</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Amount</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Tanggal</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $tx): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3 font-mono text-xs text-blue-600"><?= e($tx['order_id']) ?></td>
                <td class="px-6 py-3 text-slate-600"><?= e($tx['customer_name'] ?: '-') ?></td>
                <td class="px-6 py-3 font-medium"><?= format_currency($tx['amount']) ?></td>
                <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($tx['status']) ?>"><?= $tx['status'] ?></span></td>
                <td class="px-6 py-3 text-slate-500 text-xs"><?= format_date($tx['created_at'], 'd/m/Y H:i') ?></td>
                <td class="px-6 py-3">
                    <a href="/merchant/transaction-detail.php?id=<?= e($tx['id']) ?>" class="text-blue-600 text-xs font-medium hover:text-blue-700">Detail</a>
                    <?php if (!empty($tx['payment_url'])): ?>
                    <button onclick="copyText('<?= e($tx['payment_url']) ?>')" class="ml-2 text-slate-400 hover:text-slate-600 text-xs">Copy</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between">
        <p class="text-sm text-slate-500">Halaman <?= $pagination['current_page'] ?> dari <?= $pagination['total_pages'] ?></p>
        <div class="flex gap-1">
            <?php if ($pagination['has_prev']): ?><a href="?tab=list&page=<?= $pagination['current_page']-1 ?>&<?= http_build_query(array_filter(['status'=>$_GET['status']??'','search'=>$_GET['search']??''])) ?>" class="px-3 py-1 rounded text-sm text-slate-600 hover:bg-slate-100">&laquo;</a><?php endif; ?>
            <?php if ($pagination['has_next']): ?><a href="?tab=list&page=<?= $pagination['current_page']+1 ?>&<?= http_build_query(array_filter(['status'=>$_GET['status']??'','search'=>$_GET['search']??''])) ?>" class="px-3 py-1 rounded text-sm text-slate-600 hover:bg-slate-100">&raquo;</a><?php endif; ?>
        </div>
    </div>
    <?php endif; endif; ?>
</div>

<?php elseif ($activeTab === 'links'): ?>
<!-- ============ TAB: PAYMENT LINKS ============ -->
<div class="flex justify-end mb-4">
    <a href="?tab=create" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">+ Buat Payment Link</a>
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400">
        <p>Belum ada payment link. <a href="?tab=create" class="text-blue-600">Buat sekarang</a></p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-slate-100">
        <?php foreach ($pagination['data'] as $tx): ?>
        <div class="p-4 hover:bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs text-slate-600"><?= e($tx['order_id']) ?></span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($tx['status']) ?>"><?= $tx['status'] ?></span>
                </div>
                <p class="text-sm font-medium text-slate-800 mt-1"><?= e($tx['link_name'] ?: $tx['order_id']) ?> - <?= format_currency($tx['amount']) ?></p>
                <p class="text-xs text-slate-400 mt-0.5 truncate"><?= e($tx['payment_url']) ?></p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button onclick="copyText('<?= e($tx['payment_url']) ?>')" class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded text-xs font-medium hover:bg-blue-100">Copy Link</button>
                <a href="<?= e($tx['payment_url']) ?>" target="_blank" class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded text-xs hover:bg-slate-200">Open</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<script>
function copyText(text) { navigator.clipboard.writeText(text).then(()=>showToast('Link copied!')); }
function copyToClipboard(id) { const el=document.getElementById(id); navigator.clipboard.writeText(el.value).then(()=>showToast('Copied!')); }
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
