<?php
// logout.php - CORRECTED LOGGING

require_once 'bootstrap.php';
require_once 'lib/LogHelper.php'; // Use the Helper for File + DB logging

// Get user info before destroying session
$username = $_SESSION['username'] ?? 'unknown';
$userId = $_SESSION['user_id'] ?? null;

if (isset($_SESSION['username'])) {
    // LOG: Logout event (Writes to audit.log and Database)
    LogHelper::logAuth('User logout successful', $username, true, [
        'user_id' => $userId,
        'action' => 'logout'
    ]);
}

// 1. Unset all session variables
$_SESSION = [];

// 2. Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session
session_destroy();

// 4. Redirect
header('Location: login.php');
exit();
?>
