<?php
require_once __DIR__ . '/includes/init.php';

// Redirect to dashboard if logged in
if (Auth::check()) {
    if (Auth::isAdmin() || Auth::hasRole(['finance', 'support'])) {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/merchant/dashboard.php');
    }
}
$appName = setting('app_name', 'Clipku Pay');

// Payment methods to advertise — follows admin panel toggles (Settings > Gateway)
$payMethods = public_payment_methods();
$payGroups = [];
foreach ($payMethods as $m) {
    $payGroups[$m['group']][] = $m;
}

// Pricing/fee — follows admin panel fee settings (Settings > Fee & Transaksi)
$feeType  = setting('default_fee_type', 'percentage');
$feeValue = (float) setting('default_fee_value', 0.7);
$feeFlat  = (float) setting('default_fee_flat', 0);
$feePct   = rtrim(rtrim(number_format($feeValue, 2, ',', '.'), '0'), ',') . '%';
if ($feeType === 'flat') {
    $feeMain = format_currency($feeFlat);
    $feeSub  = 'Biaya tetap per transaksi berhasil';
} elseif ($feeType === 'hybrid') {
    $feeMain = $feePct;
    $feeSub  = '+ ' . format_currency($feeFlat) . ' per transaksi';
} else { // percentage
    $feeMain = $feePct;
    $feeSub  = $feeFlat > 0 ? '+ ' . format_currency($feeFlat) . ' flat' : 'Tanpa biaya flat tambahan';
}
$minTrx = (int) setting('min_transaction_amount', 1000);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?> - Payment Gateway Multi Merchant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="font-sans bg-white text-slate-900 antialiased">


<!-- Navbar -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
            </div>
            <span class="text-lg font-bold text-slate-800"><?= e($appName) ?></span>
        </div>
        <div class="hidden sm:flex items-center gap-6">
            <a href="#features" class="text-sm text-slate-600 hover:text-blue-600 transition-colors">Fitur</a>
            <a href="#metode" class="text-sm text-slate-600 hover:text-blue-600 transition-colors">Metode Bayar</a>
            <a href="#pricing" class="text-sm text-slate-600 hover:text-blue-600 transition-colors">Harga</a>
            <a href="/docs.php" class="text-sm text-slate-600 hover:text-blue-600 transition-colors">API Docs</a>
        </div>
        <div class="flex items-center gap-3">
            <a href="/login.php" class="text-sm font-medium text-slate-700 hover:text-blue-600 transition-colors">Login</a>
            <a href="/register.php" class="text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-all shadow-sm hover:shadow-md">Daftar</a>
        </div>
    </div>
</nav>


<!-- Hero Section -->
<section class="pt-32 pb-20 px-4 sm:px-6 relative overflow-hidden">
    <!-- Background decoration -->
    <div class="absolute inset-0 -z-10">
        <div class="absolute top-20 left-1/4 w-96 h-96 bg-blue-100 rounded-full opacity-30 blur-3xl"></div>
        <div class="absolute bottom-0 right-1/4 w-80 h-80 bg-indigo-100 rounded-full opacity-30 blur-3xl"></div>
    </div>
    
    <div class="max-w-5xl mx-auto text-center">
        <div class="inline-flex items-center gap-2 bg-blue-50 border border-blue-100 rounded-full px-4 py-1.5 mb-6">
            <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
            <span class="text-xs font-medium text-blue-700">Payment Gateway Indonesia</span>
        </div>
        
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight mb-6">
            Terima Pembayaran<br>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-indigo-600">Lebih Mudah</span>
        </h1>
        
        <p class="text-lg sm:text-xl text-slate-600 max-w-2xl mx-auto mb-10 leading-relaxed">
            Platform payment gateway self-hosted untuk bisnis Indonesia. Terima QRIS, Virtual Account bank, dan e-wallet, kelola multi merchant, dan pantau transaksi real-time.
        </p>
        
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="/register.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-4 rounded-xl transition-all shadow-lg shadow-blue-500/25 hover:shadow-xl hover:shadow-blue-500/30 hover:-translate-y-0.5">
                Mulai Gratis
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
            <a href="/docs.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white hover:bg-slate-50 text-slate-700 font-medium px-8 py-4 rounded-xl border border-slate-200 transition-all hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                Lihat API Docs
            </a>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-3 gap-6 max-w-lg mx-auto mt-16">
            <div><p class="text-2xl sm:text-3xl font-extrabold text-slate-800">99.9%</p><p class="text-xs text-slate-500 mt-1">Uptime</p></div>
            <div><p class="text-2xl sm:text-3xl font-extrabold text-slate-800">&lt;1s</p><p class="text-xs text-slate-500 mt-1">Response</p></div>
            <div><p class="text-2xl sm:text-3xl font-extrabold text-slate-800">QRIS</p><p class="text-xs text-slate-500 mt-1">All Banks</p></div>
        </div>
    </div>
</section>


<!-- Features Section -->
<section id="features" class="py-20 px-4 sm:px-6 bg-slate-50">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">Semua yang Anda Butuhkan</h2>
            <p class="text-slate-600 max-w-xl mx-auto">Platform lengkap untuk menerima dan mengelola pembayaran digital bisnis Anda.</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Feature 1 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">QRIS Universal</h3>
                <p class="text-sm text-slate-600">Terima pembayaran dari semua e-wallet dan mobile banking: GoPay, OVO, DANA, ShopeePay, dan 50+ bank.</p>
            </div>
            
            <!-- Feature 2 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Multi Merchant</h3>
                <p class="text-sm text-slate-600">Kelola banyak merchant dengan wallet, fee, dan settlement terpisah dalam satu platform.</p>
            </div>
            
            <!-- Feature 3 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Webhook Real-time</h3>
                <p class="text-sm text-slate-600">Notifikasi otomatis saat pembayaran masuk dengan HMAC signature untuk keamanan.</p>
            </div>
            
            <!-- Feature 4 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Keamanan Maksimal</h3>
                <p class="text-sm text-slate-600">CSRF protection, rate limiting, HMAC signature, dan role-based access control.</p>
            </div>
            
            <!-- Feature 5 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="w-12 h-12 bg-rose-100 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Dashboard Analytics</h3>
                <p class="text-sm text-slate-600">Pantau transaksi, revenue, dan statistik bisnis secara real-time di dashboard.</p>
            </div>
            
            <!-- Feature 6 -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="w-12 h-12 bg-cyan-100 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">REST API</h3>
                <p class="text-sm text-slate-600">API sederhana untuk integrasi ke website, aplikasi, atau sistem apapun dalam hitungan menit.</p>
            </div>
        </div>
    </div>
</section>


<!-- Payment Methods Section (driven by admin panel toggles) -->
<section id="metode" class="py-20 px-4 sm:px-6">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-14">
            <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-100 rounded-full px-4 py-1.5 mb-4">
                <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                <span class="text-xs font-medium text-emerald-700">Metode Pembayaran</span>
            </div>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">Terima Semua Metode Populer</h2>
            <p class="text-slate-600 max-w-xl mx-auto">QRIS, Virtual Account bank, dan e-wallet dalam satu platform. Daftar metode di bawah mengikuti yang diaktifkan di panel admin.</p>
        </div>

        <?php if (empty($payMethods)): ?>
        <p class="text-center text-slate-400 text-sm">Belum ada metode pembayaran yang diaktifkan.</p>
        <?php else: ?>
        <div class="space-y-6">
            <?php
            $groupMeta = [
                'QRIS'            => ['desc' => 'Satu QR untuk semua bank & e-wallet'],
                'Virtual Account' => ['desc' => 'Transfer bank otomatis & terverifikasi'],
                'E-Wallet'        => ['desc' => 'Bayar langsung dari dompet digital'],
            ];
            foreach ($groupMeta as $gkey => $meta):
                if (empty($payGroups[$gkey])) continue;
            ?>
            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-6 sm:p-8">
                <div class="flex items-center justify-between gap-3 mb-5">
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-slate-800"><?= e($gkey) ?></h3>
                        <p class="text-sm text-slate-500"><?= e($meta['desc']) ?></p>
                    </div>
                    <span class="flex-shrink-0 text-xs font-medium text-slate-400"><?= count($payGroups[$gkey]) ?> metode</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <?php foreach ($payGroups[$gkey] as $m): ?>
                    <div class="bg-white rounded-xl border border-slate-200 h-16 flex items-center justify-center px-4 hover:shadow-md hover:border-blue-300 transition-all" title="<?= e($m['name']) ?>">
                        <img src="<?= e(payment_logo($m['code'])) ?>" alt="<?= e($m['name']) ?>" class="max-h-8 w-auto object-contain" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                        <span class="hidden text-sm font-bold text-slate-700"><?= e($m['name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-center text-xs text-slate-400 mt-6">Metode pembayaran dapat diaktifkan/nonaktifkan oleh admin di <span class="font-medium">Settings &rsaquo; Gateway</span>.</p>
        <?php endif; ?>
    </div>
</section>


<!-- Pricing Section -->
<section id="pricing" class="py-20 px-4 sm:px-6">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4">Harga Transparan</h2>
            <p class="text-slate-600">Tanpa biaya bulanan. Hanya bayar per transaksi yang berhasil.</p>
        </div>
        
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-3xl p-8 sm:p-12 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="relative">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                    <div>
                        <p class="text-blue-200 text-sm font-medium mb-2">Per Transaksi Berhasil</p>
                        <p class="text-5xl font-extrabold"><?= e($feeMain) ?></p>
                        <p class="text-blue-200 text-sm mt-2"><?= e($feeSub) ?></p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2"><svg class="w-5 h-5 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span class="text-sm">Unlimited transaksi</span></div>
                        <div class="flex items-center gap-2"><svg class="w-5 h-5 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span class="text-sm">Webhook & API akses penuh</span></div>
                        <div class="flex items-center gap-2"><svg class="w-5 h-5 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span class="text-sm">Settlement harian</span></div>
                        <div class="flex items-center gap-2"><svg class="w-5 h-5 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span class="text-sm">Dashboard & laporan</span></div>
                    </div>
                </div>
                <div class="mt-8">
                    <a href="/register.php" class="inline-flex items-center gap-2 bg-white text-blue-700 font-semibold px-8 py-3 rounded-xl hover:bg-blue-50 transition-colors">
                        Daftar Sekarang
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-20 px-4 sm:px-6 bg-slate-50">
    <div class="max-w-3xl mx-auto text-center">
        <h2 class="text-3xl font-extrabold text-slate-900 mb-4">Siap Menerima Pembayaran?</h2>
        <p class="text-slate-600 mb-8">Daftar sekarang dan mulai terima pembayaran QRIS dalam hitungan menit.</p>
        <a href="/register.php" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-4 rounded-xl transition-all shadow-lg shadow-blue-500/25">
            Daftar Gratis
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </a>
    </div>
</section>


<!-- Footer -->
<footer class="py-10 px-4 sm:px-6 border-t border-slate-200">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
            </div>
            <span class="text-sm font-semibold text-slate-700"><?= e($appName) ?></span>
        </div>
        <div class="flex items-center gap-6">
            <a href="/docs.php" class="text-sm text-slate-500 hover:text-blue-600 transition-colors">API Docs</a>
            <a href="/login.php" class="text-sm text-slate-500 hover:text-blue-600 transition-colors">Login</a>
            <a href="/register.php" class="text-sm text-slate-500 hover:text-blue-600 transition-colors">Daftar</a>
        </div>
        <p class="text-xs text-slate-400">&copy; <?= date('Y') ?> <?= e($appName) ?>. All rights reserved.</p>
    </div>
</footer>

</body>
</html>
