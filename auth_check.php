<?php
// auth_check.php - Session timeout and authentication check

// Session timeout in seconds (10 minutes = 600, 5 minutes = 300)
define('SESSION_TIMEOUT', 600);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check session timeout
if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    
    if ($inactive_time > SESSION_TIMEOUT) {
        // Destroy session
        $_SESSION = array();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
        
        // Redirect to login with expired message
        header('Location: login.php?expired=1');
        exit();
    }
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();
