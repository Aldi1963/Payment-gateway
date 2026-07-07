<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireRole(['merchant']);

require_once base_path('app/Repositories/UserRepository.php');
require_once base_path('app/Services/AuditLogService.php');

$userRepo = new UserRepository();
$auditService = new AuditLogService();
$merchantId = Auth::merchantId();

if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? '';

    if ($action === 'add_staff') {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $permissions = $_POST['permissions'] ?? [];

        $errors = [];
        if (empty($name)) $errors[] = 'Nama wajib diisi.';
        if (!is_valid_email($email)) $errors[] = 'Email tidak valid.';
        if (strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';
        if ($userRepo->findByEmail($email)) $errors[] = 'Email sudah terdaftar.';

        if (empty($errors)) {
            $userRepo->create([
                'id' => generate_uuid(),
                'merchant_id' => $merchantId,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'staff_merchant',
                'status' => 'active',
                'permissions' => $permissions,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $auditService->log(Auth::id(), Auth::role(), $merchantId, 'staff_added', "Staff {$name} added by merchant", ['email' => $email]);
            flash('success', "Staff {$name} berhasil ditambahkan.");
        } else {
            foreach ($errors as $err) flash('error', $err);
        }

    } elseif ($action === 'update_staff') {
        $staffId = $_POST['staff_id'] ?? '';
        $staff = $userRepo->find($staffId);
        if ($staff && $staff['merchant_id'] === $merchantId && $staff['role'] === 'staff_merchant') {
            $updates = ['updated_at' => now()];
            if (!empty($_POST['name'])) $updates['name'] = sanitize($_POST['name']);
            if (!empty($_POST['status']) && in_array($_POST['status'], ['active','inactive','suspended'])) $updates['status'] = $_POST['status'];
            if (isset($_POST['permissions'])) $updates['permissions'] = $_POST['permissions'];
            if (!empty($_POST['new_password']) && strlen($_POST['new_password']) >= 8) {
                $updates['password_hash'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            }
            $userRepo->update($staffId, $updates);
            flash('success', 'Staff berhasil diperbarui.');
        } else {
            flash('error', 'Staff tidak ditemukan.');
        }

    } elseif ($action === 'remove_staff') {
        $staffId = $_POST['staff_id'] ?? '';
        $staff = $userRepo->find($staffId);
        if ($staff && $staff['merchant_id'] === $merchantId && $staff['role'] === 'staff_merchant') {
            $userRepo->delete($staffId);
            $auditService->log(Auth::id(), Auth::role(), $merchantId, 'staff_removed', "Staff {$staff['name']} removed", []);
            flash('success', "Staff {$staff['name']} berhasil dihapus.");
        }
    }
    redirect('/merchant/staff.php');
}

$allStaff = $userRepo->findByMerchant($merchantId);
$staffList = array_filter($allStaff, fn($u) => $u['role'] === 'staff_merchant');
$staffList = array_values($staffList);

$availablePermissions = [
    'view_transactions' => 'Lihat Transaksi',
    'create_payment' => 'Buat Pembayaran',
    'view_wallet' => 'Lihat Wallet',
    'view_withdrawals' => 'Lihat Withdrawal',
    'request_withdrawal' => 'Request Withdrawal',
    'manage_webhook' => 'Kelola Webhook',
    'view_api_keys' => 'Lihat API Key',
    'view_payment_links' => 'Lihat Payment Links',
];

$pageTitle = 'Kelola Staff';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/merchant_layout.php';
?>


<div class="max-w-3xl">

<!-- Staff List -->
<div class="bg-white rounded-xl border border-slate-200 p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-slate-800">Staff Anda</h3>
        <span class="text-sm text-slate-400"><?= count($staffList) ?> staff</span>
    </div>

    <?php if (empty($staffList)): ?>
    <div class="text-center py-8 text-slate-400">
        <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <p class="text-sm">Belum ada staff. Tambahkan di bawah.</p>
    </div>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($staffList as $staff): ?>
        <div class="flex items-start justify-between p-4 bg-slate-50 rounded-lg border border-slate-200">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-700 text-sm font-bold mt-0.5"><?= strtoupper(substr($staff['name'], 0, 1)) ?></div>
                <div>
                    <p class="text-sm font-medium text-slate-800"><?= e($staff['name']) ?></p>
                    <p class="text-xs text-slate-500"><?= e($staff['email']) ?></p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1 <?= status_badge_class($staff['status']) ?>"><?= ucfirst($staff['status']) ?></span>
                    <?php if (!empty($staff['permissions'])): ?>
                    <div class="flex flex-wrap gap-1 mt-2">
                        <?php foreach ((array)$staff['permissions'] as $perm): ?>
                        <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded"><?= e($availablePermissions[$perm] ?? $perm) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex gap-2 shrink-0">
                <button onclick="editStaff(<?= e(json_encode($staff)) ?>)" class="text-blue-600 text-xs font-medium hover:text-blue-700">Edit</button>
                <form method="POST" class="inline" onsubmit="return confirm('Hapus staff ini?')">
                    <?= csrf_field() ?><input type="hidden" name="_action" value="remove_staff"><input type="hidden" name="staff_id" value="<?= $staff['id'] ?>">
                    <button class="text-red-500 text-xs font-medium hover:text-red-700">Hapus</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Staff Form -->
<div class="bg-white rounded-xl border border-slate-200 p-6">
    <h3 class="text-sm font-semibold text-slate-800 mb-4">Tambah Staff Baru</h3>
    <form method="POST" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="add_staff">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs text-slate-500 mb-1">Nama</label>
                <input type="text" name="name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Nama lengkap"></div>
            <div><label class="block text-xs text-slate-500 mb-1">Email</label>
                <input type="email" name="email" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="staff@email.com"></div>
        </div>
        <div><label class="block text-xs text-slate-500 mb-1">Password</label>
            <input type="password" name="password" required minlength="8" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Min 8 karakter"></div>
        <div>
            <label class="block text-xs text-slate-500 mb-2">Hak Akses (Permissions)</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <?php foreach ($availablePermissions as $permKey => $permLabel): ?>
                <label class="flex items-center gap-2 text-sm bg-slate-50 px-3 py-2 rounded-lg cursor-pointer hover:bg-slate-100 border border-slate-200">
                    <input type="checkbox" name="permissions[]" value="<?= $permKey ?>" class="w-4 h-4 rounded border-slate-300 text-emerald-600">
                    <?= $permLabel ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Tambah Staff</button>
    </form>
</div>
</div>


<!-- Edit Staff Modal -->
<div id="editStaffModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="this.parentElement.classList.add('hidden')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6 max-h-[85vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Edit Staff</h3>
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="update_staff">
            <input type="hidden" name="staff_id" id="esId">
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Nama</label>
                <input type="text" name="name" id="esName" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                <select name="status" id="esStatus" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1">Password Baru <span class="text-slate-400">(kosongkan jika tidak ubah)</span></label>
                <input type="password" name="new_password" minlength="8" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm"></div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Permissions</label>
                <div class="grid grid-cols-1 gap-2" id="esPermissions">
                    <?php foreach ($availablePermissions as $pk => $pl): ?>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="permissions[]" value="<?= $pk ?>" class="w-4 h-4 rounded border-slate-300 text-emerald-600 es-perm"> <?= $pl ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
                <button type="button" onclick="document.getElementById('editStaffModal').classList.add('hidden')" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function editStaff(staff) {
    document.getElementById('editStaffModal').classList.remove('hidden');
    document.getElementById('esId').value = staff.id;
    document.getElementById('esName').value = staff.name;
    document.getElementById('esStatus').value = staff.status;
    // Reset and set permissions
    document.querySelectorAll('.es-perm').forEach(cb => cb.checked = false);
    if (staff.permissions && Array.isArray(staff.permissions)) {
        staff.permissions.forEach(p => {
            const cb = document.querySelector('.es-perm[value="' + p + '"]');
            if (cb) cb.checked = true;
        });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/merchant_footer.php'; ?>
