<?php
// bootstrap.php - The single source of truth for session and app initialization.

// 1. Error Reporting (Good for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Timezone
date_default_timezone_set('UTC');

// 3. Centralized Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_name('IDM_SESSION');
    session_set_cookie_params([
        'lifetime' => 86400, // 1 day
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// 4. Centralized CSRF Token Management
// Ensure a valid CSRF token exists for the current session.
if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) !== 64) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 5. Centralized CSRF Functions (moved from config/database.php)
function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['csrf_token']).'">';
}

function validate_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
