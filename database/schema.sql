-- ==============================================================
-- Warehouse Management System Database Schema
-- Database Name: warehouse_management
-- Supported on MySQL 8+ / MariaDB 10.4+
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `warehouse_management` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `warehouse_management`;

-- -------------------------------------------------------------
-- Table: users
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: items
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_code` VARCHAR(50) NOT NULL UNIQUE,
    `item_name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `location` VARCHAR(100) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `unit` VARCHAR(30) NOT NULL DEFAULT 'pcs',
    `reorder_level` INT NOT NULL DEFAULT 10,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'box',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_item_code` (`item_code`),
    INDEX `idx_category` (`category`),
    INDEX `idx_location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table: stock_movements
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `item_id` INT NOT NULL,
    `movement_type` ENUM('IN', 'OUT') NOT NULL,
    `quantity` INT NOT NULL,
    `movement_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reference_note` VARCHAR(255) NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_movements_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    INDEX `idx_movement_date` (`movement_date`),
    INDEX `idx_item_movement` (`item_id`, `movement_date`),
    INDEX `idx_movement_type` (`movement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Seed Data: Initial Users
-- Password for admin: admin123
-- Password for user:  user123
-- -------------------------------------------------------------
INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `is_active`) VALUES
(1, 'System Administrator', 'admin', '$2y$10$NNU0ZaQWp4HDrspW9.0eVO1K3rvBMWNpvY6yihPIzACUEQkbKzFCa', 'admin', 1),
(2, 'Warehouse Operator', 'user', '$2y$10$Fyq/LNfCIZdiTt.up7K29uc3e7fed3EhpoCDT.lxB7MmVPaAFtxUy', 'user', 1)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- -------------------------------------------------------------
-- Seed Data: Initial Inventory Items (Clean 5 Essential Items)
-- -------------------------------------------------------------
INSERT INTO `items` (`id`, `item_code`, `item_name`, `category`, `location`, `quantity`, `unit`, `reorder_level`, `icon`) VALUES
(1, 'ITM-001', 'Barcode Scanner', 'Electronics', 'Shelf A-1', 45, 'pcs', 15, 'scanner'),
(2, 'ITM-002', 'Laptop Battery',  'Electronics', 'Shelf A-2', 8,  'pcs', 20, 'battery'),
(3, 'ITM-003', 'Tool Wrench',    'Hardware',    'Rack B-1',  25, 'pcs', 10, 'tool'),
(4, 'ITM-004', 'Cardboard Box',  'Packaging',   'Zone C-1',  150,'pcs', 40, 'package'),
(5, 'ITM-005', 'Safety Helmet',  'Safety',      'Rack D-2',  4,  'pcs', 10, 'shield')
ON DUPLICATE KEY UPDATE `id` = `id`;

-- -------------------------------------------------------------
-- Seed Data: Initial Stock Movements (Audit Trail for 5 Items)
-- -------------------------------------------------------------
INSERT INTO `stock_movements` (`id`, `item_id`, `movement_type`, `quantity`, `movement_date`, `reference_note`, `created_by`) VALUES
(1, 1, 'IN', 50, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Initial Purchase Order PO-101', 1),
(2, 1, 'OUT', 5, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Dispatched to Dispatch Bay', 1),
(3, 2, 'IN', 20, DATE_SUB(NOW(), INTERVAL 4 DAY), 'Emergency Stock Shipment', 1),
(4, 2, 'OUT', 12, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Issued to IT Department (Low stock)', 1),
(5, 3, 'IN', 30, DATE_SUB(NOW(), INTERVAL 6 DAY), 'Supplier Delivery GRN-330', 1),
(6, 3, 'OUT', 5, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Issued to Workshop Station', 1),
(7, 4, 'IN', 200, DATE_SUB(NOW(), INTERVAL 7 DAY), 'Bulk Packaging Restock', 1),
(8, 4, 'OUT', 50, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Order Fulfillment Batch', 1),
(9, 5, 'IN', 15, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Safety Equipment Intake', 1),
(10, 5, 'OUT', 11, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Staff Safety Kit Distribution (Low stock)', 1)
ON DUPLICATE KEY UPDATE `id` = `id`;
