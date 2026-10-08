<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db_connection();
$currentAdminId = (int)current_user_id();

// ==========================================
// 1. Handle User Management Actions (POST + CSRF)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token  = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Security verification failed: CSRF token invalid.');
        header('Location: ' . url('admin/users.php'));
        exit;
    }

    // --- Action: ADD USER ---
    if ($action === 'add') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role     = trim($_POST['role'] ?? 'user');
        $password = trim($_POST['password'] ?? '');

        if ($fullName === '' || $username === '' || $password === '') {
            set_flash('danger', 'All user fields including password are required.');
            header('Location: ' . url('admin/users.php'));
            exit;
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            set_flash('danger', 'Invalid role assigned.');
            header('Location: ' . url('admin/users.php'));
            exit;
        }

        if (strlen($password) < 6) {
            set_flash('danger', 'Password must be at least 6 characters long.');
            header('Location: ' . url('admin/users.php'));
            exit;
        }

        // Check unique username
        $check = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $check->execute([$username]);
        if ($check->fetch()) {
            set_flash('danger', "Username '{$username}' is already taken. Please pick another.");
            header('Location: ' . url('admin/users.php'));
            exit;
        }

        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (full_name, username, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$fullName, $username, $hashedPassword, $role]);
            set_flash('success', "User '{$fullName}' ({$username}) was created successfully.");
        } catch (Throwable $e) {
            error_log("Failed to create user: " . $e->getMessage());
            set_flash('danger', 'Failed to save new user into database.');
        }

        header('Location: ' . url('admin/users.php'));
        exit;
    }

    // --- Action: EDIT USER ---
    if ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role     = trim($_POST['role'] ?? 'user');
        $password = trim($_POST['password'] ?? '');

        if ($id <= 0 || $fullName === '' || $username === '') {
            set_flash('danger', 'Missing required user information.');
            header('Location: ' . url('admin/users.php'));
            exit;
        }

        if (!in_array($role, ['admin', 'user'], true)) {
            set_flash('danger', 'Invalid role selection.');
            header('Location: ' . url('admin/users.php'));
            exit;
        }

        // Ensure unique username for other records
        $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $check->execute([$username, $id]);
        if ($check->fetch()) {
            set_flash('danger', "Username '{$username}' is already in use by another user.");
            header('Location: ' . url('admin/users.php'));
            exit;
        }

        try {
            if ($password !== '') {
                if (strlen($password) < 6) {
                    set_flash('danger', 'New password must be at least 6 characters.');
                    header('Location: ' . url('admin/users.php'));
                    exit;
                }
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, password_hash = ? WHERE id = ?");
                $stmt->execute([$fullName, $username, $role, $hashed, $id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, role = ? WHERE id = ?");
                $stmt->execute([$fullName, $username, $role, $id]);
            }

            set_flash('success', 'User profile updated successfully.');
        } catch (Throwable $e) {
            error_log("Failed to update user: " . $e->getMessage());
            set_flash('danger', 'Error updating user profile.');
        }

        header('Location: ' . url('admin/users.php'));
        exit;
    }

    // --- Action: DELETE USER ---
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        // Section 14: An administrator must not be allowed to delete their own account
        if ($id === $currentAdminId) {
            set_flash('danger', 'Security Violation: You cannot delete your own currently logged-in administrator account.');
            header('Location: ' . url('admin/users.php'));
            exit;
        }

        // Check if user has associated stock movements
        $checkMv = $db->prepare("SELECT COUNT(*) AS mv_count FROM stock_movements WHERE created_by = ?");
        $checkMv->execute([$id]);
        $mvCount = (int)$checkMv->fetch()['mv_count'];

        if ($mvCount > 0) {
            set_flash('danger', "Cannot delete user: This user has logged {$mvCount} stock movement operations. Deleting them would compromise transaction integrity.");
            header('Location: ' . url('admin/users.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'User deleted successfully.');
        } catch (Throwable $e) {
            error_log("Failed to delete user: " . $e->getMessage());
            set_flash('danger', 'Error deleting user.');
        }

        header('Location: ' . url('admin/users.php'));
        exit;
    }
}

// 2. Fetch User List
$stmt = $db->query("SELECT id, full_name, username, role, is_active, created_at, updated_at FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();

$pageTitle = 'User Management';
$pageSubtitle = 'Manage system access roles, staff accounts, and security credentials';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="filter-bar">
    <div style="flex: 1;">
        <span style="font-weight: 600; color: var(--text-primary);">Total Users: <?php echo count($users); ?></span>
    </div>
    <button type="button" class="btn btn-primary" onclick="openModal('addUserModal')">
        <?php echo render_icon('plus', 'icon'); ?>
        <span>Create New User</span>
    </button>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <?php echo render_icon('users', 'icon'); ?>
            <span>System Users &amp; Roles</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Account Status</th>
                        <th>Date Registered</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong><?php echo e($u['full_name']); ?></strong>
                                <?php if ((int)$u['id'] === $currentAdminId): ?>
                                    <span class="badge badge-primary" style="font-size: 10px; margin-left: 6px;">Current Account</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo e($u['username']); ?></code></td>
                            <td>
                                <span class="user-role-badge role-<?php echo e($u['role']); ?>">
                                    <?php echo strtoupper($u['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int)$u['is_active'] === 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($u['created_at'])); ?></td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <button 
                                        type="button" 
                                        class="btn btn-light btn-sm"
                                        title="Edit User"
                                        onclick='openEditUserModal(<?php echo json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                    >
                                        <?php echo render_icon('edit', 'icon'); ?>
                                        <span>Edit</span>
                                    </button>

                                    <?php if ((int)$u['id'] !== $currentAdminId): ?>
                                        <form method="POST" action="<?php echo e(url('admin/users.php')); ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete user <?php echo e(addslashes($u['username'])); ?>?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                            <button type="submit" class="btn btn-light btn-sm" style="color: var(--danger);" title="Delete User">
                                                <?php echo render_icon('trash', 'icon'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: ADD USER                            -->
<!-- ========================================== -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">Create New System User</div>
            <button type="button" class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="<?php echo e(url('admin/users.php')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_full_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="add_full_name" name="full_name" class="form-control" placeholder="e.g. Sarah Jenkins" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_username">Username <span class="required">*</span></label>
                    <input type="text" id="add_username" name="username" class="form-control" placeholder="e.g. sjenkins" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_role">System Access Role <span class="required">*</span></label>
                    <select id="add_role" name="role" class="form-control" required>
                        <option value="user">User (Read-only Catalog &amp; Dashboard)</option>
                        <option value="admin">Admin (Full Warehouse Control)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_password">Initial Password <span class="required">*</span></label>
                    <input type="password" id="add_password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT USER                           -->
<!-- ========================================== -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">Edit User Account</div>
            <button type="button" class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST" action="<?php echo e(url('admin/users.php')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_user_id">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit_full_name">Full Name <span class="required">*</span></label>
                    <input type="text" id="edit_full_name" name="full_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_username">Username <span class="required">*</span></label>
                    <input type="text" id="edit_username" name="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_role">System Role <span class="required">*</span></label>
                    <select id="edit_role" name="role" class="form-control" required>
                        <option value="user">User (Read-only Catalog &amp; Dashboard)</option>
                        <option value="admin">Admin (Full Control)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_password">Reset Password</label>
                    <input type="password" id="edit_password" name="password" class="form-control" placeholder="Leave blank to retain current password">
                    <div class="form-text">Only enter text here if you wish to set a new password.</div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_password').value = '';
    openModal('editUserModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
