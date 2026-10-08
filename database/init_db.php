<?php
declare(strict_types=1);

$host = '127.0.0.1';
$port = '3307';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("schema.sql not found at {$schemaFile}");
    }

    $sql = file_get_contents($schemaFile);
    // Execute multiple statements
    $pdo->exec($sql);

    echo "DATABASE_SETUP_SUCCESSFUL: Database 'warehouse_management' created and seeded successfully.\n";
} catch (Throwable $e) {
    echo "DATABASE_SETUP_FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
