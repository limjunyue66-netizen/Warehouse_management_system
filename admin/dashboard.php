<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db_connection();

// 1. Total Items & Total Stock Quantity
$stmt = $db->query("SELECT COUNT(*) AS total_items, COALESCE(SUM(quantity), 0) AS total_quantity FROM items");
$totals = $stmt->fetch();
$totalItems = (int)$totals['total_items'];
$totalQuantity = (int)$totals['total_quantity'];

// 2. Low Stock Items Count
$stmt = $db->query("SELECT COUNT(*) AS low_stock_count FROM items WHERE quantity <= reorder_level");
$lowStockCount = (int)$stmt->fetch()['low_stock_count'];

// 3. Today's Stock IN and Stock OUT
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT 
    COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE 0 END), 0) AS today_in,
    COALESCE(SUM(CASE WHEN movement_type = 'OUT' THEN quantity ELSE 0 END), 0) AS today_out
    FROM stock_movements 
    WHERE DATE(movement_date) = ?");
$stmt->execute([$today]);
$todayMovements = $stmt->fetch();
$todayIn = (int)$todayMovements['today_in'];
$todayOut = (int)$todayMovements['today_out'];

// 4. Detailed Low Stock Items List
$stmt = $db->query("SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level, icon 
    FROM items 
    WHERE quantity <= reorder_level 
    ORDER BY quantity ASC, item_name ASC");
$lowStockItems = $stmt->fetchAll();

// 5. Recent Movements
$stmt = $db->query("SELECT m.*, i.item_code, i.item_name, i.unit, i.icon, u.full_name AS operator_name
    FROM stock_movements m
    JOIN items i ON m.item_id = i.id
    JOIN users u ON m.created_by = u.id
    ORDER BY m.movement_date DESC, m.id DESC
    LIMIT 8");
$recentMovements = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard';
$pageSubtitle = 'Real-time Warehouse Operations & Critical Inventory Alerts';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Metric Summary Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Inventory Items</div>
            <div class="stat-value"><?php echo number_format($totalItems); ?></div>
        </div>
        <div class="stat-icon-wrapper stat-icon-primary">
            <?php echo render_icon('items', 'icon'); ?>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Total Stock Quantity</div>
            <div class="stat-value"><?php echo number_format($totalQuantity); ?></div>
        </div>
        <div class="stat-icon-wrapper stat-icon-info">
            <?php echo render_icon('package', 'icon'); ?>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Low Stock Alerts</div>
            <div class="stat-value" style="<?php echo $lowStockCount > 0 ? 'color: var(--danger);' : ''; ?>">
                <?php echo number_format($lowStockCount); ?>
            </div>
        </div>
        <div class="stat-icon-wrapper <?php echo $lowStockCount > 0 ? 'stat-icon-danger' : 'stat-icon-success'; ?>">
            <?php echo render_icon('alert', 'icon'); ?>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Today's Stock IN</div>
            <div class="stat-value" style="color: var(--success);"><?php echo number_format($todayIn); ?></div>
        </div>
        <div class="stat-icon-wrapper stat-icon-success">
            <?php echo render_icon('arrow-down', 'icon'); ?>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Today's Stock OUT</div>
            <div class="stat-value" style="color: var(--warning);"><?php echo number_format($todayOut); ?></div>
        </div>
        <div class="stat-icon-wrapper stat-icon-warning">
            <?php echo render_icon('arrow-up', 'icon'); ?>
        </div>
    </div>
</div>

<!-- Low Stock Section (Critical Section 7) -->
<div class="card">
    <div class="card-header" style="<?php echo $lowStockCount > 0 ? 'border-left: 4px solid var(--danger);' : ''; ?>">
        <div class="card-title">
            <?php echo render_icon('alert', 'icon'); ?>
            <span>Low Stock Attention Required (Quantity &le; Reorder Level)</span>
            <?php if ($lowStockCount > 0): ?>
                <span class="badge badge-danger"><?php echo $lowStockCount; ?> Critical Items</span>
            <?php else: ?>
                <span class="badge badge-success">All Stock Levels Healthy</span>
            <?php endif; ?>
        </div>
        <a href="<?php echo e(url('admin/items.php')); ?>" class="btn btn-outline btn-sm">View All Items</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($lowStockItems)): ?>
            <div style="padding: 32px; text-align: center; color: var(--text-secondary);">
                <div style="color: var(--success); font-size: 24px; margin-bottom: 6px;">✓</div>
                <strong>No low stock alerts at this moment.</strong>
                <p style="font-size: 13px; color: var(--text-muted);">All item quantities are currently above their minimum reorder thresholds.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Code</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Current Stock</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockItems as $item): ?>
                            <tr class="low-stock-row">
                                <td>
                                    <div class="item-title-cell">
                                        <div class="item-badge-icon">
                                            <?php echo render_icon($item['icon'] ?? 'box', 'icon'); ?>
                                        </div>
                                        <div>
                                            <div class="item-main-name"><?php echo e($item['item_name']); ?></div>
                                            <div class="item-sub-desc"><?php echo e($item['unit']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?php echo e($item['item_code']); ?></code></td>
                                <td><span class="badge badge-secondary"><?php echo e($item['category']); ?></span></td>
                                <td><?php echo e($item['location']); ?></td>
                                <td>
                                    <strong style="color: var(--danger); font-size: 15px;">
                                        <?php echo number_format((int)$item['quantity']); ?>
                                    </strong> 
                                    <span style="font-size: 12px; color: var(--text-muted);"><?php echo e($item['unit']); ?></span>
                                </td>
                                <td>
                                    <?php echo number_format((int)$item['reorder_level']); ?> 
                                    <span style="font-size: 12px; color: var(--text-muted);"><?php echo e($item['unit']); ?></span>
                                </td>
                                <td>
                                    <?php if ((int)$item['quantity'] === 0): ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Low Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?php echo e(url('admin/movements.php?quick_in=' . (int)$item['id'])); ?>" class="btn btn-success btn-sm">
                                        <?php echo render_icon('plus', 'icon'); ?>
                                        <span>Restock IN</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Stock Movements Audit Trail -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <?php echo render_icon('movements', 'icon'); ?>
            <span>Recent Stock Movements Activity</span>
        </div>
        <a href="<?php echo e(url('admin/movements.php')); ?>" class="btn btn-outline btn-sm">View Full History</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($recentMovements)): ?>
            <div style="padding: 32px; text-align: center; color: var(--text-secondary);">
                No stock movement activity recorded yet.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reference / Note</th>
                            <th>Logged By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMovements as $mv): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($mv['movement_date'])); ?></td>
                                <td>
                                    <div class="item-title-cell">
                                        <div class="item-badge-icon">
                                            <?php echo render_icon($mv['icon'] ?? 'box', 'icon'); ?>
                                        </div>
                                        <div>
                                            <div class="item-main-name"><?php echo e($mv['item_name']); ?></div>
                                            <div class="item-sub-desc"><code><?php echo e($mv['item_code']); ?></code></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($mv['movement_type']); ?>">
                                        <?php echo $mv['movement_type'] === 'IN' ? '↓ IN' : '↑ OUT'; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo number_format((int)$mv['quantity']); ?></strong> 
                                    <span style="font-size: 12px; color: var(--text-muted);"><?php echo e($mv['unit']); ?></span>
                                </td>
                                <td><?php echo e($mv['reference_note'] ?? '—'); ?></td>
                                <td>
                                    <span class="badge badge-secondary"><?php echo e($mv['operator_name']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
