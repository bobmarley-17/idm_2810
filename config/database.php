<?php
// Database Configuration
$dbHost = 'localhost';
//$dbName = 'idm_tool';
$dbName = 'idmdb1209';
$dbUser = 'idm_user';
$dbPass = 'test123'; // Change to your actual password

/**
 * Error Reporting Configuration
 * 
 * Development Settings:
 * - Show all errors
 * - Display errors on screen
 * 
 * Production Settings (comment out the above and uncomment below):
 * // error_reporting(0);
 * // ini_set('display_errors', 0);
  ini_set('display_errors', 1);
 * // ini_set('log_errors', 1);
 * // ini_set('error_log', __DIR__.'/../logs/php_errors.log');
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Session Configuration
 * - Starts session only if not already active
 * - Secure session settings
 */
if (session_status() === PHP_SESSION_NONE) {
    // Basic session configuration
    session_name('IDM_SESSION');
    session_set_cookie_params([
        'lifetime' => 86400, // 1 day
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure' => isset($_SERVER['HTTPS']), // Enable if using HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
}

// Timezone Configuration
date_default_timezone_set('UTC');

// PDO Connection with Error Handling
try {
    $db = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]
    );
    
    // Set session variables if this is a new session
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
} catch (PDOException $e) {
    // Log error securely
    error_log('Database connection failed: ' . $e->getMessage());
    
    // Display user-friendly message
    if (ini_get('display_errors')) {
        die('<h2>Database Connection Error</h2><p>Please try again later.</p>');
    } else {
        die('Service temporarily unavailable');
    }
}

/**
 * Utility Functions
 */

/**
 * Generates CSRF token input field
 */
function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['csrf_token']).'">';
}

/**
 * Validates CSRF token
 */
function validate_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Register shutdown function for error handling
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Critical error: " . var_export($error, true));
        if (ini_get('display_errors')) {
            echo '<h2>Application Error</h2><p>A critical error occurred.</p>';
        }
    }
});
