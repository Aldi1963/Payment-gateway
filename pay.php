<?php
/**
 * Branded Checkout / Payment Page
 * URL: /pay.php?id=ORDER_ID or /pay.php?order_id=INV-XXXXX
 * 
 * Features:
 * - QR code display
 * - Countdown timer (configurable expiry)
 * - Auto-check payment status via AJAX polling
 * - Merchant branding
 * - Mobile responsive
 * - Auto-redirect on payment success
 */

require_once __DIR__ . '/includes/init.php';
require_once base_path('app/Repositories/TransactionRepository.php');
require_once base_path('app/Repositories/MerchantRepository.php');

$txRepo = new TransactionRepository();
$merchantRepo = new MerchantRepository();

// Find transaction by ID or order_id
$txId = $_GET['id'] ?? '';
$orderId = $_GET['order_id'] ?? '';

$transaction = null;
if (!empty($txId)) {
    $transaction = $txRepo->find($txId);
} elseif (!empty($orderId)) {
    $transaction = $txRepo->findByOrderId($orderId);
}

if (!$transaction) {
    http_response_code(404);
    $pageError = 'Transaksi tidak ditemukan.';
}

$merchant = null;
$expiryMinutes = 60;
if ($transaction) {
    $merchant = $merchantRepo->find($transaction['merchant_id']);
    $expiryMinutes = (int)($merchant['payment_expiry_minutes'] ?? setting('payment_expiry_minutes', 60));
    
    // Calculate expiry time
    $createdAt = strtotime($transaction['created_at']);
    $expiryTime = $createdAt + ($expiryMinutes * 60);
    $now = time();
    $remainingSeconds = max(0, $expiryTime - $now);
    
    // Auto-expire if time passed and still pending
    if ($remainingSeconds <= 0 && $transaction['status'] === 'PENDING') {
        $txRepo->update($transaction['id'], ['status' => 'EXPIRED', 'expired_at' => now(), 'updated_at' => now()]);
        $transaction['status'] = 'EXPIRED';
    }
}

// AJAX status check endpoint
if (isset($_GET['check_status']) && $transaction) {
    header('Content-Type: application/json');
    // Re-read from storage for fresh status
    $fresh = $txRepo->find($transaction['id']);
    echo json_encode([
        'status' => $fresh['status'] ?? 'UNKNOWN',
        'paid_at' => $fresh['paid_at'] ?? null,
        'redirect_url' => $fresh['redirect_url'] ?? null,
    ]);
    exit;
}

$appName = setting('app_name', 'Clipku Pay');
$merchantName = $merchant['business_name'] ?? $appName;
$thankYouMsg = $merchant['thank_you_message'] ?? 'Terima kasih atas pembayaran Anda!';
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
    <?php
    // Midtrans detection for custom checkout
    $isMidtrans = ($transaction['payment_channel'] ?? '') === 'midtrans';
    ?>
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
<!-- SUCCESS STATE -->
<div class="bg-white rounded-2xl shadow-xl p-8 text-center">
    <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-10 h-10 text-emerald-600 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
    </div>
    <h1 class="text-2xl font-bold text-emerald-700 mb-2">Pembayaran Berhasil!</h1>
    <p class="text-slate-500 mb-6"><?= e($thankYouMsg) ?></p>
    <div class="bg-slate-50 rounded-xl p-4 text-left space-y-2 text-sm mb-6">
        <div class="flex justify-between"><span class="text-slate-500">Order</span><span class="font-mono font-medium"><?= e($transaction['order_id']) ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Jumlah</span><span class="font-bold text-emerald-600"><?= format_currency($transaction['amount']) ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Dibayar</span><span><?= format_date($transaction['paid_at'] ?? '') ?></span></div>
    </div>
    <p class="text-xs text-slate-400"><?= e($merchantName) ?></p>
</div>

<?php elseif ($transaction['status'] === 'EXPIRED'): ?>
<!-- EXPIRED STATE -->
<div class="bg-white rounded-2xl shadow-xl p-8 text-center">
    <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <h1 class="text-xl font-bold text-slate-700 mb-2">Pembayaran Expired</h1>
    <p class="text-slate-500 mb-4">Batas waktu pembayaran telah habis.</p>
    <div class="bg-slate-50 rounded-xl p-4 text-sm">
        <p class="text-slate-600">Order: <span class="font-mono"><?= e($transaction['order_id']) ?></span></p>
        <p class="text-slate-600">Amount: <?= format_currency($transaction['amount']) ?></p>
    </div>
    <p class="text-xs text-slate-400 mt-4">Silakan buat transaksi baru atau hubungi merchant.</p>
</div>

<?php elseif (in_array($transaction['status'], ['FAILED'])): ?>
<!-- FAILED STATE -->
<div class="bg-white rounded-2xl shadow-xl p-8 text-center">
    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    </div>
    <h1 class="text-xl font-bold text-red-700 mb-2">Pembayaran Gagal</h1>
    <p class="text-slate-500">Transaksi ini gagal diproses.</p>
</div>

<?php else: ?>
<!-- PENDING - MAIN CHECKOUT -->
<?php
$isMidtrans = ($transaction['payment_channel'] ?? '') === 'midtrans' && !empty($transaction['snap_token']);
$isQris = !$isMidtrans;
?>
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <!-- Header with merchant branding -->
    <div class="bg-gradient-to-r <?= $isMidtrans ? 'from-indigo-600 to-purple-700' : 'from-blue-600 to-blue-700' ?> px-6 py-5 text-white text-center">
        <h1 class="text-lg font-bold"><?= e($merchantName) ?></h1>
        <p class="<?= $isMidtrans ? 'text-indigo-200' : 'text-blue-200' ?> text-sm mt-0.5"><?= e($transaction['link_name'] ?? 'Pembayaran') ?></p>
    </div>

    <div class="p-6">
        <!-- Amount -->
        <div class="text-center mb-6">
            <p class="text-sm text-slate-500 mb-1">Total Pembayaran</p>
            <p class="text-4xl font-extrabold text-slate-800"><?= format_currency($transaction['amount']) ?></p>
            <p class="text-xs text-slate-400 mt-1 font-mono"><?= e($transaction['order_id']) ?></p>
        </div>

<?php if ($isMidtrans): ?>
        <!-- ========== MIDTRANS CHECKOUT (OUR PAGE) ========== -->
        <?php
        // Decode provider metadata from snap_token field
        $midtransMeta = json_decode($transaction['snap_token'] ?? '{}', true) ?: [];
        $vaNumber = $midtransMeta['va_number'] ?? null;
        $vaBank = $midtransMeta['va_bank'] ?? null;
        $deeplink = $midtransMeta['deeplink'] ?? null;
        $paymentCode = $midtransMeta['payment_code'] ?? null;
        $paymentType = $midtransMeta['payment_type'] ?? '';
        $qrUrl = $transaction['qr_url'] ?? null;
        ?>

        <?php if ($vaNumber): ?>
        <!-- Virtual Account Display -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5 mb-5">
            <p class="text-xs text-indigo-600 font-medium mb-1">Transfer ke Virtual Account</p>
            <p class="text-sm font-bold text-indigo-900 mb-3"><?= e($vaBank ?? 'BANK') ?></p>
            <div class="flex items-center gap-2 bg-white border border-indigo-200 rounded-lg p-3">
                <p class="flex-1 text-xl font-bold font-mono text-slate-900 tracking-wider" id="vaNumberDisplay"><?= e($vaNumber) ?></p>
                <button onclick="copyVA()" class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700 transition-colors">Copy</button>
            </div>
            <p class="text-xs text-indigo-500 mt-2">Salin nomor VA dan bayar melalui ATM, Mobile Banking, atau Internet Banking <?= e($vaBank ?? '') ?></p>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
            <p class="text-sm font-medium text-blue-800 mb-2">Cara Pembayaran:</p>
            <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                <li>Buka aplikasi Mobile Banking atau ATM <?= e($vaBank ?? '') ?></li>
                <li>Pilih menu Transfer &rarr; Virtual Account</li>
                <li>Masukkan nomor VA: <strong class="font-mono"><?= e($vaNumber) ?></strong></li>
                <li>Masukkan nominal: <strong><?= format_currency($transaction['amount']) ?></strong></li>
                <li>Konfirmasi dan selesaikan pembayaran</li>
            </ol>
        </div>

        <?php elseif ($qrUrl): ?>
        <!-- QRIS Display (Midtrans QRIS) -->
        <div class="flex justify-center mb-5">
            <div class="p-3 bg-white border-2 border-indigo-200 rounded-xl shadow-sm">
                <img src="<?= e($qrUrl) ?>" alt="QRIS" class="w-48 h-48 object-contain" id="qrImage">
            </div>
        </div>
        <div class="text-center mb-4">
            <p class="text-xs text-slate-500">Scan QR code dengan aplikasi e-wallet atau mobile banking</p>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
            <p class="text-sm font-medium text-blue-800 mb-2">Cara Pembayaran:</p>
            <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                <li>Buka aplikasi e-wallet (GoPay, OVO, DANA, dll)</li>
                <li>Pilih menu Scan / QRIS</li>
                <li>Scan QR code di atas</li>
                <li>Konfirmasi pembayaran sebesar <strong><?= format_currency($transaction['amount']) ?></strong></li>
            </ol>
        </div>

        <?php elseif ($deeplink): ?>
        <!-- E-Wallet Deeplink (GoPay/ShopeePay) -->
        <div class="text-center mb-5">
            <a href="<?= e($deeplink) ?>" class="inline-block w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold py-4 px-6 rounded-xl transition-all shadow-lg text-lg">
                Bayar dengan <?= e(ucfirst($paymentType)) ?>
            </a>
            <p class="text-xs text-slate-400 mt-3">Klik tombol di atas untuk membuka aplikasi pembayaran</p>
        </div>

        <?php elseif ($paymentCode): ?>
        <!-- Payment Code (Alfamart/Indomaret) -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-5">
            <p class="text-xs text-amber-600 font-medium mb-1">Kode Pembayaran</p>
            <div class="flex items-center gap-2 bg-white border border-amber-200 rounded-lg p-3">
                <p class="flex-1 text-xl font-bold font-mono text-slate-900 tracking-wider"><?= e($paymentCode) ?></p>
                <button onclick="navigator.clipboard.writeText('<?= e($paymentCode) ?>')" class="px-3 py-1.5 bg-amber-600 text-white text-xs rounded-lg hover:bg-amber-700">Copy</button>
            </div>
            <p class="text-xs text-amber-500 mt-2">Tunjukkan kode ini di kasir Alfamart/Indomaret</p>
        </div>

        <?php else: ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 text-center">
            <p class="text-sm text-amber-700">Menunggu detail pembayaran...</p>
        </div>
        <?php endif; ?>

<?php else: ?>
        <!-- ========== QRIS CHECKOUT ========== -->
        <?php if (!empty($transaction['qr_url'])): ?>
        <div class="flex justify-center mb-5">
            <div class="p-3 bg-white border-2 border-slate-200 rounded-xl shadow-sm">
                <?php
                $qrData = $transaction['qr_url'];
                $isRawQris = !str_starts_with($qrData, 'http');
                if ($isRawQris):
                    $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrData);
                ?>
                <img src="<?= e($qrImageUrl) ?>" alt="QRIS" class="w-48 h-48 object-contain" id="qrImage">
                <?php else: ?>
                <img src="<?= e($qrData) ?>" alt="QRIS" class="w-48 h-48 object-contain" id="qrImage">
                <?php endif; ?>
            </div>
        </div>
        <div class="text-center mb-4">
            <p class="text-xs text-slate-500">Scan QR code dengan aplikasi e-wallet atau mobile banking</p>
            <div class="flex items-center justify-center gap-2 mt-2">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/QRIS_logo.svg/120px-QRIS_logo.svg.png" alt="QRIS" class="h-5">
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
            <p class="text-sm font-medium text-blue-800 mb-2">Cara Pembayaran:</p>
            <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                <li>Buka aplikasi e-wallet atau mobile banking</li>
                <li>Pilih menu Scan QR / QRIS</li>
                <li>Scan QR code di atas</li>
                <li>Konfirmasi pembayaran sebesar <strong><?= format_currency($transaction['amount']) ?></strong></li>
            </ol>
        </div>
<?php endif; ?>

        <!-- Countdown Timer -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center mb-4">
            <p class="text-xs text-slate-500 mb-1">Batas waktu pembayaran</p>
            <div id="countdown" class="text-2xl font-bold text-slate-800 font-mono" data-remaining="<?= $remainingSeconds ?>">
                --:--:--
            </div>
            <p class="text-xs text-slate-400 mt-1">Bayar sebelum waktu habis</p>
        </div>

        <!-- Status Indicator -->
        <div id="statusBox" class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-center">
            <div class="flex items-center justify-center gap-2">
                <div class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></div>
                <p class="text-sm font-medium text-amber-700" id="statusText">Menunggu pembayaran...</p>
            </div>
        </div>

        <!-- Customer Info -->
        <?php if (!empty($transaction['customer_name'])): ?>
        <div class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-500 space-y-1">
            <?php if ($transaction['customer_name']): ?><p>Customer: <?= e($transaction['customer_name']) ?></p><?php endif; ?>
            <?php if ($transaction['customer_email']): ?><p>Email: <?= e($transaction['customer_email']) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="bg-slate-50 px-6 py-3 text-center border-t border-slate-100">
        <p class="text-xs text-slate-400">Powered by <?= e($appName) ?></p>
    </div>
</div>

<!-- Auto-check & Payment Scripts -->
<script>
(function() {
    let remaining = <?= $remainingSeconds ?>;
    const countdownEl = document.getElementById('countdown');
    const statusBox = document.getElementById('statusBox');
    const statusText = document.getElementById('statusText');
    let checkInterval;

    // Countdown timer
    function updateCountdown() {
        if (remaining <= 0) {
            countdownEl.textContent = '00:00:00';
            countdownEl.classList.add('text-red-600');
            statusBox.className = 'bg-red-50 border border-red-200 rounded-xl p-3 text-center';
            statusText.textContent = 'Waktu pembayaran habis';
            statusText.className = 'text-sm font-medium text-red-700';
            clearInterval(checkInterval);
            setTimeout(() => location.reload(), 2000);
            return;
        }
        remaining--;
        const h = Math.floor(remaining / 3600);
        const m = Math.floor((remaining % 3600) / 60);
        const s = remaining % 60;
        countdownEl.textContent = 
            String(h).padStart(2,'0') + ':' + 
            String(m).padStart(2,'0') + ':' + 
            String(s).padStart(2,'0');
        
        if (remaining < 300) {
            countdownEl.classList.add('text-red-600');
        }
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);

    // Auto-check payment status every 5 seconds
    function checkStatus() {
        fetch(location.href + (location.href.includes('?') ? '&' : '?') + 'check_status=1')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'PAID') {
                    statusBox.className = 'bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-center';
                    statusText.textContent = '✓ Pembayaran berhasil!';
                    statusText.className = 'text-sm font-bold text-emerald-700';
                    clearInterval(checkInterval);
                    setTimeout(() => {
                        const redirect = data.redirect_url || location.href;
                        location.href = redirect;
                    }, 2000);
                } else if (data.status === 'EXPIRED' || data.status === 'FAILED') {
                    location.reload();
                }
            })
            .catch(() => {});
    }
    checkInterval = setInterval(checkStatus, 5000);
    setTimeout(checkStatus, 2000);
})();

<?php if ($isMidtrans && !empty($midtransMeta['va_number'])): ?>
function copyVA() {
    navigator.clipboard.writeText('<?= e($midtransMeta['va_number'] ?? '') ?>').then(() => {
        const btn = document.querySelector('[onclick="copyVA()"]');
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
}
<?php endif; ?>
</script>
<?php endif; ?>

</div>
</body>
</html>
