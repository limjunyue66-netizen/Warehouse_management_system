<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', 'Invalid item identifier specified.');
    header('Location: ' . url('user/items.php'));
    exit;
}

$db = get_db_connection();

// 1. Fetch Item Details
$stmt = $db->prepare("SELECT * FROM items WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('danger', 'Item not found in warehouse registry.');
    header('Location: ' . url('user/items.php'));
    exit;
}

// 2. Fetch Item Stock Movement History
$stmt = $db->prepare("SELECT m.*, u.full_name AS operator_name 
                      FROM stock_movements m 
                      JOIN users u ON m.created_by = u.id 
                      WHERE m.item_id = ? 
                      ORDER BY m.movement_date DESC, m.id DESC");
$stmt->execute([$id]);
$history = $stmt->fetchAll();

$isLow = (int)$item['quantity'] <= (int)$item['reorder_level'];

$pageTitle = 'Item Details: ' . $item['item_name'];
$pageSubtitle = 'Detailed warehouse specifications and movement ledger';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="<?php echo e(url('user/items.php')); ?>" class="btn btn-outline btn-sm">
        &larr; Back to Inventory Catalog
    </a>
</div>

<!-- Item Specification Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <div class="item-badge-icon" style="width: 32px; height: 32px;">
                <?php echo render_icon($item['icon'] ?? 'box', 'icon'); ?>
            </div>
            <span><?php echo e($item['item_name']); ?></span>
            <span style="font-size: 13px; color: var(--text-muted); font-weight: normal;">(<code><?php echo e($item['item_code']); ?></code>)</span>
        </div>
        <div>
            <?php if ((int)$item['quantity'] === 0): ?>
                <span class="badge badge-danger">Out of Stock</span>
            <?php elseif ($isLow): ?>
                <span class="badge badge-warning">Low Stock Warning</span>
            <?php else: ?>
                <span class="badge badge-success">Sufficient Stock</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px;">
            <div>
                <div class="stat-label">Item Code</div>
                <div style="font-size: 16px; font-weight: 600;"><code><?php echo e($item['item_code']); ?></code></div>
            </div>

            <div>
                <div class="stat-label">Category</div>
                <div style="font-size: 16px; font-weight: 600;"><?php echo e($item['category']); ?></div>
            </div>

            <div>
                <div class="stat-label">Warehouse Storage Location</div>
                <div style="font-size: 16px; font-weight: 600; color: var(--primary);"><?php echo e($item['location']); ?></div>
            </div>

            <div>
                <div class="stat-label">Current Stock Balance</div>
                <div style="font-size: 22px; font-weight: 700; color: <?php echo $isLow ? 'var(--danger)' : 'var(--text-primary)'; ?>;">
                    <?php echo number_format((int)$item['quantity']); ?> 
                    <span style="font-size: 14px; font-weight: 500; color: var(--text-muted);"><?php echo e($item['unit']); ?></span>
                </div>
            </div>

            <div>
                <div class="stat-label">Reorder Alert Level</div>
                <div style="font-size: 16px; font-weight: 600;">
                    <?php echo number_format((int)$item['reorder_level']); ?> <?php echo e($item['unit']); ?>
                </div>
            </div>

            <div>
                <div class="stat-label">Date Registered</div>
                <div style="font-size: 14px; color: var(--text-secondary);"><?php echo date('Y-m-d H:i', strtotime($item['created_at'])); ?></div>
            </div>

            <div>
                <div class="stat-label">Last Modified</div>
                <div style="font-size: 14px; color: var(--text-secondary);"><?php echo date('Y-m-d H:i', strtotime($item['updated_at'])); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Movement History Ledger -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <?php echo render_icon('movements', 'icon'); ?>
            <span>Stock Movement History for this Item</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($history)): ?>
            <div style="padding: 30px; text-align: center; color: var(--text-secondary);">
                No historical stock movements found for this item.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Movement Type</th>
                            <th>Quantity</th>
                            <th>Reference / Note</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($h['movement_date'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($h['movement_type']); ?>">
                                        <?php echo $h['movement_type'] === 'IN' ? '↓ STOCK IN' : '↑ STOCK OUT'; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo number_format((int)$h['quantity']); ?></strong> 
                                    <span style="font-size: 12px; color: var(--text-muted);"><?php echo e($item['unit']); ?></span>
                                </td>
                                <td><?php echo e($h['reference_note'] ?? '—'); ?></td>
                                <td><span class="badge badge-secondary"><?php echo e($h['operator_name']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
