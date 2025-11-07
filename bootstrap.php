<?php
/**
 * bootstrap.php - The single source of truth for session, CSRF, DB, and app initialization.
 * This file should be the FIRST file included on every user-facing PHP script.
 */

// 1. Centralized Session Management
// Always check status before starting. This is the correct, safe way.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Error Reporting (Good for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 3. Timezone
date_default_timezone_set('UTC');


// 4. Centralized CSRF Token Management
// Ensure a valid CSRF token exists for the current session.
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        die('Could not generate a secure token.');
    }
}

// 5. Centralized CSRF Functions
/**
 * Generates a hidden input field with the CSRF token.
 * @return string
 */
function csrf_input(): string {
    // Make sure the session token exists before trying to use it.
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validates the submitted CSRF token against the one in the session.
 * @return bool
 */
function validate_csrf(): bool {
    // Check that both POST value and SESSION value are set before comparing
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}


// 6. Include other essential files and create global objects
require_once 'config/database.php';
require_once 'vendor/autoload.php'; // Composer autoloader for libraries
require_once 'lib/AuditLogger.php'; // Your custom logger

// 7. Create the database connection object
try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If the database connection fails, log it and stop.
    error_log("FATAL: Database connection failed: " . $e->getMessage());
    die("A critical database error occurred. Please contact the administrator.");
}

?>
