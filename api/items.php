<?php
declare(strict_types=1);

/**
 * Warehouse Management System - Items API Endpoint
 * Requires Active Session Authentication
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/init.php';

// Enforce authentication for API access
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Authentication required.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    $db = get_db_connection();

    $search   = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');

    $sql = "SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level, icon, created_at, updated_at 
            FROM items 
            WHERE 1=1";
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

    // Map formatted data
    $data = array_map(function ($item) {
        $qty = (int)$item['quantity'];
        $reorder = (int)$item['reorder_level'];
        return [
            'id'            => (int)$item['id'],
            'item_code'     => $item['item_code'],
            'item_name'     => $item['item_name'],
            'category'      => $item['category'],
            'location'      => $item['location'],
            'quantity'      => $qty,
            'unit'          => $item['unit'],
            'reorder_level' => $reorder,
            'icon'          => $item['icon'] ?? 'box',
            'is_low_stock'  => $qty <= $reorder,
            'created_at'    => $item['created_at'],
            'updated_at'    => $item['updated_at'],
        ];
    }, $items);

    echo json_encode([
        'success' => true,
        'count'   => count($data),
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error processing inventory data.'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
