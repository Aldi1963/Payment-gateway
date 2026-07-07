<?php
require_once __DIR__ . '/includes/init.php';

if (Auth::check()) {
    redirect('/merchant/dashboard.php');
}

if (is_post()) {
    require_once base_path('app/Controllers/AuthController.php');
    $controller = new AuthController();
    $controller->register();
}

$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Merchant - PayGate Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="min-h-full font-sans bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 flex items-center justify-center p-4 py-8">

<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-emerald-600 rounded-2xl mb-4 shadow-lg shadow-emerald-500/30">
            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
        </div>
        <h1 class="text-2xl font-bold text-white">Daftar Merchant</h1>
        <p class="text-slate-400 mt-1">Buat akun untuk mulai menerima pembayaran</p>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-8">
        <?php foreach ($flashes as $flash): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' ?>">
            <?= e($flash['message']) ?>
        </div>
        <?php endforeach; ?>

        <form method="POST" action="/register.php" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Bisnis</label>
                <input type="text" name="business_name" required
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                    placeholder="PT Contoh Indonesia" value="<?= e($_POST['business_name'] ?? '') ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                <input type="text" name="name" required
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                    placeholder="John Doe" value="<?= e($_POST['name'] ?? '') ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" required
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                    placeholder="email@contoh.com" value="<?= e($_POST['email'] ?? '') ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">No. WhatsApp</label>
                <input type="text" name="phone"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                    placeholder="08123456789" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required minlength="8"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                    placeholder="Minimal 8 karakter">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Password</label>
                <input type="password" name="password_confirm" required minlength="8"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                    placeholder="Ulangi password">
            </div>

            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 px-4 rounded-lg transition-colors shadow-sm mt-2">
                Daftar Sekarang
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-slate-600">Sudah punya akun?
                <a href="/login.php" class="text-blue-600 hover:text-blue-700 font-medium">Masuk</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>
