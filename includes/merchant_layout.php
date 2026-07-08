<?php
/**
 * Merchant Layout - Simplified Sidebar + Main content
 * Menu: Dashboard, Pembayaran, Wallet, Integrasi API, Staff, Pengaturan
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$userName = $_SESSION['user_name'] ?? 'Merchant';
$userRole = $_SESSION['user_role'] ?? 'merchant';
$appName = setting('app_name', 'Clipku Pay');

$merchantMenus = [
    ['url' => '/merchant/dashboard.php', 'icon' => 'dashboard', 'label' => 'Dashboard', 'page' => 'dashboard'],
    ['url' => '/merchant/projects.php', 'icon' => 'integration', 'label' => 'Proyek', 'page' => 'projects'],
    ['url' => '/merchant/payments.php', 'icon' => 'payments', 'label' => 'Pembayaran', 'page' => 'payments'],
    ['url' => '/merchant/wallet.php', 'icon' => 'wallet', 'label' => 'Wallet', 'page' => 'wallet'],
    ['url' => '/merchant/integration.php', 'icon' => 'integration', 'label' => 'Integrasi API', 'page' => 'integration'],
    ['url' => '/merchant/staff.php', 'icon' => 'staff', 'label' => 'Staff', 'page' => 'staff'],
    ['url' => '/merchant/settings.php', 'icon' => 'settings', 'label' => 'Pengaturan', 'page' => 'settings'],
];

// Staff merchant: filter menus based on permissions
if ($userRole === 'staff_merchant') {
    $staffPerms = $_SESSION['permissions'] ?? [];
    $permMenuMap = [
        'view_transactions' => ['payments'],
        'create_payment' => ['payments'],
        'view_wallet' => ['wallet'],
        'view_withdrawals' => ['wallet'],
        'request_withdrawal' => ['wallet'],
        'manage_webhook' => ['integration'],
        'view_api_keys' => ['integration'],
        'view_payment_links' => ['payments'],
    ];
    $allowedPages = ['dashboard', 'settings']; // always allowed
    foreach ($staffPerms as $perm) {
        if (isset($permMenuMap[$perm])) {
            $allowedPages = array_merge($allowedPages, $permMenuMap[$perm]);
        }
    }
    $merchantMenus = array_filter($merchantMenus, fn($m) => in_array($m['page'], $allowedPages));
}
?>

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white transform -translate-x-full lg:translate-x-0 lg:static transition-transform duration-200 ease-in-out">
        <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-700">
            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>
            </div>
            <div>
                <h1 class="text-lg font-bold"><?= e($appName) ?></h1>
                <p class="text-xs text-slate-400">Merchant Panel</p>
            </div>
        </div>

        <nav class="mt-4 px-3 space-y-1 overflow-y-auto max-h-[calc(100vh-180px)]">
            <?php foreach ($merchantMenus as $menu): ?>
            <a href="<?= $menu['url'] ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $currentPage === $menu['page'] ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                <span class="w-5 h-5 flex items-center justify-center">
                    <?= get_merchant_icon($menu['icon']) ?>
                </span>
                <?= $menu['label'] ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-700">
            <div class="flex items-center gap-3">
                <?php $avatarUrl = $_SESSION['user_avatar'] ?? ''; ?>
                <?php if (!empty($avatarUrl)): ?>
                <img src="<?= e($avatarUrl) ?>" alt="" class="w-8 h-8 rounded-full object-cover ring-2 ring-slate-600">
                <?php else: ?>
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-sm font-bold">
                    <?= strtoupper(substr($userName, 0, 1)) ?>
                </div>
                <?php endif; ?>
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
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
<?php
        // Load projects for the switcher (merchant users only)
        $switcherProjects = [];
        $activeProject = null;
        if (in_array($userRole, ['merchant', 'staff_merchant'])) {
            try {
                require_once base_path('app/Services/ProjectService.php');
                $__projectService = new ProjectService();
                $switcherProjects = $__projectService->listByUser(Auth::id());
                $__activeId = Auth::merchantId();
                foreach ($switcherProjects as $__p) {
                    if ($__p['id'] === $__activeId) { $activeProject = $__p; break; }
                }
            } catch (\Throwable $e) {
                $switcherProjects = [];
            }
        }
?>
        <!-- Topbar -->
        <header class="bg-white border-b border-slate-200 px-4 lg:px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-600 hover:text-slate-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h2 class="text-lg font-semibold text-slate-800"><?= e($pageTitle ?? 'Dashboard') ?></h2>
            </div>
            <div class="flex items-center gap-3">
                <?php if (!empty($switcherProjects)): ?>
                <!-- Project Switcher -->
                <div class="relative" id="projectSwitcher">
                    <button onclick="toggleProjectMenu()" type="button" class="flex items-center gap-2 px-3 py-1.5 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50 max-w-[200px]">
                        <span class="w-2 h-2 rounded-full <?= ($activeProject['status'] ?? '') === 'active' ? 'bg-emerald-500' : 'bg-amber-400' ?>"></span>
                        <span class="truncate font-medium"><?= e($activeProject['business_name'] ?? 'Pilih Proyek') ?></span>
                        <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="projectMenu" class="hidden absolute right-0 mt-2 w-64 bg-white border border-slate-200 rounded-lg shadow-lg z-50 py-1 max-h-80 overflow-y-auto">
                        <?php foreach ($switcherProjects as $__p): ?>
                        <form method="POST" action="/merchant/projects.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="switch_project">
                            <input type="hidden" name="merchant_id" value="<?= e($__p['id']) ?>">
                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50 text-left <?= $__p['id'] === ($activeProject['id'] ?? '') ? 'bg-blue-50' : '' ?>">
                                <span class="w-2 h-2 rounded-full flex-shrink-0 <?= $__p['status'] === 'active' ? 'bg-emerald-500' : ($__p['status'] === 'pending' ? 'bg-amber-400' : 'bg-slate-300') ?>"></span>
                                <span class="flex-1 min-w-0">
                                    <span class="block truncate font-medium text-slate-700"><?= e($__p['business_name']) ?></span>
                                    <span class="block text-xs text-slate-400"><?= e($__p['slug']) ?></span>
                                </span>
                                <?php if ($__p['id'] === ($activeProject['id'] ?? '')): ?>
                                <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <?php endif; ?>
                            </button>
                        </form>
                        <?php endforeach; ?>
                        <div class="border-t border-slate-100 mt-1 pt-1">
                            <a href="/merchant/projects.php" class="flex items-center gap-2 px-3 py-2 text-sm text-blue-600 hover:bg-slate-50">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Buat / Kelola Proyek
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <span class="hidden sm:inline text-sm text-slate-500"><?= date('d M Y, H:i') ?></span>
            </div>
        </header>
        <script>
        function toggleProjectMenu() {
            document.getElementById('projectMenu').classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            var sw = document.getElementById('projectSwitcher');
            var menu = document.getElementById('projectMenu');
            if (sw && menu && !sw.contains(e.target)) { menu.classList.add('hidden'); }
        });
        </script>

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
