<?php
require_once __DIR__ . '/includes/init.php';

// If already logged in, redirect
if (Auth::check()) {
    if (Auth::isAdmin() || Auth::hasRole(['finance', 'support'])) {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/merchant/dashboard.php');
    }
}

// Handle POST login
if (is_post()) {
    require_once base_path('app/Controllers/AuthController.php');
    $controller = new AuthController();
    $controller->login();
}

$appName = setting('app_name', 'PayGate Pro');
$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="h-full font-sans antialiased">


<div class="min-h-full flex">
    <!-- Left Panel - Branding -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-700 relative overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute top-20 left-10 w-72 h-72 bg-white/5 rounded-full blur-2xl"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
        </div>
        <div class="relative flex flex-col justify-center px-12 xl:px-16">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                </div>
                <span class="text-xl font-bold text-white"><?= e($appName) ?></span>
            </div>
            <h1 class="text-3xl xl:text-4xl font-extrabold text-white leading-tight mb-4">
                Kelola Pembayaran<br>dengan Mudah
            </h1>
            <p class="text-blue-100 text-lg leading-relaxed mb-8">Platform payment gateway modern untuk bisnis Indonesia. QRIS, multi merchant, real-time webhook.</p>
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></div>
                    <span class="text-white/90 text-sm">Dashboard real-time & analytics</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></div>
                    <span class="text-white/90 text-sm">Terima QRIS dari semua bank & e-wallet</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></div>
                    <span class="text-white/90 text-sm">API & webhook untuk integrasi otomatis</span>
                </div>
            </div>
        </div>
    </div>


    <!-- Right Panel - Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center px-4 sm:px-8 py-12 bg-slate-50">
        <div class="w-full max-w-md">
            <!-- Mobile logo -->
            <div class="lg:hidden text-center mb-8">
                <div class="inline-flex items-center gap-2">
                    <div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
                    </div>
                    <span class="text-lg font-bold text-slate-800"><?= e($appName) ?></span>
                </div>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-slate-900">Selamat Datang</h2>
                    <p class="text-slate-500 text-sm mt-1">Masuk ke akun Anda untuk melanjutkan</p>
                </div>

                <?php foreach ($flashes as $flash): ?>
                <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2 <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100' ?>">
                    <?php if ($flash['type'] === 'success'): ?>
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <?php else: ?>
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <?php endif; ?>
                    <?= e($flash['message']) ?>
                </div>
                <?php endforeach; ?>

                <form method="POST" action="/login.php" class="space-y-5">
                    <?= csrf_field() ?>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <input type="email" id="email" name="email" required autocomplete="email"
                                class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm bg-slate-50 focus:bg-white"
                                placeholder="email@contoh.com" value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                            <a href="/forgot-password.php" class="text-xs font-medium text-blue-600 hover:text-blue-700">Lupa password?</a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                            <input type="password" id="password" name="password" required autocomplete="current-password"
                                class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm bg-slate-50 focus:bg-white"
                                placeholder="Masukkan password">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition-all shadow-lg shadow-blue-500/25 hover:shadow-xl hover:shadow-blue-500/30">
                        Masuk
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-sm text-slate-500">Belum punya akun? 
                        <a href="/register.php" class="text-blue-600 hover:text-blue-700 font-semibold">Daftar Merchant</a>
                    </p>
                </div>
            </div>

            <p class="text-center text-slate-400 text-xs mt-6">&copy; <?= date('Y') ?> <?= e($appName) ?>. All rights reserved.</p>
        </div>
    </div>
</div>

</body>
</html>
