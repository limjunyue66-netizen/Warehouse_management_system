<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db_connection();
$userId = (int)current_user_id();

// ==========================================
// 1. Process Stock Movement (Transaction + Lock)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Security validation failed: Invalid CSRF token.');
        header('Location: ' . url('admin/movements.php'));
        exit;
    }

    $itemId       = (int)($_POST['item_id'] ?? 0);
    $movementType = strtoupper(trim($_POST['movement_type'] ?? ''));
    $quantity     = (int)($_POST['quantity'] ?? 0);
    $reference    = trim($_POST['reference_note'] ?? '');

    // Validation
    if ($itemId <= 0) {
        set_flash('danger', 'Please select a valid item.');
        header('Location: ' . url('admin/movements.php'));
        exit;
    }

    if (!in_array($movementType, ['IN', 'OUT'], true)) {
        set_flash('danger', 'Invalid movement type. Must be IN or OUT.');
        header('Location: ' . url('admin/movements.php'));
        exit;
    }

    if ($quantity <= 0) {
        set_flash('danger', 'Movement quantity must be a positive number greater than 0.');
        header('Location: ' . url('admin/movements.php'));
        exit;
    }

    // Execute Transaction with Row-Level Locking (Section 11)
    try {
        $db->beginTransaction();

        // 1. Lock the item row using FOR UPDATE
        $lockStmt = $db->prepare("SELECT id, item_code, item_name, quantity, unit FROM items WHERE id = ? FOR UPDATE");
        $lockStmt->execute([$itemId]);
        $item = $lockStmt->fetch();

        if (!$item) {
            $db->rollBack();
            set_flash('danger', 'Item not found in database.');
            header('Location: ' . url('admin/movements.php'));
            exit;
        }

        $currentStock = (int)$item['quantity'];

        // 2. Validate OUT movement cannot exceed current stock (Section 10)
        if ($movementType === 'OUT' && $quantity > $currentStock) {
            $db->rollBack();
            set_flash('danger', "Transaction Rejected: Cannot dispatch {$quantity} {$item['unit']} of '{$item['item_name']}'. Current stock is only {$currentStock} {$item['unit']}. Negative inventory is not permitted.");
            header('Location: ' . url('admin/movements.php'));
            exit;
        }

        // 3. Calculate new quantity
        $newStock = ($movementType === 'IN') ? ($currentStock + $quantity) : ($currentStock - $quantity);

        // 4. Insert into stock_movements
        $mvStmt = $db->prepare("INSERT INTO stock_movements (item_id, movement_type, quantity, movement_date, reference_note, created_by) 
                                VALUES (?, ?, ?, NOW(), ?, ?)");
        $mvStmt->execute([$itemId, $movementType, $quantity, $reference !== '' ? $reference : null, $userId]);

        // 5. Update item stock
        $updStmt = $db->prepare("UPDATE items SET quantity = ? WHERE id = ?");
        $updStmt->execute([$newStock, $itemId]);

        $db->commit();

        $actionDesc = ($movementType === 'IN') ? 'received' : 'dispatched';
        set_flash('success', "Stock updated successfully: {$quantity} {$item['unit']} of '{$item['item_name']}' {$actionDesc}. New stock balance: {$newStock} {$item['unit']}.");
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Stock movement transaction failure: " . $e->getMessage());
        set_flash('danger', 'Transaction failed due to a database error. No stock was modified.');
    }

    header('Location: ' . url('admin/movements.php'));
    exit;
}

// ==========================================
// 2. Query Movement History with Filters
// ==========================================
$filterItem      = (int)($_GET['item_id'] ?? 0);
$filterType      = strtoupper(trim($_GET['movement_type'] ?? ''));
$filterStartDate = trim($_GET['start_date'] ?? '');
$filterEndDate   = trim($_GET['end_date'] ?? '');

// Pre-fill quick IN trigger if provided
$quickInId = (int)($_GET['quick_in'] ?? 0);
if ($quickInId > 0 && $filterItem === 0) {
    $filterItem = $quickInId;
}

$historySql = "SELECT m.*, i.item_code, i.item_name, i.unit, i.icon, u.full_name AS operator_name
               FROM stock_movements m
               JOIN items i ON m.item_id = i.id
               JOIN users u ON m.created_by = u.id
               WHERE 1=1";
$params = [];

if ($filterItem > 0) {
    $historySql .= " AND m.item_id = ?";
    $params[] = $filterItem;
}

if (in_array($filterType, ['IN', 'OUT'], true)) {
    $historySql .= " AND m.movement_type = ?";
    $params[] = $filterType;
}

if ($filterStartDate !== '') {
    $historySql .= " AND DATE(m.movement_date) >= ?";
    $params[] = $filterStartDate;
}

if ($filterEndDate !== '') {
    $historySql .= " AND DATE(m.movement_date) <= ?";
    $params[] = $filterEndDate;
}

$historySql .= " ORDER BY m.movement_date DESC, m.id DESC LIMIT 200";
$stmt = $db->prepare($historySql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

// All active items for dropdown
$itemsStmt = $db->query("SELECT id, item_code, item_name, quantity, unit, icon FROM items ORDER BY item_name ASC");
$allItems = $itemsStmt->fetchAll();

$pageTitle = 'Stock Movements';
$pageSubtitle = 'Record Stock IN / OUT transactions and audit real-time inventory ledger';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stock Action Form Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <?php echo render_icon('movements', 'icon'); ?>
            <span>Record New Stock Movement (IN / OUT)</span>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="<?php echo e(url('admin/movements.php')); ?>" id="movementForm">
            <?php echo csrf_field(); ?>

            <div class="form-grid">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" for="move_item_id">Select Item <span class="required">*</span></label>
                    <select name="item_id" id="move_item_id" class="form-control" required onchange="updateItemStockNotice(this)">
                        <option value="">-- Choose an Inventory Item --</option>
                        <?php foreach ($allItems as $itm): ?>
                            <option 
                                value="<?php echo (int)$itm['id']; ?>" 
                                data-stock="<?php echo (int)$itm['quantity']; ?>"
                                data-unit="<?php echo e($itm['unit']); ?>"
                                <?php echo ($filterItem === (int)$itm['id']) ? 'selected' : ''; ?>
                            >
                                [<?php echo e($itm['item_code']); ?>] <?php echo e($itm['item_name']); ?> (Current Stock: <?php echo number_format((int)$itm['quantity']); ?> <?php echo e($itm['unit']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="stockNotice" class="form-text" style="font-weight: 500;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="movement_type">Movement Type <span class="required">*</span></label>
                    <select name="movement_type" id="movement_type" class="form-control" required>
                        <option value="IN" <?php echo ($quickInId > 0) ? 'selected' : ''; ?>>Stock IN (Receiving / Restock)</option>
                        <option value="OUT">Stock OUT (Dispatch / Issue)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="quantity">Quantity <span class="required">*</span></label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" placeholder="e.g. 10" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="reference_note">Reference / Note / Order Number</label>
                <input type="text" name="reference_note" id="reference_note" class="form-control" placeholder="e.g. PO-89201, Dispatched to Production Line A, Delivery GRN-102">
                <div class="form-text">Every movement should include an audit reference for accountability.</div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                <button type="reset" class="btn btn-outline">Clear Form</button>
                <button type="submit" class="btn btn-primary btn-lg">
                    <?php echo render_icon('check', 'icon'); ?>
                    <span>Confirm & Execute Movement</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- History & Ledger Section -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <?php echo render_icon('reports', 'icon'); ?>
            <span>Movement History Ledger</span>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Bar -->
        <form method="GET" action="<?php echo e(url('admin/movements.php')); ?>" class="filter-bar" style="margin-bottom: 24px;">
            <div style="flex: 1; min-width: 200px;">
                <select name="item_id" class="form-control">
                    <option value="">All Inventory Items</option>
                    <?php foreach ($allItems as $itm): ?>
                        <option value="<?php echo (int)$itm['id']; ?>" <?php echo ($filterItem === (int)$itm['id']) ? 'selected' : ''; ?>>
                            <?php echo e($itm['item_name']); ?> (<?php echo e($itm['item_code']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="min-width: 140px;">
                <select name="movement_type" class="form-control">
                    <option value="">All Types (IN / OUT)</option>
                    <option value="IN" <?php echo ($filterType === 'IN') ? 'selected' : ''; ?>>Stock IN Only</option>
                    <option value="OUT" <?php echo ($filterType === 'OUT') ? 'selected' : ''; ?>>Stock OUT Only</option>
                </select>
            </div>

            <div style="min-width: 140px;">
                <input type="date" name="start_date" class="form-control" value="<?php echo e($filterStartDate); ?>" title="Start Date">
            </div>

            <div style="min-width: 140px;">
                <input type="date" name="end_date" class="form-control" value="<?php echo e($filterEndDate); ?>" title="End Date">
            </div>

            <button type="submit" class="btn btn-outline">Filter</button>
            <?php if ($filterItem > 0 || $filterType !== '' || $filterStartDate !== '' || $filterEndDate !== ''): ?>
                <a href="<?php echo e(url('admin/movements.php')); ?>" class="btn btn-light">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Movement Table -->
        <?php if (empty($movements)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                <div style="font-size: 32px; margin-bottom: 8px;">📋</div>
                <strong>No movement records found matching your filters.</strong>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reference / Note</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movements as $m): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($m['movement_date'])); ?></td>
                                <td><code><?php echo e($m['item_code']); ?></code></td>
                                <td>
                                    <div class="item-title-cell">
                                        <div class="item-badge-icon" style="width: 28px; height: 28px;">
                                            <?php echo render_icon($m['icon'] ?? 'box', 'icon'); ?>
                                        </div>
                                        <span><?php echo e($m['item_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($m['movement_type']); ?>">
                                        <?php echo $m['movement_type'] === 'IN' ? '↓ STOCK IN' : '↑ STOCK OUT'; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo number_format((int)$m['quantity']); ?></strong> 
                                    <span style="font-size: 12px; color: var(--text-muted);"><?php echo e($m['unit']); ?></span>
                                </td>
                                <td><?php echo e($m['reference_note'] ?? '—'); ?></td>
                                <td>
                                    <span class="badge badge-secondary"><?php echo e($m['operator_name']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateItemStockNotice(selectElem) {
    const notice = document.getElementById('stockNotice');
    const selected = selectElem.options[selectElem.selectedIndex];
    if (selected && selected.value) {
        const stock = selected.getAttribute('data-stock');
        const unit = selected.getAttribute('data-unit');
        notice.innerHTML = `Available in warehouse: <strong style="color: var(--primary);">${stock} ${unit}</strong>`;
    } else {
        notice.innerHTML = '';
    }
}
// Trigger on page load if option pre-selected
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('move_item_id');
    if (sel && sel.value) updateItemStockNotice(sel);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
