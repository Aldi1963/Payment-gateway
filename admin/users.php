<?php
require_once __DIR__ . '/../includes/init.php';
Auth::requireRole(['super_admin', 'admin']);

require_once base_path('app/Repositories/UserRepository.php');
require_once base_path('app/Repositories/MerchantRepository.php');
require_once base_path('app/Services/AuditLogService.php');

$userRepo = new UserRepository();
$merchantRepo = new MerchantRepository();
$auditService = new AuditLogService();

// Handle POST actions
if (is_post()) {
    Auth::verifyCsrf();
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $email = sanitize($_POST['email'] ?? '');
        $name = sanitize($_POST['name'] ?? '');
        $role = sanitize($_POST['role'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = sanitize($_POST['status'] ?? 'active');

        $errors = [];
        if (empty($name)) $errors[] = 'Nama wajib diisi.';
        if (!is_valid_email($email)) $errors[] = 'Email tidak valid.';
        if (strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';
        if (!in_array($role, ['super_admin','admin','finance','support'])) $errors[] = 'Role tidak valid.';
        if ($userRepo->findByEmail($email)) $errors[] = 'Email sudah terdaftar.';

        if (empty($errors)) {
            $userRepo->create([
                'id' => generate_uuid(),
                'merchant_id' => null,
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $auditService->log(Auth::id(), Auth::role(), null, 'user_created', "Created user {$name} ({$role})", ['email' => $email]);
            flash('success', "User {$name} berhasil dibuat.");
        } else {
            foreach ($errors as $err) flash('error', $err);
        }
        redirect('/admin/users.php');


    } elseif ($action === 'update') {
        $userId = $_POST['user_id'] ?? '';
        $user = $userRepo->find($userId);
        if (!$user) { flash('error', 'User tidak ditemukan.'); redirect('/admin/users.php'); }

        $updates = ['updated_at' => now()];
        if (!empty($_POST['name'])) $updates['name'] = sanitize($_POST['name']);
        if (!empty($_POST['role']) && in_array($_POST['role'], ['super_admin','admin','finance','support','merchant','staff_merchant'])) {
            $updates['role'] = $_POST['role'];
        }
        if (!empty($_POST['status']) && in_array($_POST['status'], ['active','inactive','suspended'])) {
            $updates['status'] = $_POST['status'];
        }
        if (!empty($_POST['new_password']) && strlen($_POST['new_password']) >= 8) {
            $updates['password_hash'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        }

        $userRepo->update($userId, $updates);
        $auditService->log(Auth::id(), Auth::role(), null, 'user_updated', "Updated user {$user['name']}", ['user_id' => $userId, 'changes' => array_keys($updates)]);
        flash('success', 'User berhasil diperbarui.');
        redirect('/admin/users.php');

    } elseif ($action === 'delete') {
        $userId = $_POST['user_id'] ?? '';
        if ($userId === Auth::id()) {
            flash('error', 'Tidak dapat menghapus akun sendiri.');
        } else {
            $user = $userRepo->find($userId);
            if ($user) {
                $userRepo->delete($userId);
                $auditService->log(Auth::id(), Auth::role(), null, 'user_deleted', "Deleted user {$user['name']}", ['email' => $user['email']]);
                flash('success', "User {$user['name']} berhasil dihapus.");
            }
        }
        redirect('/admin/users.php');
    }
}

// Get all users (admin-side only: non-merchant roles + all)
$filterRole = $_GET['role'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$allUsers = $userRepo->findAll();

if (!empty($filterRole)) {
    $allUsers = array_filter($allUsers, fn($u) => ($u['role'] ?? '') === $filterRole);
    $allUsers = array_values($allUsers);
}
if (!empty($filterSearch)) {
    $search = strtolower($filterSearch);
    $allUsers = array_filter($allUsers, fn($u) => str_contains(strtolower($u['name'] . ' ' . $u['email']), $search));
    $allUsers = array_values($allUsers);
}

$pagination = paginate($allUsers, (int)($_GET['page'] ?? 1));
$merchants = $merchantRepo->findAll();

$pageTitle = 'User Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/admin_layout.php';
?>


<!-- Toolbar -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <form method="GET" class="flex items-center gap-2 flex-wrap">
        <input type="text" name="search" value="<?= e($filterSearch) ?>" placeholder="Cari nama/email..." class="px-4 py-2 border border-slate-300 rounded-lg text-sm w-48">
        <select name="role" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">Semua Role</option>
            <option value="super_admin" <?= $filterRole === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
            <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="finance" <?= $filterRole === 'finance' ? 'selected' : '' ?>>Finance</option>
            <option value="support" <?= $filterRole === 'support' ? 'selected' : '' ?>>Support</option>
            <option value="merchant" <?= $filterRole === 'merchant' ? 'selected' : '' ?>>Merchant</option>
            <option value="staff_merchant" <?= $filterRole === 'staff_merchant' ? 'selected' : '' ?>>Staff Merchant</option>
        </select>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm">Filter</button>
    </form>
    <button onclick="document.getElementById('createUserModal').classList.remove('hidden')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">+ Tambah User</button>
</div>

<!-- Users Table -->
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
<?php if (empty($pagination['data'])): ?>
    <div class="p-12 text-center text-slate-400"><p>Tidak ada user ditemukan.</p></div>
<?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Nama</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Email</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Role</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Dibuat</th>
                <th class="px-6 py-3 text-left font-medium text-slate-600">Aksi</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($pagination['data'] as $u): ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-3 font-medium text-slate-800"><?= e($u['name']) ?></td>
                <td class="px-6 py-3 text-slate-600"><?= e($u['email']) ?></td>
                <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700"><?= e(ucfirst(str_replace('_', ' ', $u['role']))) ?></span></td>
                <td class="px-6 py-3"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= status_badge_class($u['status']) ?>"><?= ucfirst($u['status']) ?></span></td>
                <td class="px-6 py-3 text-xs text-slate-500"><?= format_date($u['created_at'], 'd/m/Y') ?></td>
                <td class="px-6 py-3">
                    <button onclick="editUser(<?= e(json_encode($u)) ?>)" class="text-blue-600 text-xs font-medium hover:text-blue-700 mr-2">Edit</button>
                    <?php if ($u['id'] !== Auth::id()): ?>
                    <form method="POST" class="inline" onsubmit="return confirm('Hapus user ini?')">
                        <?= csrf_field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button class="text-red-600 text-xs font-medium hover:text-red-700">Hapus</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between">
        <span class="text-sm text-slate-500"><?= $pagination['total'] ?> users</span>
        <div class="flex gap-1">
            <?php if ($pagination['has_prev']): ?><a href="?page=<?= $pagination['current_page']-1 ?>&role=<?= e($filterRole) ?>&search=<?= e($filterSearch) ?>" class="px-3 py-1 rounded text-sm hover:bg-slate-100">&laquo;</a><?php endif; ?>
            <?php if ($pagination['has_next']): ?><a href="?page=<?= $pagination['current_page']+1 ?>&role=<?= e($filterRole) ?>&search=<?= e($filterSearch) ?>" class="px-3 py-1 rounded text-sm hover:bg-slate-100">&raquo;</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>


<!-- Create User Modal -->
<div id="createUserModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="this.parentElement.classList.add('hidden')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Tambah User Baru</h3>
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="create">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                <input type="text" name="name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required minlength="8" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Min 8 karakter">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                    <select name="role" required class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                        <option value="admin">Admin</option>
                        <option value="finance">Finance</option>
                        <option value="support">Support</option>
                        <?php if (Auth::isSuperAdmin()): ?><option value="super_admin">Super Admin</option><?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Buat User</button>
                <button type="button" onclick="this.closest('[id]').classList.add('hidden')" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="this.parentElement.classList.add('hidden')"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Edit User</h3>
        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="update">
            <input type="hidden" name="user_id" id="editUserId">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nama</label>
                <input type="text" name="name" id="editUserName" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" id="editUserEmail" disabled class="w-full px-4 py-2.5 border border-slate-200 bg-slate-50 rounded-lg text-sm text-slate-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                    <select name="role" id="editUserRole" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                        <option value="super_admin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="finance">Finance</option>
                        <option value="support">Support</option>
                        <option value="merchant">Merchant</option>
                        <option value="staff_merchant">Staff Merchant</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select name="status" id="editUserStatus" class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password Baru <span class="text-slate-400">(kosongkan jika tidak ubah)</span></label>
                <input type="password" name="new_password" minlength="8" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Min 8 karakter">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Simpan</button>
                <button type="button" onclick="this.closest('[id]').classList.add('hidden')" class="px-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('editUserModal').classList.remove('hidden');
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserName').value = user.name;
    document.getElementById('editUserEmail').value = user.email;
    document.getElementById('editUserRole').value = user.role;
    document.getElementById('editUserStatus').value = user.status;
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
