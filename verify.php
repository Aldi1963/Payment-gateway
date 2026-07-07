<?php
/**
 * Email Verification Page
 * Verifies user email after registration
 */

require_once __DIR__ . '/includes/init.php';
require_once base_path('app/Repositories/UserRepository.php');
require_once base_path('app/Services/AuditLogService.php');

$token = $_GET['token'] ?? '';
$status = 'invalid';
$message = 'Link verifikasi tidak valid atau sudah expired.';

if (!empty($token)) {
    $userRepo = new UserRepository();
    $users = $userRepo->findAll();
    
    foreach ($users as $user) {
        if (($user['verify_token'] ?? '') === $token) {
            // Check expiry (24 hours)
            $tokenCreated = strtotime($user['verify_token_at'] ?? '');
            if ($tokenCreated && (time() - $tokenCreated) < 86400) {
                // Verify the user
                $userRepo->update($user['id'], [
                    'email_verified' => true,
                    'verify_token' => null,
                    'verify_token_at' => null,
                    'updated_at' => now(),
                ]);
                
                $status = 'success';
                $message = 'Email berhasil diverifikasi! Silakan login.';
                
                $auditService = new AuditLogService();
                $auditService->log($user['id'], $user['role'], $user['merchant_id'] ?? null,
                    'email_verified', 'Email verified', ['email' => $user['email']]);
            } else {
                $status = 'expired';
                $message = 'Link verifikasi sudah expired. Silakan request ulang.';
            }
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - <?= e(setting('app_name', 'PayGate Pro')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="min-h-screen font-sans bg-slate-100 flex items-center justify-center p-4">
<div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8 text-center">
    <?php if ($status === 'success'): ?>
    <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    </div>
    <h1 class="text-xl font-bold text-slate-800 mb-2">Email Terverifikasi!</h1>
    <p class="text-slate-500 mb-6"><?= e($message) ?></p>
    <a href="/login.php" class="inline-block bg-blue-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-blue-700">Login Sekarang</a>
    <?php else: ?>
    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </div>
    <h1 class="text-xl font-bold text-slate-800 mb-2"><?= $status === 'expired' ? 'Link Expired' : 'Verifikasi Gagal' ?></h1>
    <p class="text-slate-500 mb-6"><?= e($message) ?></p>
    <a href="/login.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">Kembali ke Login</a>
    <?php endif; ?>
</div>
</body>
</html>
