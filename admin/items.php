<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db_connection();
$userId = current_user_id();

// ==========================================
// 1. Handle Form Submissions (POST + CSRF)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token  = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($token)) {
        set_flash('danger', 'Security check failed: Invalid or expired CSRF token.');
        header('Location: ' . url('admin/items.php'));
        exit;
    }

    // --- Action: ADD ITEM ---
    if ($action === 'add') {
        $itemCode     = trim($_POST['item_code'] ?? '');
        $itemName     = trim($_POST['item_name'] ?? '');
        $category     = trim($_POST['category'] ?? '');
        $location     = trim($_POST['location'] ?? '');
        $quantity     = (int)($_POST['quantity'] ?? 0);
        $unit         = trim($_POST['unit'] ?? 'pcs');
        $reorderLevel = (int)($_POST['reorder_level'] ?? 10);
        $icon         = trim($_POST['icon'] ?? 'box');

        if ($itemCode === '' || $itemName === '' || $category === '' || $location === '' || $unit === '') {
            set_flash('danger', 'All required item fields must be provided.');
            header('Location: ' . url('admin/items.php'));
            exit;
        }

        if ($quantity < 0 || $reorderLevel < 0) {
            set_flash('danger', 'Quantity and Reorder Level must be non-negative values.');
            header('Location: ' . url('admin/items.php'));
            exit;
        }

        // Check Unique Item Code
        $stmt = $db->prepare("SELECT id FROM items WHERE item_code = ? LIMIT 1");
        $stmt->execute([$itemCode]);
        if ($stmt->fetch()) {
            set_flash('danger', "Item Code '{$itemCode}' is already in use. Please use a unique code.");
            header('Location: ' . url('admin/items.php'));
            exit;
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO items (item_code, item_name, category, location, quantity, unit, reorder_level, icon) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$itemCode, $itemName, $category, $location, $quantity, $unit, $reorderLevel, $icon]);
            $newItemId = (int)$db->lastInsertId();

            // Trace initial balance with stock movement if quantity > 0
            if ($quantity > 0) {
                $mvStmt = $db->prepare("INSERT INTO stock_movements (item_id, movement_type, quantity, movement_date, reference_note, created_by) 
                                        VALUES (?, 'IN', ?, NOW(), 'Initial Opening Balance on Item Creation', ?)");
                $mvStmt->execute([$newItemId, $quantity, $userId]);
            }

            $db->commit();
            set_flash('success', "Item '{$itemName}' ({$itemCode}) was successfully added.");
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Failed to add item: " . $e->getMessage());
            set_flash('danger', 'Failed to save item. Database error encountered.');
        }

        header('Location: ' . url('admin/items.php'));
        exit;
    }

    // --- Action: EDIT ITEM ---
    if ($action === 'edit') {
        $id           = (int)($_POST['id'] ?? 0);
        $itemName     = trim($_POST['item_name'] ?? '');
        $category     = trim($_POST['category'] ?? '');
        $location     = trim($_POST['location'] ?? '');
        $unit         = trim($_POST['unit'] ?? 'pcs');
        $reorderLevel = (int)($_POST['reorder_level'] ?? 10);
        $icon         = trim($_POST['icon'] ?? 'box');

        if ($id <= 0 || $itemName === '' || $category === '' || $location === '' || $unit === '') {
            set_flash('danger', 'Invalid item details or missing required fields.');
            header('Location: ' . url('admin/items.php'));
            exit;
        }

        if ($reorderLevel < 0) {
            set_flash('danger', 'Reorder level cannot be negative.');
            header('Location: ' . url('admin/items.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("UPDATE items SET item_name = ?, category = ?, location = ?, unit = ?, reorder_level = ?, icon = ? WHERE id = ?");
            $stmt->execute([$itemName, $category, $location, $unit, $reorderLevel, $icon, $id]);
            set_flash('success', "Item details updated successfully.");
        } catch (Throwable $e) {
            error_log("Failed to update item: " . $e->getMessage());
            set_flash('danger', 'Failed to update item.');
        }

        header('Location: ' . url('admin/items.php'));
        exit;
    }

    // --- Action: DELETE ITEM ---
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            set_flash('danger', 'Invalid item ID for deletion.');
            header('Location: ' . url('admin/items.php'));
            exit;
        }

        // Section 9 Rule: Check if item is referenced by stock movements
        $checkStmt = $db->prepare("SELECT COUNT(*) AS mv_count FROM stock_movements WHERE item_id = ?");
        $checkStmt->execute([$id]);
        $mvCount = (int)$checkStmt->fetch()['mv_count'];

        if ($mvCount > 0) {
            set_flash('danger', "Cannot delete this item: It is linked to {$mvCount} historical stock movement records. Deleting it would break audit logs and inventory integrity.");
            header('Location: ' . url('admin/items.php'));
            exit;
        }

        try {
            $stmt = $db->prepare("DELETE FROM items WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'Item has been deleted successfully.');
        } catch (Throwable $e) {
            error_log("Delete item failed: " . $e->getMessage());
            set_flash('danger', 'Error deleting item.');
        }

        header('Location: ' . url('admin/items.php'));
        exit;
    }
}

// ==========================================
// 2. Fetch Items with Search & Category Filters
// ==========================================
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

$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Distinct categories for filter dropdown
$catStmt = $db->query("SELECT DISTINCT category FROM items ORDER BY category ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

$availableIcons = get_item_icons();

$pageTitle = 'Inventory Item Management';
$pageSubtitle = 'Manage warehouse catalog, track stock thresholds, and register products';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Top Action & Filter Toolbar -->
<div class="filter-bar">
    <form method="GET" action="<?php echo e(url('admin/items.php')); ?>" style="display: flex; flex-wrap: wrap; gap: 12px; flex: 1;">
        <div class="filter-input-wrap">
            <?php echo render_icon('search', 'icon'); ?>
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="Search code, name, or location..." 
                value="<?php echo e($search); ?>"
            >
        </div>

        <div style="min-width: 180px;">
            <select name="category" class="form-control" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo e($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                        <?php echo e($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-outline">
            <?php echo render_icon('filter', 'icon'); ?>
            <span>Filter</span>
        </button>

        <?php if ($search !== '' || $category !== ''): ?>
            <a href="<?php echo e(url('admin/items.php')); ?>" class="btn btn-light">Reset</a>
        <?php endif; ?>
    </form>

    <button type="button" class="btn btn-primary" onclick="openModal('addItemModal')">
        <?php echo render_icon('plus', 'icon'); ?>
        <span>Add New Item</span>
    </button>
</div>

<!-- Items Table Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <?php echo render_icon('items', 'icon'); ?>
            <span>Inventory Items (<?php echo count($items); ?> total)</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($items)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                <div style="font-size: 36px; margin-bottom: 8px;">📦</div>
                <strong>No inventory items found matching your criteria.</strong>
                <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Try refining your search terms or add a new item.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Item Code</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Quantity</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $isLow = (int)$item['quantity'] <= (int)$item['reorder_level']; ?>
                            <tr class="<?php echo $isLow ? 'low-stock-row' : ''; ?>">
                                <td>
                                    <div class="item-title-cell">
                                        <div class="item-badge-icon" title="<?php echo e($item['icon']); ?>">
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
                                        <span class="badge badge-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <a href="<?php echo e(url('admin/movements.php?item_id=' . (int)$item['id'])); ?>" class="btn btn-outline btn-sm" title="Stock Operations">
                                            <?php echo render_icon('movements', 'icon'); ?>
                                        </a>

                                        <button 
                                            type="button" 
                                            class="btn btn-light btn-sm" 
                                            title="Edit Item"
                                            onclick='openEditModal(<?php echo json_encode($item, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                        >
                                            <?php echo render_icon('edit', 'icon'); ?>
                                        </button>

                                        <form method="POST" action="<?php echo e(url('admin/items.php')); ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete item: <?php echo e(addslashes($item['item_name'])); ?>?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                            <button type="submit" class="btn btn-light btn-sm" style="color: var(--danger);" title="Delete Item">
                                                <?php echo render_icon('trash', 'icon'); ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: ADD NEW ITEM                        -->
<!-- ========================================== -->
<div class="modal-overlay" id="addItemModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">Add New Warehouse Item</div>
            <button type="button" class="modal-close" onclick="closeModal('addItemModal')">&times;</button>
        </div>
        <form method="POST" action="<?php echo e(url('admin/items.php')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="add_item_code">Item Code <span class="required">*</span></label>
                        <input type="text" id="add_item_code" name="item_code" class="form-control" placeholder="e.g. ITM-ELC-009" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_item_name">Item Name <span class="required">*</span></label>
                        <input type="text" id="add_item_name" name="item_name" class="form-control" placeholder="e.g. Laser Barcode Reader" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="add_category">Category <span class="required">*</span></label>
                        <input type="text" id="add_category" name="category" class="form-control" placeholder="e.g. Electronics, Tools, Safety" required list="categoryList">
                        <datalist id="categoryList">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo e($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_location">Warehouse Location <span class="required">*</span></label>
                        <input type="text" id="add_location" name="location" class="form-control" placeholder="e.g. Aisle A-2, Bin 10" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="add_quantity">Initial Quantity</label>
                        <input type="number" id="add_quantity" name="quantity" class="form-control" value="0" min="0" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_unit">Unit of Measure <span class="required">*</span></label>
                        <input type="text" id="add_unit" name="unit" class="form-control" value="pcs" placeholder="pcs, units, boxes, rolls" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="add_reorder">Reorder Threshold Level</label>
                        <input type="number" id="add_reorder" name="reorder_level" class="form-control" value="10" min="0" required>
                        <div class="form-text">Triggers low-stock warning when quantity falls to or below this level.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_icon">Visual Item Icon</label>
                        <select id="add_icon" name="icon" class="form-control">
                            <?php foreach ($availableIcons as $icoKey => $icoDesc): ?>
                                <option value="<?php echo e($icoKey); ?>"><?php echo e($icoDesc); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addItemModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Item</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDIT ITEM                           -->
<!-- ========================================== -->
<div class="modal-overlay" id="editItemModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title">Edit Item Details</div>
            <button type="button" class="modal-close" onclick="closeModal('editItemModal')">&times;</button>
        </div>
        <form method="POST" action="<?php echo e(url('admin/items.php')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Item Code</label>
                    <input type="text" id="edit_item_code_disp" class="form-control" disabled style="background: #f1f5f9; cursor: not-allowed;">
                    <div class="form-text">Item codes serve as unique warehouse identifiers and cannot be altered.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_item_name">Item Name <span class="required">*</span></label>
                    <input type="text" id="edit_item_name" name="item_name" class="form-control" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="edit_category">Category <span class="required">*</span></label>
                        <input type="text" id="edit_category" name="category" class="form-control" required list="categoryList">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_location">Location <span class="required">*</span></label>
                        <input type="text" id="edit_location" name="location" class="form-control" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="edit_unit">Unit of Measure <span class="required">*</span></label>
                        <input type="text" id="edit_unit" name="unit" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_reorder_level">Reorder Level</label>
                        <input type="number" id="edit_reorder_level" name="reorder_level" class="form-control" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_icon">Visual Item Icon</label>
                    <select id="edit_icon" name="icon" class="form-control">
                        <?php foreach ($availableIcons as $icoKey => $icoDesc): ?>
                            <option value="<?php echo e($icoKey); ?>"><?php echo e($icoDesc); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editItemModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Item</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_item_code_disp').value = item.item_code;
    document.getElementById('edit_item_name').value = item.item_name;
    document.getElementById('edit_category').value = item.category;
    document.getElementById('edit_location').value = item.location;
    document.getElementById('edit_unit').value = item.unit;
    document.getElementById('edit_reorder_level').value = item.reorder_level;
    document.getElementById('edit_icon').value = item.icon || 'box';
    openModal('editItemModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
