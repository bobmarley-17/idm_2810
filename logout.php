<?php
// logout.php - CORRECTED FOR PASS-THROUGH AUTHENTICATION

// Load bootstrap to start the session and access session variables
require_once 'bootstrap.php';
// Load the logger
require_once 'vendor/autoload.php'; // Make sure this is included for Monolog
require_once 'lib/AuditLogger.php';

// Get the username for logging BEFORE we destroy the session
$username = $_SESSION['username'] ?? 'unknown_user';

// Log the logout event
AuditLogger::getLogger('auth')->info('User logout successful.', [
    'user' => $username,
    'details' => ''
]);

// 1. Unset all of the session variables.
$_SESSION = [];

// 2. If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finally, destroy the session.
session_destroy();

// 4. Redirect the user back to your application's login page.
header('Location: login.php');
exit();
