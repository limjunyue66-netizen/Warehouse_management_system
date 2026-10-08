<?php
declare(strict_types=1);

/**
 * Warehouse Management System - Common Header
 */

require_once __DIR__ . '/init.php';

$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Professional, high-reliability Warehouse Management System">
    <title><?php echo e($pageTitle); ?> - <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo e(url('assets/css/style.css')); ?>">
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-wrapper">
    <?php require_once __DIR__ . '/nav.php'; ?>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle Navigation">
                    <?php echo render_icon('menu', 'icon'); ?>
                </button>
                <div class="page-title-wrap">
                    <h1><?php echo e($pageTitle); ?></h1>
                    <p><?php echo e($pageSubtitle ?? 'Warehouse Operations & Inventory Management'); ?></p>
                </div>
            </div>

            <div class="topbar-right">
                <div class="user-pill" style="display: flex; align-items: center; gap: 10px;">
                    <span class="user-role-badge role-<?php echo e($user['role'] ?? 'user'); ?>">
                        <?php echo e(strtoupper($user['role'] ?? 'USER')); ?>
                    </span>
                    <a href="<?php echo e(url('logout.php')); ?>" class="btn btn-outline btn-sm" title="Sign Out">
                        <?php echo render_icon('logout', 'icon'); ?>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="content-container">
            <?php echo render_flash(); ?>
