<?php
/**
 * Forgot Password Page
 * Sends password reset link to user's email
 */
require_once __DIR__ . '/includes/init.php';

if (Auth::check()) {
    redirect('/');
}

// Handle POST - send reset email
if (is_post()) {
    Auth::verifyCsrf();
    
    require_once base_path('includes/turnstile.php');
    if (!turnstile_verify()) {
        flash('error', 'Verifikasi captcha gagal. Silakan coba lagi.');
        redirect('/forgot-password.php');
    }
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email) || !is_valid_email($email)) {
        flash('error', 'Masukkan alamat email yang valid.');
        redirect('/forgot-password.php');
    }
    
    // Always show success message to prevent email enumeration
    // In production, send actual reset email here
    require_once base_path('app/Repositories/UserRepository.php');
    $userRepo = new UserRepository();
    $user = $userRepo->findByEmail($email);
    
    if ($user) {
        // Generate reset token
        $token = generate_random(32);
        $userRepo->update($user['id'], [
            'verify_token' => $token,
            'verify_token_at' => now(),
        ]);
        
        // Send reset email
        require_once base_path('app/Services/EmailService.php');
        $emailService = new EmailService();
        $emailService->sendPasswordReset($email, $user['name'] ?? 'User', $token);
        
        app_log("Password reset requested for: {$email}", 'INFO');
    }
    
    flash('success', 'Jika email terdaftar, kami telah mengirim link reset password. Cek inbox Anda.');
    redirect('/forgot-password.php');
}

$appName = setting('app_name', 'Clipku Pay');
require_once base_path('includes/turnstile.php');
$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - <?= e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
    <?= turnstile_script() ?>
</head>
<body class="h-full font-sans antialiased bg-slate-50 flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
        <a href="/" class="inline-flex items-center gap-2">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
            </div>
            <span class="text-lg font-bold text-slate-800"><?= e($appName) ?></span>
        </a>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50 p-8">
        <!-- Icon -->
        <div class="flex justify-center mb-6">
            <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center">
                <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            </div>
        </div>

        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-slate-900">Lupa Password?</h2>
            <p class="text-slate-500 text-sm mt-2">Masukkan email Anda dan kami akan mengirimkan link untuk reset password.</p>
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

        <form method="POST" action="/forgot-password.php" class="space-y-5">
            <?= csrf_field() ?>
            
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Alamat Email</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <input type="email" id="email" name="email" required autocomplete="email"
                        class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm bg-slate-50 focus:bg-white"
                        placeholder="email@contoh.com">
                </div>
            </div>

            <?= turnstile_widget() ?>

            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition-all shadow-lg shadow-blue-500/25">
                Kirim Link Reset
            </button>
        </form>

        <div class="mt-6 text-center">
            <a href="/login.php" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-blue-600 font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Kembali ke Login
            </a>
        </div>
    </div>

    <p class="text-center text-slate-400 text-xs mt-6">&copy; <?= date('Y') ?> <?= e($appName) ?>. All rights reserved.</p>
</div>

</body>
</html>
