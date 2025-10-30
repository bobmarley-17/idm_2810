<?php
// auth/logout.php
require_once '../bootstrap.php';
require_once '../lib/AuditLogger.php';
require_once '../config/saml/settings.php';
require_once '../vendor/autoload.php';

use OneLogin\Saml2\Auth;

$username = $_SESSION['username'] ?? 'unknown';
AuditLogger::getLogger('auth')->info('User initiated SSO logout.', ['user' => $username]);

// Also destroy the local session
session_destroy();

$auth = new Auth($settingsInfo);

// The URL to return to after the IdP has logged the user out.
$returnTo = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/idm_2810/auth/login.php';

// This redirects to the IdP for global logout.
$auth->logout($returnTo);
