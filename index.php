<?php
require_once __DIR__ . '/includes/init.php';

// Redirect to login or dashboard
if (Auth::check()) {
    if (Auth::isAdmin() || Auth::hasRole(['finance', 'support'])) {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/merchant/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayGate Pro - Payment Gateway Multi Merchant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="h-full font-sans bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900">

<!-- Landing Page -->
<div class="min-h-screen flex flex-col">
    <!-- Nav -->
    <nav class="px-6 py-4 flex items-center justify-between max-w-7xl mx-auto w-full">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
            </div>
            <span class="text-xl font-bold text-white">PayGate Pro</span>
        </div>
        <div class="flex items-center gap-4">
            <a href="/login.php" class="text-slate-300 hover:text-white text-sm font-medium transition-colors">Login</a>
            <a href="/register.php" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">Daftar Merchant</a>
        </div>
    </nav>

    <!-- Hero -->
    <div class="flex-1 flex items-center justify-center px-6">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-4xl md:text-6xl font-extrabold text-white leading-tight mb-6">
                Payment Gateway<br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-emerald-400">Multi Merchant</span>
            </h1>
            <p class="text-lg md:text-xl text-slate-300 mb-8 max-w-2xl mx-auto">
                Platform pembayaran self-hosted yang powerful. Terima pembayaran QRIS, kelola merchant, dan pantau transaksi dalam satu dashboard.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="/register.php" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-3.5 rounded-xl transition-colors shadow-lg shadow-blue-500/30">
                    Mulai Sekarang &rarr;
                </a>
                <a href="/login.php" class="w-full sm:w-auto border border-slate-600 hover:border-slate-500 text-slate-300 hover:text-white font-medium px-8 py-3.5 rounded-xl transition-colors">
                    Login Dashboard
                </a>
            </div>

            <!-- Features -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-16">
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-6 text-left">
                    <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-white font-semibold mb-2">Integrasi QRIS</h3>
                    <p class="text-slate-400 text-sm">Terima pembayaran QRIS otomatis via AldiQRIS dengan webhook real-time.</p>
                </div>
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-6 text-left">
                    <div class="w-10 h-10 bg-emerald-500/20 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h3 class="text-white font-semibold mb-2">Multi Merchant</h3>
                    <p class="text-slate-400 text-sm">Kelola banyak merchant dengan wallet, fee, dan settlement terpisah.</p>
                </div>
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-xl p-6 text-left">
                    <div class="w-10 h-10 bg-amber-500/20 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-white font-semibold mb-2">Keamanan</h3>
                    <p class="text-slate-400 text-sm">CSRF protection, webhook signature validation, dan role-based access control.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="px-6 py-6 text-center">
        <p class="text-slate-500 text-sm">&copy; <?= date('Y') ?> PayGate Pro. Self-Hosted Payment Gateway.</p>
    </footer>
</div>

</body>
</html>
