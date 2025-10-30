<?php
// auth/acs.php
require_once '../bootstrap.php';
require_once '../config/database.php';
require_once '../lib/AuditLogger.php';
require_once '../config/saml/settings.php';
require_once '../vendor/autoload.php';

use OneLogin\Saml2\Auth;

$auth = new Auth($settingsInfo);
$auth->processResponse();

if ($auth->getErrors()) {
    error_log('SAML ACS Error: ' . $auth->getLastErrorReason());
    die("SSO login failed. Error: " . $auth->getLastErrorReason());
}

if (!$auth->isAuthenticated()) {
    die("SSO authentication failed. Not authenticated.");
}

// User is authenticated by the IdP. Now, get their attributes.
// TODO: Use the real attribute names from your IT team.
$email = $auth->getAttribute('email')[0] ?? null;
$username = $auth->getAttribute('username')[0] ?? explode('@', $email)[0]; // Fallback to part of email

if (empty($email)) {
    die("Required 'email' attribute was not provided by the SSO provider.");
}

// Check if this user is authorized to use the application.
$stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE email = ? AND status = 'active'");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && $user['password_hash'] !== null) {
    // USER EXISTS and IS AUTHORIZED
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['sso_session'] = true;

    AuditLogger::getLogger('auth')->info('SSO login successful.', ['user' => $user['username'], 'details' => json_encode(['email' => $email])]);
    
    // Redirect to the main application page
    header('Location: ../index.php'); // Go up one level
    exit();
} else {
    // User either doesn't exist or is not authorized (password_hash is NULL)
    AuditLogger::getLogger('auth')->warning('SSO login blocked for unauthorized or non-existent user.', ['user' => $email]);
    die("Access Denied. You are not authorized to use this application, or your account does not exist in this system.");
}
