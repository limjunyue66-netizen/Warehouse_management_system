<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$db = get_db_connection();

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

$sql = "SELECT * FROM items WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (item_code LIKE ? OR item_name LIKE ? OR location LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($category !== '') {
    $sql .= " AND category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY item_name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Distinct categories for filter
$cats = $db->query("SELECT DISTINCT category FROM items ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Inventory Catalog';
$pageSubtitle = 'Search items, locate bins, and check real-time stock balances';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Search and Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="<?php echo e(url('user/items.php')); ?>" style="display: flex; flex-wrap: wrap; gap: 12px; flex: 1;">
        <div class="filter-input-wrap">
            <?php echo render_icon('search', 'icon'); ?>
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="Search by code, item name, or bin location..." 
                value="<?php echo e($search); ?>"
            >
        </div>

        <div style="min-width: 180px;">
            <select name="category" class="form-control" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?php echo e($c); ?>" <?php echo $category === $c ? 'selected' : ''; ?>>
                        <?php echo e($c); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-outline">
            <?php echo render_icon('filter', 'icon'); ?>
            <span>Filter</span>
        </button>

        <?php if ($search !== '' || $category !== ''): ?>
            <a href="<?php echo e(url('user/items.php')); ?>" class="btn btn-light">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Inventory Catalog Table Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <?php echo render_icon('items', 'icon'); ?>
            <span>Warehouse Items (<?php echo count($items); ?> found)</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($items)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                <div style="font-size: 32px; margin-bottom: 8px;">🔍</div>
                <strong>No inventory items match your search.</strong>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Item Code</th>
                            <th>Category</th>
                            <th>Warehouse Location</th>
                            <th>Current Stock</th>
                            <th>Reorder Threshold</th>
                            <th>Stock Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $isLow = (int)$item['quantity'] <= (int)$item['reorder_level']; ?>
                            <tr class="<?php echo $isLow ? 'low-stock-row' : ''; ?>">
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
                                    <strong style="<?php echo $isLow ? 'color: var(--danger); font-size: 15px;' : 'color: var(--text-primary);'; ?>">
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
                                    <?php elseif ($isLow): ?>
                                        <span class="badge badge-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Sufficient</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?php echo e(url('user/item-details.php?id=' . (int)$item['id'])); ?>" class="btn btn-outline btn-sm">
                                        <?php echo render_icon('eye', 'icon'); ?>
                                        <span>View Details</span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
