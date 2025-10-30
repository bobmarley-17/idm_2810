<?php
// auth/login.php
require_once '../bootstrap.php'; // Go up one level to find bootstrap.php

// If a user is already logged in, redirect them to the main application.
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php'); // Go up one level to find index.php
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>IDM System Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f0f2f5; }
        .login-box { text-align: center; background: white; padding: 3rem; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>IDM System</h2>
        <p class="lead text-muted">Please log in with your company credentials.</p>
        <hr>
        <a href="sso_trigger.php" class="btn btn-primary btn-lg mt-3">
            <i class="fas fa-sign-in-alt"></i> Login with SSO
        </a>
    </div>
</body>
</html>
