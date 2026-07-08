<?php
require_once __DIR__ . '/includes/init.php';

$orderId = $_GET['order_id'] ?? '';
$transaction = null;

if (!empty($orderId)) {
    require_once base_path('app/Repositories/TransactionRepository.php');
    $txRepo = new TransactionRepository();
    $transaction = $txRepo->findByOrderId($orderId);
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Clipku Pay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="min-h-full font-sans bg-slate-50 flex items-center justify-center p-4">

<div class="w-full max-w-md text-center">
    <?php if ($transaction && $transaction['status'] === 'PAID'): ?>
    <!-- Success State -->
    <div class="bg-white rounded-2xl shadow-lg p-8">
        <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Pembayaran Berhasil!</h1>
        <p class="text-slate-600 mb-6">Terima kasih atas pembayaran Anda.</p>
        
        <div class="bg-slate-50 rounded-xl p-4 text-left space-y-2 mb-6">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Order ID</span>
                <span class="font-medium text-slate-800"><?= e($transaction['order_id']) ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Jumlah</span>
                <span class="font-bold text-emerald-600"><?= format_currency($transaction['amount']) ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Status</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">PAID</span>
            </div>
            <?php if (!empty($transaction['paid_at'])): ?>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Waktu Bayar</span>
                <span class="text-slate-800"><?= format_date($transaction['paid_at']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($transaction): ?>
    <!-- Pending/Other State -->
    <div class="bg-white rounded-2xl shadow-lg p-8">
        <div class="w-20 h-20 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Menunggu Pembayaran</h1>
        <p class="text-slate-600 mb-6">Silakan selesaikan pembayaran Anda.</p>
        
        <div class="bg-slate-50 rounded-xl p-4 text-left space-y-2 mb-6">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Order ID</span>
                <span class="font-medium text-slate-800"><?= e($transaction['order_id']) ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Jumlah</span>
                <span class="font-bold text-slate-800"><?= format_currency($transaction['amount']) ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Status</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($transaction['status']) ?>"><?= e($transaction['status']) ?></span>
            </div>
        </div>

        <?php if (!empty($transaction['payment_url'])): ?>
        <a href="<?= e($transaction['payment_url']) ?>" target="_blank" class="inline-flex items-center justify-center w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors">
            Bayar Sekarang
        </a>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Not Found -->
    <div class="bg-white rounded-2xl shadow-lg p-8">
        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Transaksi Tidak Ditemukan</h1>
        <p class="text-slate-600">Order ID tidak valid atau transaksi tidak ditemukan.</p>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
