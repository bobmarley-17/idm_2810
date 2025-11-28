<?php
/**
 * bootstrap.php - The single source of truth for session, CSRF, DB, and app initialization.
 * This file should be the FIRST file included on every user-facing PHP script.
 */

// 1. Centralized Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Error Reporting (Good for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 3. Timezone
date_default_timezone_set('Asia/Kolkata');

// 4. Centralized CSRF Token Management
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        die('Could not generate a secure token.');
    }
}

// 5. Centralized CSRF Functions
function csrf_input(): string {
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function validate_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// 6. Include other essential files and create global objects
require_once 'config/database.php';
require_once 'vendor/autoload.php';
require_once 'lib/AuditLogger.php';

// 7. Create the database connection object
try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set up AuditLogger with database
    AuditLogger::setPdo($db);
    
    // Set up AuditLogger with file path (NEW!)
    AuditLogger::setLogFile(__DIR__ . '/logs/audit.log');

} catch (PDOException $e) {
    error_log("FATAL: Database connection failed: " . $e->getMessage());
    die("A critical database error occurred. Please contact the administrator.");
}

?>
