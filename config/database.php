<?php
// config/database.php - Database connection ONLY.

$dbHost = 'localhost';
$dbName = 'idmdb2810';
$dbUser = 'idm_user';
$dbPass = 'test123';

try {
    $db = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('<h2>Database Connection Error</h2><p>Unable to connect to the service.</p>');
}
