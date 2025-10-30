<?php
// auth/sso_trigger.php
require_once '../bootstrap.php';
require_once '../config/saml/settings.php'; // Go up one level
require_once '../vendor/autoload.php';      // Go up one level

use OneLogin\Saml2\Auth;

try {
    $auth = new Auth($settingsInfo);
    $auth->login(); // Redirects to the IdP
} catch (Exception $e) {
    error_log('SSO Trigger Error: ' . $e->getMessage());
    die('An error occurred while trying to initiate SSO. Please contact support.');
}
