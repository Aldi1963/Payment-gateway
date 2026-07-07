<?php
/**
 * Admin Layout - Sidebar + Main content
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$userName = $_SESSION['user_name'] ?? 'Admin';
$userRole = $_SESSION['user_role'] ?? 'admin';

$adminMenus = [
    ['url' => '/admin/dashboard.php', 'icon' => 'dashboard', 'label' => 'Dashboard', 'page' => 'dashboard'],
    ['url' => '/admin/users.php', 'icon' => 'users', 'label' => 'Users', 'page' => 'users'],
    ['url' => '/admin/merchants.php', 'icon' => 'store', 'label' => 'Merchants', 'page' => 'merchants'],
    ['url' => '/admin/transactions.php', 'icon' => 'receipt', 'label' => 'Transaksi', 'page' => 'transactions'],
    ['url' => '/admin/fee-management.php', 'icon' => 'fee', 'label' => 'Fee Engine', 'page' => 'fee-management'],
    ['url' => '/admin/config-changes.php', 'icon' => 'shield', 'label' => 'Config Verify', 'page' => 'config-changes'],
    ['url' => '/admin/withdrawals.php', 'icon' => 'wallet', 'label' => 'Withdrawals', 'page' => 'withdrawals'],
    ['url' => '/admin/settlements.php', 'icon' => 'bank', 'label' => 'Settlements', 'page' => 'settlements'],
    ['url' => '/admin/webhook-logs.php', 'icon' => 'webhook', 'label' => 'Webhook Logs', 'page' => 'webhook-logs'],
    ['url' => '/admin/audit-logs.php', 'icon' => 'audit', 'label' => 'Audit Logs', 'page' => 'audit-logs'],
    ['url' => '/admin/settings.php', 'icon' => 'settings', 'label' => 'Settings', 'page' => 'settings'],
];

// Role-based menu filtering
if ($userRole === 'support') {
    $allowedPages = ['dashboard', 'merchants', 'transactions', 'webhook-logs', 'audit-logs'];
    $adminMenus = array_filter($adminMenus, fn($m) => in_array($m['page'], $allowedPages));
}
if ($userRole === 'finance') {
    $allowedPages = ['dashboard', 'transactions', 'withdrawals', 'settlements'];
    $adminMenus = array_filter($adminMenus, fn($m) => in_array($m['page'], $allowedPages));
}
?>

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white transform -translate-x-full lg:translate-x-0 lg:static transition-transform duration-200 ease-in-out">
        <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-700">
            <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
            </div>
            <div>
                <h1 class="text-lg font-bold">PayGate Pro</h1>
                <p class="text-xs text-slate-400">Admin Panel</p>
            </div>
        </div>

        <nav class="mt-4 px-3 space-y-1">
            <?php foreach ($adminMenus as $menu): ?>
            <a href="<?= $menu['url'] ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === $menu['page'] ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                <span class="w-5 h-5 flex items-center justify-center">
                    <?= get_menu_icon($menu['icon']) ?>
                </span>
                <?= $menu['label'] ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-slate-600 rounded-full flex items-center justify-center text-sm font-bold">
                    <?= strtoupper(substr($userName, 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate"><?= e($userName) ?></p>
                    <p class="text-xs text-slate-400"><?= ucfirst(str_replace('_', ' ', $userRole)) ?></p>
                </div>
                <a href="/logout.php" class="text-slate-400 hover:text-red-400" title="Logout">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Topbar -->
        <header class="bg-white border-b border-slate-200 px-4 lg:px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-slate-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h2 class="text-lg font-semibold text-slate-800"><?= e($pageTitle ?? 'Dashboard') ?></h2>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-500"><?= date('d M Y, H:i') ?></span>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-y-auto p-4 lg:p-6">
            <?php
            $flashes = get_flash();
            foreach ($flashes as $flash): ?>
            <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>" role="alert">
                <?= e($flash['message']) ?>
                <button onclick="this.parentElement.remove()" class="float-right font-bold">&times;</button>
            </div>
            <?php endforeach; ?>
