<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireMerchant();

require_once base_path('app/Controllers/MerchantController.php');
$controller = new MerchantController();

if (is_post()) {
    Auth::verifyCsrf();
    $result = $controller->updateProfile($_POST);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect('/merchant/profile.php');
}

$merchant = $controller->getMerchant();
$user = Auth::user();
$pageTitle = 'Profil';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>

<div class="max-w-lg">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-6">Profil Merchant</h3>
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Bisnis</label>
                <input type="text" name="business_name" value="<?= e($merchant['business_name'] ?? '') ?>"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                <input type="text" name="name" value="<?= e($user['name'] ?? '') ?>"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">No. Telepon</label>
                <input type="text" name="phone" value="<?= e($merchant['phone'] ?? '') ?>"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" value="<?= e($user['email'] ?? '') ?>" disabled
                    class="w-full px-4 py-2.5 border border-slate-200 bg-slate-50 rounded-lg text-sm text-slate-500">
            </div>

            <div class="border-t border-slate-200 pt-4 mt-4">
                <p class="text-sm font-medium text-slate-700 mb-3">Ubah Password</p>
                <div class="space-y-3">
                    <input type="password" name="current_password" placeholder="Password saat ini"
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
                    <input type="password" name="new_password" placeholder="Password baru (min 8 char)"
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
                    <input type="password" name="password_confirm" placeholder="Konfirmasi password baru"
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
                </div>
                <p class="text-xs text-slate-400 mt-1">Kosongkan jika tidak ingin mengubah password.</p>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm">
                Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
