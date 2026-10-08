<?php
declare(strict_types=1);

/**
 * Warehouse Management System - Configuration File
 * Fully compatible with XAMPP, LAMP, and cPanel Shared Hosting
 */

// Application Information
define('APP_NAME', 'Warehouse Management System');
define('APP_VERSION', '1.0.0');

// Detect Protocol & Host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Universal Base Path Resolution (Works in root domain, subdomains, subdirectories, and cPanel)
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';

$basePath = '';
if ($docRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
    $relative = substr($projectRoot, strlen($docRoot));
    $basePath = '/' . trim(str_replace('\\', '/', $relative), '/');
} else {
    // Dynamic fallback based on script location
    $scriptFilename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName     = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptFilename !== '' && $scriptName !== '' && str_starts_with($scriptFilename, $projectRoot)) {
        $relativeScript = substr($scriptFilename, strlen($projectRoot));
        $scriptDirDepth = substr_count(trim($relativeScript, '/'), '/');
        $urlDir = dirname($scriptName);
        for ($i = 0; $i < $scriptDirDepth; $i++) {
            $urlDir = dirname($urlDir);
        }
        $basePath = '/' . trim(str_replace('\\', '/', $urlDir), '/');
    }
}

if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}

// Global Application URLs
define('BASE_PATH', rtrim($basePath, '/'));
define('APP_URL', rtrim($protocol . $host . BASE_PATH, '/'));

// ==============================================================
// Database Configuration
// ==============================================================
// For cPanel hosting:
// 1. Create a MySQL database and user via cPanel "MySQL Databases" tool
// 2. Grant ALL PRIVILEGES to the user for the database
// 3. Update DB_NAME, DB_USER, DB_PASS below with your cPanel values
// ==============================================================
define('DB_HOST', '127.0.0.1');     // Usually 'localhost' or '127.0.0.1' on cPanel
define('DB_PORT', 3307);            // Auto-detects 3306 (cPanel standard) and 3307 (XAMPP)
define('DB_NAME', 'warehouse_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session Timeout in Seconds (2 hours)
define('SESSION_LIFETIME', 7200);
