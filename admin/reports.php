<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db_connection();

// 1. Collect Filter Inputs
$filterItemId   = (int)($_GET['item_id'] ?? 0);
$filterCategory = trim($_GET['category'] ?? '');
$filterType     = strtoupper(trim($_GET['movement_type'] ?? ''));
$filterStartDate= trim($_GET['start_date'] ?? '');
$filterEndDate  = trim($_GET['end_date'] ?? '');

// 2. Build Query for Detailed Records
$sql = "SELECT m.*, i.item_code, i.item_name, i.category, i.unit, i.icon, u.full_name AS operator_name
        FROM stock_movements m
        JOIN items i ON m.item_id = i.id
        JOIN users u ON m.created_by = u.id
        WHERE 1=1";
$params = [];

if ($filterItemId > 0) {
    $sql .= " AND m.item_id = ?";
    $params[] = $filterItemId;
}

if ($filterCategory !== '') {
    $sql .= " AND i.category = ?";
    $params[] = $filterCategory;
}

if (in_array($filterType, ['IN', 'OUT'], true)) {
    $sql .= " AND m.movement_type = ?";
    $params[] = $filterType;
}

if ($filterStartDate !== '') {
    $sql .= " AND DATE(m.movement_date) >= ?";
    $params[] = $filterStartDate;
}

if ($filterEndDate !== '') {
    $sql .= " AND DATE(m.movement_date) <= ?";
    $params[] = $filterEndDate;
}

$sql .= " ORDER BY m.movement_date DESC, m.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// 3. Calculate Summary Statistics
$totalIn = 0;
$totalOut = 0;
foreach ($records as $r) {
    if ($r['movement_type'] === 'IN') {
        $totalIn += (int)$r['quantity'];
    } elseif ($r['movement_type'] === 'OUT') {
        $totalOut += (int)$r['quantity'];
    }
}
$netMovement = $totalIn - $totalOut;

// Distinct categories & items for dropdowns
$cats = $db->query("SELECT DISTINCT category FROM items ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
$itemsList = $db->query("SELECT id, item_code, item_name FROM items ORDER BY item_name ASC")->fetchAll();

$pageTitle = 'Inventory & Movement Reports';
$pageSubtitle = 'Comprehensive inventory tracking, net balance calculation, and print-ready reports';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Print-Only Header -->
<div class="print-only" style="margin-bottom: 24px; border-bottom: 2px solid #333; padding-bottom: 12px;">
    <h2 style="margin: 0; font-size: 24px;"><?php echo e(APP_NAME); ?> — Audit Report</h2>
    <div style="font-size: 13px; color: #555; margin-top: 4px;">
        Generated on: <?php echo date('Y-m-d H:i:s'); ?> | Auditor: <?php echo e(current_user()['full_name']); ?>
    </div>
</div>

<!-- Filter Toolbar (Hidden when printing) -->
<div class="filter-bar no-print">
    <form method="GET" action="<?php echo e(url('admin/reports.php')); ?>" style="display: flex; flex-wrap: wrap; gap: 12px; flex: 1;">
        <div style="flex: 1; min-width: 180px;">
            <select name="item_id" class="form-control">
                <option value="">All Inventory Items</option>
                <?php foreach ($itemsList as $it): ?>
                    <option value="<?php echo (int)$it['id']; ?>" <?php echo $filterItemId === (int)$it['id'] ? 'selected' : ''; ?>>
                        <?php echo e($it['item_name']); ?> (<?php echo e($it['item_code']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width: 150px;">
            <select name="category" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?php echo e($c); ?>" <?php echo $filterCategory === $c ? 'selected' : ''; ?>>
                        <?php echo e($c); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width: 130px;">
            <select name="movement_type" class="form-control">
                <option value="">All Types (IN &amp; OUT)</option>
                <option value="IN" <?php echo $filterType === 'IN' ? 'selected' : ''; ?>>Stock IN Only</option>
                <option value="OUT" <?php echo $filterType === 'OUT' ? 'selected' : ''; ?>>Stock OUT Only</option>
            </select>
        </div>

        <div style="min-width: 135px;">
            <input type="date" name="start_date" class="form-control" value="<?php echo e($filterStartDate); ?>" title="Start Date">
        </div>

        <div style="min-width: 135px;">
            <input type="date" name="end_date" class="form-control" value="<?php echo e($filterEndDate); ?>" title="End Date">
        </div>

        <button type="submit" class="btn btn-outline">
            <?php echo render_icon('filter', 'icon'); ?>
            <span>Generate Report</span>
        </button>

        <?php if ($filterItemId > 0 || $filterCategory !== '' || $filterType !== '' || $filterStartDate !== '' || $filterEndDate !== ''): ?>
            <a href="<?php echo e(url('admin/reports.php')); ?>" class="btn btn-light">Reset</a>
        <?php endif; ?>
    </form>

    <button type="button" class="btn btn-success" onclick="window.print()">
        <?php echo render_icon('print', 'icon'); ?>
        <span>Print Report</span>
    </button>
</div>

<!-- Report Summary Cards (Section 13) -->
<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Stock IN</div>
            <div class="stat-value" style="color: var(--success);"><?php echo number_format($totalIn); ?></div>
        </div>
        <div class="stat-icon-wrapper stat-icon-success">
            <?php echo render_icon('arrow-down', 'icon'); ?>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Total Stock OUT</div>
            <div class="stat-value" style="color: var(--danger);"><?php echo number_format($totalOut); ?></div>
        </div>
        <div class="stat-icon-wrapper stat-icon-danger">
            <?php echo render_icon('arrow-up', 'icon'); ?>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Net Movement (IN - OUT)</div>
            <div class="stat-value" style="<?php echo $netMovement >= 0 ? 'color: var(--primary);' : 'color: var(--danger);'; ?>">
                <?php echo ($netMovement > 0 ? '+' : '') . number_format($netMovement); ?>
            </div>
        </div>
        <div class="stat-icon-wrapper stat-icon-primary">
            <?php echo render_icon('reports', 'icon'); ?>
        </div>
    </div>
</div>

<!-- Detailed Movement Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <?php echo render_icon('reports', 'icon'); ?>
            <span>Movement Audit Trail (<?php echo count($records); ?> transactions found)</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($records)): ?>
            <div style="padding: 40px; text-align: center; color: var(--text-secondary);">
                <strong>No inventory movements match the specified filter criteria.</strong>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th style="color: var(--success);">Stock IN</th>
                            <th style="color: var(--danger);">Stock OUT</th>
                            <th>Reference / Note</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['movement_date'])); ?></td>
                                <td><code><?php echo e($row['item_code']); ?></code></td>
                                <td>
                                    <div class="item-title-cell">
                                        <div class="item-badge-icon" style="width: 28px; height: 28px;">
                                            <?php echo render_icon($row['icon'] ?? 'box', 'icon'); ?>
                                        </div>
                                        <span><?php echo e($row['item_name']); ?></span>
                                    </div>
                                </td>
                                <td><span class="badge badge-secondary"><?php echo e($row['category']); ?></span></td>
                                <td>
                                    <?php if ($row['movement_type'] === 'IN'): ?>
                                        <strong style="color: var(--success);">+<?php echo number_format((int)$row['quantity']); ?></strong> 
                                        <span style="font-size: 11px; color: var(--text-muted);"><?php echo e($row['unit']); ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['movement_type'] === 'OUT'): ?>
                                        <strong style="color: var(--danger);">-<?php echo number_format((int)$row['quantity']); ?></strong> 
                                        <span style="font-size: 11px; color: var(--text-muted);"><?php echo e($row['unit']); ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($row['reference_note'] ?? '—'); ?></td>
                                <td><span class="badge badge-secondary"><?php echo e($row['operator_name']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
