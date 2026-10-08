<?php
declare(strict_types=1);

/**
 * Warehouse Management System - Responsive Sidebar Navigation
 */

$user = current_user();
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$role = current_user_role();
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand-icon">
            <?php echo render_icon('box', 'icon'); ?>
        </div>
        <div>
            <div class="sidebar-brand-text">WMS Pro</div>
            <div class="sidebar-brand-subtitle">Warehouse Suite</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($role === 'admin'): ?>
            <div class="nav-section-title">Operations</div>
            
            <a href="<?php echo e(url('admin/dashboard.php')); ?>" class="nav-item <?php echo str_contains($currentUri, 'admin/dashboard.php') ? 'active' : ''; ?>">
                <?php echo render_icon('dashboard', 'icon'); ?>
                <span>Dashboard</span>
            </a>

            <a href="<?php echo e(url('admin/items.php')); ?>" class="nav-item <?php echo str_contains($currentUri, 'admin/items.php') ? 'active' : ''; ?>">
                <?php echo render_icon('items', 'icon'); ?>
                <span>Inventory Items</span>
            </a>

            <a href="<?php echo e(url('admin/movements.php')); ?>" class="nav-item <?php echo str_contains($currentUri, 'admin/movements.php') ? 'active' : ''; ?>">
                <?php echo render_icon('movements', 'icon'); ?>
                <span>Stock Movements</span>
            </a>

            <div class="nav-section-title">Analytics & Control</div>

            <a href="<?php echo e(url('admin/reports.php')); ?>" class="nav-item <?php echo str_contains($currentUri, 'admin/reports.php') ? 'active' : ''; ?>">
                <?php echo render_icon('reports', 'icon'); ?>
                <span>Reports & Audit</span>
            </a>

            <a href="<?php echo e(url('admin/users.php')); ?>" class="nav-item <?php echo str_contains($currentUri, 'admin/users.php') ? 'active' : ''; ?>">
                <?php echo render_icon('users', 'icon'); ?>
                <span>User Management</span>
            </a>

        <?php else: ?>
            <div class="nav-section-title">Operator Menu</div>

            <a href="<?php echo e(url('user/dashboard.php')); ?>" class="nav-item <?php echo str_contains($currentUri, 'user/dashboard.php') ? 'active' : ''; ?>">
                <?php echo render_icon('dashboard', 'icon'); ?>
                <span>Dashboard</span>
            </a>

            <a href="<?php echo e(url('user/items.php')); ?>" class="nav-item <?php echo (str_contains($currentUri, 'user/items.php') || str_contains($currentUri, 'user/item-details.php')) ? 'active' : ''; ?>">
                <?php echo render_icon('items', 'icon'); ?>
                <span>Inventory Catalog</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-mini-profile">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['full_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo e($user['full_name'] ?? 'User'); ?></div>
                <span class="user-role-badge role-<?php echo e($user['role'] ?? 'user'); ?>">
                    <?php echo e($user['role'] ?? 'user'); ?>
                </span>
            </div>
        </div>
    </div>
</aside>
