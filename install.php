<?php
/**
 * PayGate Pro - Installation Script
 * Run once to initialize storage and create Super Admin account.
 * DELETE THIS FILE AFTER INSTALLATION!
 */

// Check PHP version
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    die("ERROR: PHP 8.2+ required. Current version: " . PHP_VERSION);
}

// Check extensions
$required_ext = ['curl', 'json', 'mbstring', 'openssl'];
$missing = [];
foreach ($required_ext as $ext) {
    if (!extension_loaded($ext)) $missing[] = $ext;
}
if (!empty($missing)) {
    die("ERROR: Missing PHP extensions: " . implode(', ', $missing));
}

require_once __DIR__ . '/app/Helpers.php';

$storageDir = __DIR__ . '/storage';

// Create storage directory
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// Initialize JSON storage files
$files = [
    'users.json', 'merchants.json', 'transactions.json',
    'wallets.json', 'withdrawals.json', 'settlements.json',
    'webhook_events.json', 'audit_logs.json', 'settings.json',
    'wallet_ledger.json', 'notifications.json', 'config_changes.json',
    'fee_rules.json', 'webhook_retries.json', 'refunds.json',
];

foreach ($files as $file) {
    $path = $storageDir . '/' . $file;
    if (!file_exists($path)) {
        file_put_contents($path, '[]', LOCK_EX);
    }
}

// Create logs file
$logFile = $storageDir . '/logs.txt';
if (!file_exists($logFile)) {
    touch($logFile);
}

// Initialize default settings
$settingsFile = $storageDir . '/settings.json';
$settings = json_decode(file_get_contents($settingsFile), true) ?: [];
if (empty($settings)) {
    $settings = [
        ['id' => generate_uuid(), 'key' => 'app_name', 'value' => 'PayGate Pro', 'created_at' => now(), 'updated_at' => now()],
        ['id' => generate_uuid(), 'key' => 'default_fee_type', 'value' => 'percentage', 'created_at' => now(), 'updated_at' => now()],
        ['id' => generate_uuid(), 'key' => 'default_fee_value', 'value' => 0.7, 'created_at' => now(), 'updated_at' => now()],
        ['id' => generate_uuid(), 'key' => 'default_fee_flat', 'value' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['id' => generate_uuid(), 'key' => 'min_withdrawal', 'value' => 10000, 'created_at' => now(), 'updated_at' => now()],
        ['id' => generate_uuid(), 'key' => 'global_webhook_url', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
    ];
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT), LOCK_EX);
}

// Create Super Admin account if no users exist
$usersFile = $storageDir . '/users.json';
$users = json_decode(file_get_contents($usersFile), true) ?: [];

if (empty($users)) {
    // Get admin credentials from form or defaults
    $adminEmail = $_POST['admin_email'] ?? 'admin@paygate.local';
    $adminPassword = $_POST['admin_password'] ?? 'admin123';
    $adminName = $_POST['admin_name'] ?? 'Super Admin';
    
    if (isset($_POST['admin_email'])) {
        // Form submitted - create the admin
        if (strlen($adminPassword) < 8) {
            $error = "Password minimal 8 karakter.";
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Email tidak valid.";
        } else {
            $users[] = [
                'id' => generate_uuid(),
                'merchant_id' => null,
                'name' => $adminName,
                'email' => $adminEmail,
                'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
                'role' => 'super_admin',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT), LOCK_EX);
            $success = true;
        }
    }
}

// Check if already installed
$alreadyInstalled = !empty($users);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - PayGate Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="min-h-screen font-sans bg-slate-100 flex items-center justify-center p-4">
<div class="w-full max-w-lg bg-white rounded-2xl shadow-lg p-8">
    <h1 class="text-2xl font-bold text-slate-800 mb-2">PayGate Pro - Instalasi</h1>
    
    <?php if (isset($success) && $success): ?>
    <div class="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
        <h3 class="font-semibold text-emerald-800 mb-2">Instalasi Berhasil!</h3>
        <p class="text-sm text-emerald-700 mb-2">Super Admin berhasil dibuat:</p>
        <ul class="text-sm text-emerald-600 space-y-1">
            <li><strong>Email:</strong> <?= htmlspecialchars($adminEmail) ?></li>
            <li><strong>Password:</strong> (yang Anda masukkan)</li>
        </ul>
        <p class="text-sm text-red-600 font-medium mt-4">PENTING: Hapus file <code>install.php</code> sekarang!</p>
        <a href="/login.php" class="inline-block mt-4 px-6 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Login Sekarang &rarr;</a>
    </div>

    <?php elseif ($alreadyInstalled): ?>
    <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
        <p class="text-sm text-amber-800 font-medium">Aplikasi sudah terinstal. Super Admin sudah ada.</p>
        <p class="text-sm text-red-600 font-medium mt-2">Hapus file <code>install.php</code> untuk keamanan!</p>
        <a href="/login.php" class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Login &rarr;</a>
    </div>

    <?php else: ?>
    <p class="text-slate-500 text-sm mb-6">Buat akun Super Admin untuk memulai.</p>

    <?php if (isset($error)): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="mb-6 p-4 bg-slate-50 rounded-lg">
        <h3 class="text-sm font-medium text-slate-700 mb-2">System Check</h3>
        <ul class="text-sm space-y-1">
            <li class="flex justify-between"><span>PHP Version</span><span class="text-emerald-600 font-medium"><?= PHP_VERSION ?> ✓</span></li>
            <li class="flex justify-between"><span>Storage writable</span><span class="<?= is_writable($storageDir) ? 'text-emerald-600' : 'text-red-600' ?> font-medium"><?= is_writable($storageDir) ? 'Yes ✓' : 'No ✗' ?></span></li>
            <?php foreach ($required_ext as $ext): ?>
            <li class="flex justify-between"><span>ext-<?= $ext ?></span><span class="text-emerald-600 font-medium">✓</span></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nama Admin</label>
            <input type="text" name="admin_name" value="Super Admin" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email Admin</label>
            <input type="email" name="admin_email" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="admin@yourdomain.com">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Password Admin</label>
            <input type="password" name="admin_password" required minlength="8" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Minimal 8 karakter">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-lg transition-colors">
            Install & Buat Admin
        </button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
