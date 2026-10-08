<?php
declare(strict_types=1);

/**
 * Warehouse Management System - Database Connection Manager
 */

require_once __DIR__ . '/config.php';

function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $portsToTry = [DB_PORT, 3306, 3307];
    $portsToTry = array_unique($portsToTry);

    $lastException = null;

    foreach ($portsToTry as $port) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                $port,
                DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    // Log the actual error for admins/logs without revealing raw credentials to end-users
    error_log("Database Connection Error: " . ($lastException ? $lastException->getMessage() : 'Unknown error'));

    // Output friendly error page
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Error - <?php echo htmlspecialchars(APP_NAME); ?></title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
            .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 32px; max-width: 520px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.3); text-align: center; }
            .icon { font-size: 48px; margin-bottom: 16px; }
            h1 { font-size: 20px; margin-bottom: 12px; color: #f87171; }
            p { font-size: 14px; line-height: 1.6; color: #94a3b8; margin-bottom: 24px; }
            .btn { display: inline-block; padding: 10px 20px; background: #3b82f6; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">⚠️</div>
            <h1>Unable to Connect to Warehouse Database</h1>
            <p>The system could not establish a connection to MySQL. Please verify that MySQL service is running in XAMPP or your database credentials in <code>config/config.php</code> are correct.</p>
            <a href="javascript:location.reload()" class="btn">Retry Connection</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
