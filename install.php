<?php
/**
 * Clipku Pay - Installation Script
 * Run once to initialize database and create Super Admin account.
 * Auto-locks after successful installation (checks DB for existing admin).
 * DELETE THIS FILE AFTER INSTALLATION for additional security!
 */

// Check PHP version
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    die("ERROR: PHP 8.2+ required. Current version: " . PHP_VERSION);
}

// Check extensions
$required_ext = ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql'];
$missing = [];
foreach ($required_ext as $ext) {
    if (!extension_loaded($ext)) $missing[] = $ext;
}
if (!empty($missing)) {
    die("ERROR: Missing PHP extensions: " . implode(', ', $missing));
}

require_once __DIR__ . '/app/Helpers.php';

$error = null;
$success = false;
$step = $_POST['step'] ?? 'form';
$alreadyInstalled = false;

// AUTO-LOCK: Check if already installed by testing DB connection and user existence
$dbConfigPath = __DIR__ . '/config/database.php';
if (file_exists($dbConfigPath)) {
    try {
        $config = require $dbConfigPath;
        if (!empty($config['host']) && !empty($config['database']) && !empty($config['username'])) {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options'] ?? []);
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'");
            $adminCount = (int)$stmt->fetchColumn();
            if ($adminCount > 0) {
                $alreadyInstalled = true;
            }
        }
    } catch (\Throwable $e) {
        // DB not configured yet or tables don't exist, proceed with install
    }
}

if ($step === 'install' && !$alreadyInstalled) {
    // Get form data
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_database'] ?? 'paygate');
    $dbUser = trim($_POST['db_username'] ?? 'root');
    $dbPass = $_POST['db_password'] ?? '';
    $adminName = trim($_POST['admin_name'] ?? 'Super Admin');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    // Validate
    if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Email admin tidak valid.";
    } elseif (strlen($adminPassword) < 8) {
        $error = "Password minimal 8 karakter.";
    } elseif (empty($dbHost) || empty($dbName) || empty($dbUser)) {
        $error = "Semua field database wajib diisi (kecuali password).";
    }

    if (!$error) {
        // Step 1: Test database connection
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

        } catch (\PDOException $e) {
            $error = "Koneksi database gagal: " . $e->getMessage();
        }
    }

    if (!$error) {
        // Step 2: Execute database.sql
        try {
            $sqlFile = __DIR__ . '/database.sql';
            if (!file_exists($sqlFile)) {
                $error = "File database.sql tidak ditemukan.";
            } else {
                $sql = file_get_contents($sqlFile);
                $pdo->exec($sql);
            }
        } catch (\PDOException $e) {
            $error = "Gagal membuat tabel: " . $e->getMessage();
        }
    }

    if (!$error) {
        // Step 3: Create admin user
        try {
            $adminId = generate_uuid();
            $stmt = $pdo->prepare("INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `status`, `email_verified`, `created_at`, `updated_at`) VALUES (:id, :name, :email, :pass, :role, :status, :verified, :created, :updated)");
            $stmt->execute([
                'id' => $adminId,
                'name' => $adminName,
                'email' => $adminEmail,
                'pass' => password_hash($adminPassword, PASSWORD_DEFAULT),
                'role' => 'super_admin',
                'status' => 'active',
                'verified' => 1,
                'created' => now(),
                'updated' => now(),
            ]);
        } catch (\PDOException $e) {
            $error = "Gagal membuat admin: " . $e->getMessage();
        }
    }

    if (!$error) {
        // Step 4: Save database config file
        $configContent = "<?php\nreturn [\n"
            . "    'host' => getenv('DB_HOST') ?: " . var_export($dbHost, true) . ",\n"
            . "    'port' => getenv('DB_PORT') ?: " . var_export($dbPort, true) . ",\n"
            . "    'database' => getenv('DB_DATABASE') ?: " . var_export($dbName, true) . ",\n"
            . "    'username' => getenv('DB_USERNAME') ?: " . var_export($dbUser, true) . ",\n"
            . "    'password' => getenv('DB_PASSWORD') ?: " . var_export($dbPass, true) . ",\n"
            . "    'charset' => 'utf8mb4',\n"
            . "    'collation' => 'utf8mb4_unicode_ci',\n"
            . "    'options' => [\n"
            . "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
            . "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
            . "        PDO::ATTR_EMULATE_PREPARES => false,\n"
            . "    ],\n"
            . "];\n";

        $configDir = __DIR__ . '/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        file_put_contents($configDir . '/database.php', $configContent, LOCK_EX);
        $success = true;
    }

    if (!$error) {
        // Step 5: Record all migrations as applied (base schema already loaded
        // from database.sql). This populates schema_migrations so future
        // `php scripts/migrate.php` runs only apply genuinely new migrations.
        try {
            define('MIGRATE_BOOTSTRAP', true);
            require_once __DIR__ . '/app/Database.php';
            require_once __DIR__ . '/app/Schema.php';
            require_once __DIR__ . '/scripts/migrate.php';
            // Apply any pending migrations (idempotent — tolerates existing objects)
            Migrator::runPending(false);
        } catch (\Throwable $e) {
            // Non-fatal: base install succeeded. Log for visibility.
            error_log('Migration runner during install failed: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Clipku Pay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']}}}}</script>
</head>
<body class="min-h-screen font-sans bg-slate-100 flex items-center justify-center p-4">
<div class="w-full max-w-lg bg-white rounded-2xl shadow-lg p-8">
    <h1 class="text-2xl font-bold text-slate-800 mb-2">Clipku Pay - Instalasi</h1>

    <?php if ($success): ?>
    <div class="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
        <h3 class="font-semibold text-emerald-800 mb-2">Instalasi Berhasil!</h3>
        <p class="text-sm text-emerald-700 mb-2">Database dan Super Admin berhasil dibuat:</p>
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
    <p class="text-slate-500 text-sm mb-6">Konfigurasi database dan buat akun Super Admin.</p>

    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="mb-6 p-4 bg-slate-50 rounded-lg">
        <h3 class="text-sm font-medium text-slate-700 mb-2">System Check</h3>
        <ul class="text-sm space-y-1">
            <li class="flex justify-between"><span>PHP Version</span><span class="text-emerald-600 font-medium"><?= PHP_VERSION ?> &#10003;</span></li>
            <?php foreach ($required_ext as $ext): ?>
            <li class="flex justify-between"><span>ext-<?= $ext ?></span><span class="<?= extension_loaded($ext) ? 'text-emerald-600' : 'text-red-600' ?> font-medium"><?= extension_loaded($ext) ? '&#10003;' : '&#10007;' ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <form method="POST" class="space-y-4">
        <input type="hidden" name="step" value="install">

        <h3 class="text-sm font-semibold text-slate-700 border-b pb-2">Konfigurasi Database MySQL</h3>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">DB Host</label>
                <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">DB Port</label>
                <input type="text" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Database Name</label>
            <input type="text" name="db_database" value="<?= htmlspecialchars($_POST['db_database'] ?? 'paygate') ?>" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">DB Username</label>
                <input type="text" name="db_username" value="<?= htmlspecialchars($_POST['db_username'] ?? 'root') ?>" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">DB Password</label>
                <input type="password" name="db_password" value="" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="(kosongkan jika tidak ada)">
            </div>
        </div>

        <h3 class="text-sm font-semibold text-slate-700 border-b pb-2 mt-6">Akun Super Admin</h3>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nama Admin</label>
            <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Super Admin') ?>" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email Admin</label>
            <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="admin@yourdomain.com">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Password Admin</label>
            <input type="password" name="admin_password" required minlength="8" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Minimal 8 karakter">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-lg transition-colors">
            Install & Setup Database
        </button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
