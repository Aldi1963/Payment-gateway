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

$pageTitle = 'Login';
$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PayGate Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="h-full font-sans bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-2xl mb-4 shadow-lg shadow-blue-500/30">
            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
        </div>
        <h1 class="text-2xl font-bold text-white">PayGate Pro</h1>
        <p class="text-slate-400 mt-1">Payment Gateway Multi Merchant</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <h2 class="text-xl font-semibold text-slate-800 mb-6">Masuk ke Akun Anda</h2>

        <?php foreach ($flashes as $flash): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' ?>">
            <?= e($flash['message']) ?>
        </div>
        <?php endforeach; ?>

        <form method="POST" action="/login.php" class="space-y-5">
            <?= csrf_field() ?>
            
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-sm"
                    placeholder="email@contoh.com" value="<?= e($_POST['email'] ?? '') ?>">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-sm"
                    placeholder="Masukkan password">
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-lg transition-colors shadow-sm">
                Masuk
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-slate-600">Belum punya akun? 
                <a href="/register.php" class="text-blue-600 hover:text-blue-700 font-medium">Daftar Merchant</a>
            </p>
        </div>
    </div>

    <p class="text-center text-slate-500 text-xs mt-6">&copy; <?= date('Y') ?> PayGate Pro. All rights reserved.</p>
</div>

</body>
</html>
