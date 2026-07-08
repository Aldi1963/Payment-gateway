<?php
/**
 * Branded Checkout / Payment Page
 * 
 * States:
 * 1. Method Selector - customer picks payment method (no channel selected yet)
 * 2. Payment Detail - shows VA/QR/deeplink after method selected
 * 3. Status Pages - PAID, EXPIRED, FAILED
 * 
 * URL: /pay.php?order_id=INV-XXXXX
 * Select: /pay.php?order_id=INV-XXXXX&select_method=BCAVA
 */

require_once __DIR__ . '/includes/init.php';
require_once base_path('app/Repositories/TransactionRepository.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Services/PaymentChannelManager.php');

$txRepo = new TransactionRepository();
$merchantRepo = new MerchantRepository();

// Find transaction
$txId = $_GET['id'] ?? '';
$orderId = $_GET['order_id'] ?? '';
$transaction = null;
if (!empty($txId)) $transaction = $txRepo->find($txId);
elseif (!empty($orderId)) $transaction = $txRepo->findByOrderId($orderId);

if (!$transaction) {
    http_response_code(404);
    $pageError = 'Transaksi tidak ditemukan.';
}

$merchant = null;
$expiryMinutes = 60;
$remainingSeconds = 0;

if ($transaction) {
    $merchant = $merchantRepo->find($transaction['merchant_id']);
    $expiryMinutes = (int)($merchant['payment_expiry_minutes'] ?? setting('payment_expiry_minutes', 60));
    $createdAt = strtotime($transaction['created_at']);
    $expiryTime = $createdAt + ($expiryMinutes * 60);
    $remainingSeconds = max(0, $expiryTime - time());

    // Auto-expire
    if ($remainingSeconds <= 0 && $transaction['status'] === 'PENDING') {
        $txRepo->update($transaction['id'], ['status' => 'EXPIRED', 'expired_at' => now(), 'updated_at' => now()]);
        $transaction['status'] = 'EXPIRED';
    }

    // Handle method selection (POST or GET)
    $selectMethod = $_GET['select_method'] ?? $_POST['select_method'] ?? '';
    if (!empty($selectMethod) && $transaction['status'] === 'PENDING' && empty($transaction['qr_url']) && empty($transaction['snap_token'])) {
        require_once base_path('app/Services/TransactionService.php');
        $txService = new TransactionService();

        // Determine channel from method code
        $upperMethod = strtoupper($selectMethod);
        if ($upperMethod === 'QRIS-A') {
            $channel = 'qris';
            $method = null;
        } else {
            $channel = 'midtrans';
            $method = $upperMethod;
        }

        $result = $txService->selectPaymentMethod($transaction['order_id'], $channel, $method);
        if ($result['success']) {
            $transaction = $txRepo->find($transaction['id']);
        } else {
            $selectError = $result['message'];
        }
    }
}

// AJAX status check
if (isset($_GET['check_status']) && $transaction) {
    header('Content-Type: application/json');
    $fresh = $txRepo->find($transaction['id']);
    echo json_encode([
        'status' => $fresh['status'] ?? 'UNKNOWN',
        'paid_at' => $fresh['paid_at'] ?? null,
        'redirect_url' => $fresh['redirect_url'] ?? null,
    ]);
    exit;
}

// Determine page state
$appName = setting('app_name', 'Clipku Pay');
$merchantName = $merchant['business_name'] ?? $appName;
$thankYouMsg = $merchant['thank_you_message'] ?? 'Terima kasih atas pembayaran Anda!';

// Check if method has been selected (has payment details)
$hasPaymentDetails = !empty($transaction['qr_url']) || !empty($transaction['snap_token']);
$isMidtrans = ($transaction['payment_channel'] ?? '') === 'midtrans';
$needsMethodSelection = $transaction && $transaction['status'] === 'PENDING' && !$hasPaymentDetails;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - <?= e($merchantName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="min-h-screen font-sans bg-gradient-to-b from-slate-100 to-slate-200 flex items-center justify-center p-4">
<div class="w-full max-w-md">


<?php if (!empty($pageError)): ?>
<!-- ERROR STATE -->
<div class="bg-white rounded-2xl shadow-xl p-8 text-center">
    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </div>
    <h1 class="text-xl font-bold text-slate-800 mb-2">Tidak Ditemukan</h1>
    <p class="text-slate-500"><?= e($pageError) ?></p>
</div>

<?php elseif ($transaction['status'] === 'PAID'): ?>
<!-- PAID STATE -->
<div class="bg-white rounded-2xl shadow-xl p-8 text-center">
    <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
    </div>
    <h1 class="text-2xl font-bold text-emerald-700 mb-2">Pembayaran Berhasil!</h1>
    <p class="text-slate-500 mb-6"><?= e($thankYouMsg) ?></p>
    <div class="bg-slate-50 rounded-xl p-4 text-left space-y-2 text-sm">
        <div class="flex justify-between"><span class="text-slate-500">Order</span><span class="font-mono font-medium"><?= e($transaction['order_id']) ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Jumlah</span><span class="font-bold text-emerald-600"><?= format_currency($transaction['amount']) ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Dibayar</span><span><?= format_date($transaction['paid_at'] ?? '') ?></span></div>
    </div>
</div>

<?php elseif ($transaction['status'] === 'EXPIRED'): ?>
<!-- EXPIRED STATE -->
<div class="bg-white rounded-2xl shadow-xl p-8 text-center">
    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <h1 class="text-xl font-bold text-slate-700 mb-2">Pembayaran Expired</h1>
    <p class="text-slate-500">Batas waktu pembayaran telah habis.</p>
</div>

<?php elseif ($transaction['status'] === 'FAILED'): ?>
<!-- FAILED STATE -->
<div class="bg-white rounded-2xl shadow-xl p-8 text-center">
    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    </div>
    <h1 class="text-xl font-bold text-red-700 mb-2">Pembayaran Gagal</h1>
    <p class="text-slate-500"><?= e($selectError ?? 'Transaksi gagal diproses.') ?></p>
</div>

<?php elseif ($needsMethodSelection): ?>
<!-- ============ METHOD SELECTOR ============ -->
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5 text-white text-center">
        <h1 class="text-lg font-bold"><?= e($merchantName) ?></h1>
        <p class="text-blue-200 text-sm mt-0.5"><?= e($transaction['link_name'] ?? 'Pembayaran') ?></p>
        <p class="text-2xl font-extrabold mt-2"><?= format_currency($transaction['amount']) ?></p>
    </div>

    <div class="p-5">
        <h2 class="text-lg font-bold text-slate-800 mb-4">Pilih Pembayaran</h2>

        <?php if (!empty($selectError)): ?>
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($selectError) ?></div>
        <?php endif; ?>

        <?php
        // Get all enabled channels and methods
        $channelManager = PaymentChannelManager::getInstance();
        $enabledChannels = $channelManager->getEnabledChannels();

        // Group methods by category
        $groups = [
            'qris' => ['label' => 'QRIS', 'methods' => []],
            'va' => ['label' => 'Virtual Account', 'methods' => []],
            'ewallet' => ['label' => 'E-Wallet', 'methods' => []],
            'store' => ['label' => 'Convenience Store', 'methods' => []],
        ];

        // Add QRIS option
        if (isset($enabledChannels['qris'])) {
            $groups['qris']['methods'][] = [
                'code' => 'QRIS-A',
                'name' => 'QRIS',
                'desc' => 'Scan QR dari semua e-wallet & mobile banking',
                'fee' => 'Fee 0.7%',
                'icon' => 'qris',
            ];
        }

        // Add Midtrans methods
        if (isset($enabledChannels['midtrans'])) {
            $midtransMethods = $enabledChannels['midtrans']->getSupportedMethods();
            foreach ($midtransMethods as $m) {
                $code = $m['code']; // Already simple: BCAVA, BNIVA, etc.
                if (in_array($code, ['BCAVA','BNIVA','BRIVA','PERMATAVA','CIMBVA','MANDIRI'])) {
                    $groups['va']['methods'][] = [
                        'code' => $code,
                        'name' => $m['name'],
                        'desc' => 'Min Rp 10.000',
                        'fee' => 'Fee Rp 4.000',
                        'icon' => $m['icon'],
                    ];
                } elseif ($code === 'MTQRIS') {
                    $groups['qris']['methods'][] = [
                        'code' => $code,
                        'name' => $m['name'],
                        'desc' => 'Scan QR dari semua e-wallet',
                        'fee' => 'Fee 0.7%',
                        'icon' => 'qris_mt',
                    ];
                } elseif (in_array($code, ['GOPAY','SHOPEEPAY'])) {
                    $groups['ewallet']['methods'][] = [
                        'code' => $code,
                        'name' => $m['name'],
                        'desc' => 'Deeplink ke aplikasi',
                        'fee' => 'Fee 2%',
                        'icon' => $m['icon'],
                    ];
                }
            }
        }
        ?>

        <div class="space-y-5">
        <?php foreach ($groups as $groupKey => $group):
            if (empty($group['methods'])) continue;
        ?>
            <!-- Group: <?= $group['label'] ?> -->
            <div>
                <div class="bg-slate-100 rounded-lg px-4 py-2 mb-2">
                    <h3 class="text-sm font-bold text-slate-700"><?= e($group['label']) ?></h3>
                </div>
                <div class="space-y-1">
                <?php foreach ($group['methods'] as $pm): ?>
                    <a href="?order_id=<?= e($transaction['order_id']) ?>&select_method=<?= e($pm['code']) ?>"
                       class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 hover:border-blue-400 hover:bg-blue-50/50 transition-all group">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-blue-100">
                            <?= get_payment_icon($pm['icon']) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 group-hover:text-blue-700"><?= e($pm['name']) ?></p>
                            <p class="text-xs text-slate-400"><?= e($pm['fee']) ?> · <?= e($pm['desc']) ?></p>
                        </div>
                        <svg class="w-4 h-4 text-slate-300 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- Timer -->
        <div class="mt-5 pt-4 border-t border-slate-100 text-center">
            <p class="text-xs text-slate-400">Batas waktu: <span class="font-mono font-medium text-slate-600" id="countdown"><?= gmdate('H:i:s', $remainingSeconds) ?></span></p>
        </div>
    </div>

    <div class="bg-slate-50 px-6 py-3 text-center border-t border-slate-100">
        <p class="text-xs text-slate-400">Powered by <?= e($appName) ?></p>
    </div>
</div>

<script>
let remaining = <?= $remainingSeconds ?>;
setInterval(() => {
    if (remaining <= 0) { location.reload(); return; }
    remaining--;
    const h = Math.floor(remaining/3600), m = Math.floor((remaining%3600)/60), s = remaining%60;
    document.getElementById('countdown').textContent = 
        String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
}, 1000);
</script>


<?php else: ?>
<!-- ============ PAYMENT DETAIL (method selected) ============ -->
<?php
$midtransMeta = json_decode($transaction['snap_token'] ?? '{}', true) ?: [];
$vaNumber = $midtransMeta['va_number'] ?? null;
$vaBank = $midtransMeta['va_bank'] ?? null;
$deeplink = $midtransMeta['deeplink'] ?? null;
$paymentCode = $midtransMeta['payment_code'] ?? null;
$qrUrl = $transaction['qr_url'] ?? null;
$paymentType = $midtransMeta['payment_type'] ?? ($transaction['payment_channel'] ?? '');
?>
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-5 text-white text-center">
        <h1 class="text-lg font-bold"><?= e($merchantName) ?></h1>
        <p class="text-blue-200 text-sm mt-0.5"><?= e($transaction['link_name'] ?? 'Pembayaran') ?></p>
    </div>

    <div class="p-6">
        <!-- Amount -->
        <div class="text-center mb-6">
            <p class="text-sm text-slate-500 mb-1">Total Pembayaran</p>
            <p class="text-4xl font-extrabold text-slate-800"><?= format_currency($transaction['amount']) ?></p>
            <p class="text-xs text-slate-400 mt-1 font-mono"><?= e($transaction['order_id']) ?></p>
        </div>

<?php if ($vaNumber): ?>
        <!-- ===== VA DISPLAY ===== -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 mb-5">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                    <?= get_payment_icon(strtolower($vaBank ?? 'bank')) ?>
                </div>
                <div>
                    <p class="text-xs text-blue-600 font-medium">Virtual Account</p>
                    <p class="text-sm font-bold text-blue-900"><?= e($vaBank ?? 'BANK') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-2 bg-white border border-blue-200 rounded-lg p-3">
                <p class="flex-1 text-xl font-bold font-mono text-slate-900 tracking-wider" id="vaDisplay"><?= e($vaNumber) ?></p>
                <button onclick="copyText('<?= e($vaNumber) ?>', this)" class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded-lg hover:bg-blue-700">Copy</button>
            </div>
        </div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-4">
            <p class="text-sm font-medium text-slate-700 mb-2">Cara Pembayaran:</p>
            <ol class="text-xs text-slate-600 space-y-1.5 list-decimal list-inside">
                <li>Buka Mobile Banking / ATM <strong><?= e($vaBank ?? '') ?></strong></li>
                <li>Pilih menu <strong>Transfer → Virtual Account</strong></li>
                <li>Masukkan nomor VA: <strong class="font-mono"><?= e($vaNumber) ?></strong></li>
                <li>Masukkan nominal: <strong><?= format_currency($transaction['amount']) ?></strong></li>
                <li>Konfirmasi dan selesaikan pembayaran</li>
            </ol>
        </div>

<?php elseif ($qrUrl): ?>
        <!-- ===== QRIS DISPLAY ===== -->
        <div class="flex justify-center mb-5">
            <div class="p-3 bg-white border-2 border-slate-200 rounded-xl shadow-sm">
                <?php
                $isRawQris = !str_starts_with($qrUrl, 'http');
                $imgSrc = $isRawQris ? 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrUrl) : $qrUrl;
                ?>
                <img src="<?= e($imgSrc) ?>" alt="QRIS" class="w-52 h-52 object-contain">
            </div>
        </div>
        <div class="text-center mb-4">
            <p class="text-xs text-slate-500">Scan QR dengan aplikasi e-wallet atau mobile banking</p>
            <div class="flex items-center justify-center gap-3 mt-2">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/QRIS_logo.svg/120px-QRIS_logo.svg.png" alt="QRIS" class="h-5">
            </div>
            <a href="<?= e($imgSrc) ?>" download="QRIS-<?= e($transaction['order_id']) ?>.png" class="inline-flex items-center gap-1 mt-3 text-xs text-blue-600 hover:text-blue-700 font-medium">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download QR
            </a>
        </div>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-4">
            <p class="text-sm font-medium text-slate-700 mb-2">Cara Pembayaran:</p>
            <ol class="text-xs text-slate-600 space-y-1.5 list-decimal list-inside">
                <li>Buka aplikasi e-wallet atau mobile banking</li>
                <li>Pilih menu <strong>Scan QR / QRIS</strong></li>
                <li>Scan QR code di atas</li>
                <li>Konfirmasi pembayaran <strong><?= format_currency($transaction['amount']) ?></strong></li>
            </ol>
        </div>

<?php elseif ($deeplink): ?>
        <!-- ===== E-WALLET DEEPLINK ===== -->
        <a href="<?= e($deeplink) ?>" class="block w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-semibold py-4 px-6 rounded-xl text-center text-lg mb-5 transition-all shadow-lg">
            Bayar dengan <?= e(ucfirst(str_replace('_', ' ', $paymentType))) ?>
        </a>
        <p class="text-center text-xs text-slate-400 mb-4">Klik tombol di atas untuk membuka aplikasi pembayaran</p>

<?php elseif ($paymentCode): ?>
        <!-- ===== PAYMENT CODE ===== -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-5">
            <p class="text-xs text-amber-600 font-medium mb-2">Kode Pembayaran</p>
            <div class="flex items-center gap-2 bg-white border border-amber-200 rounded-lg p-3">
                <p class="flex-1 text-xl font-bold font-mono text-slate-900 tracking-wider"><?= e($paymentCode) ?></p>
                <button onclick="copyText('<?= e($paymentCode) ?>', this)" class="px-3 py-1.5 bg-amber-600 text-white text-xs rounded-lg hover:bg-amber-700">Copy</button>
            </div>
            <p class="text-xs text-amber-500 mt-2">Tunjukkan kode ini di kasir</p>
        </div>
<?php endif; ?>

        <!-- Countdown -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center mb-4">
            <p class="text-xs text-slate-500 mb-1">Batas waktu pembayaran</p>
            <div id="countdown" class="text-2xl font-bold text-slate-800 font-mono">--:--:--</div>
        </div>

        <!-- Status -->
        <div id="statusBox" class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-center">
            <div class="flex items-center justify-center gap-2">
                <div class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></div>
                <p class="text-sm font-medium text-amber-700" id="statusText">Menunggu pembayaran...</p>
            </div>
        </div>

        <!-- Change method link -->
        <div class="mt-4 text-center">
            <a href="?order_id=<?= e($transaction['order_id']) ?>" class="text-xs text-slate-400 hover:text-blue-600">← Ganti metode pembayaran</a>
        </div>
    </div>

    <div class="bg-slate-50 px-6 py-3 text-center border-t border-slate-100">
        <p class="text-xs text-slate-400">Powered by <?= e($appName) ?></p>
    </div>
</div>

<script>
(function() {
    let remaining = <?= $remainingSeconds ?>;
    const cd = document.getElementById('countdown');
    const statusBox = document.getElementById('statusBox');
    const statusText = document.getElementById('statusText');

    function tick() {
        if (remaining <= 0) { cd.textContent = '00:00:00'; cd.classList.add('text-red-600'); location.reload(); return; }
        remaining--;
        const h=Math.floor(remaining/3600), m=Math.floor((remaining%3600)/60), s=remaining%60;
        cd.textContent = String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
        if (remaining < 300) cd.classList.add('text-red-600');
    }
    tick(); setInterval(tick, 1000);

    function checkStatus() {
        fetch(location.pathname + '?order_id=<?= e($transaction['order_id']) ?>&check_status=1')
            .then(r => r.json())
            .then(d => {
                if (d.status === 'PAID') {
                    statusBox.className = 'bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-center';
                    statusText.textContent = '✓ Pembayaran berhasil!';
                    statusText.className = 'text-sm font-bold text-emerald-700';
                    setTimeout(() => location.href = d.redirect_url || location.href, 2000);
                } else if (d.status === 'EXPIRED' || d.status === 'FAILED') {
                    location.reload();
                }
            }).catch(()=>{});
    }
    setInterval(checkStatus, 5000);
    setTimeout(checkStatus, 2000);
})();

function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
}
</script>
<?php endif; ?>

</div>
</body>
</html>
<?php
// Payment method icon helper
function get_payment_icon(string $icon): string {
    return match($icon) {
        'qris', 'qris_mt' => '<svg class="w-5 h-5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="3" height="3" rx="0.5"/><rect x="18" y="18" width="3" height="3" rx="0.5"/></svg>',
        'bca' => '<span class="text-[10px] font-bold text-blue-700">BCA</span>',
        'bni' => '<span class="text-[10px] font-bold text-orange-600">BNI</span>',
        'bri' => '<span class="text-[10px] font-bold text-blue-800">BRI</span>',
        'permata' => '<span class="text-[10px] font-bold text-green-700">PMT</span>',
        'mandiri' => '<span class="text-[10px] font-bold text-blue-900">MDR</span>',
        'cimb' => '<span class="text-[10px] font-bold text-red-700">CIMB</span>',
        'gopay' => '<span class="text-[10px] font-bold text-blue-500">GPay</span>',
        'shopeepay' => '<span class="text-[10px] font-bold text-orange-500">SPay</span>',
        'alfamart' => '<span class="text-[10px] font-bold text-red-600">Alfa</span>',
        'indomaret' => '<span class="text-[10px] font-bold text-yellow-600">Indo</span>',
        default => '<svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
    };
}
?>
