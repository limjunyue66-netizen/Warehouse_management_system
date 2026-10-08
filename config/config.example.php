<?php
declare(strict_types=1);

/**
 * Warehouse Management System - Configuration Template (Example)
 * Rename this file to config.php and update your credentials accordingly.
 */

define('APP_NAME', 'Warehouse Management System');
define('APP_VERSION', '1.0.0');

// Detect Protocol & Host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Universal Base Path Resolution (Compatible with Root Domain, Subdirectory, and cPanel)
$projectRoot = str_replace('\\', '/', dirname(__DIR__));
$docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';

$basePath = '';
if ($docRoot !== '' && str_starts_with($projectRoot, $docRoot)) {
    $relative = substr($projectRoot, strlen($docRoot));
    $basePath = '/' . trim(str_replace('\\', '/', $relative), '/');
} else {
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

define('BASE_PATH', rtrim($basePath, '/'));
define('APP_URL', rtrim($protocol . $host . BASE_PATH, '/'));

// ==============================================================
// Database Credentials Template (Safe for Version Control)
// ==============================================================
define('DB_HOST', '127.0.0.1');                 // e.g. 'localhost' or '127.0.0.1'
define('DB_PORT', 3306);                        // Default MySQL port is 3306
define('DB_NAME', 'your_database_name');        // e.g. 'warehouse_management'
define('DB_USER', 'your_database_username');    // e.g. 'root'
define('DB_PASS', 'your_database_password');    // e.g. '' or your secure password

define('SESSION_LIFETIME', 7200);
