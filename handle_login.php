<?php
// handle_login.php - THE FINAL, WORKING VERSION

require_once 'bootstrap.php';
require_once 'config/database.php';
require_once 'lib/AuditLogger.php';

// --- Stage 1: Security and Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf()) {
    $_SESSION['login_error'] = 'Invalid request or session expired.';
    header('Location: login.php');
    exit();
}

$username = $_POST['username'];
$password = $_POST['password'];

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Username and password are required.';
    header('Location: login.php');
    exit();
}

// --- Stage 2: Call the External Authentication API ---
$authUrl = "https://auth.qa.int.untd.com/bin/sso?async=true&action=parms&type=login&username=" . urlencode($username) . "&password=" . urlencode($password);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $authUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

// CRITICAL FIX #1: Bypass the expired SSL certificate for testing.
// WARNING: This must be removed before production.
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$responseXml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($responseXml === false || $httpCode !== 200) {
    error_log("Auth API call failed. HTTP Code: " . $httpCode);
    $_SESSION['login_error'] = 'The authentication service is currently unavailable. Please try again later.';
    header('Location: login.php');
    exit();
}

// --- Stage 3: Parse the XML Response ---
try {
    // CRITICAL FIX #2: Repair the malformed XML before parsing.
    $fixedXmlString = preg_replace('/&(?![a-zA-Z]{2,6};|#[0-9]{2,4};)/', '&amp;', $responseXml);

    $xml = @simplexml_load_string($fixedXmlString);
    if ($xml === false) {
        throw new Exception("Failed to parse XML response from auth server.");
    }
    
    // CRITICAL FIX #3: Correctly access the child nodes of the XML object.
    $result = (string)$xml->result[0];
    $message = (string)$xml->message[0];

} catch (Exception $e) {
    error_log($e->getMessage() . " Raw response: " . $responseXml);
    $_SESSION['login_error'] = 'An unexpected error occurred while parsing the auth response.';
    header('Location: login.php');
    exit();
}

// --- Stage 4: Authentication Check ---
if ($result !== 'true') {
    $_SESSION['login_error'] = htmlspecialchars($message);
    AuditLogger::getLogger('auth')->warning('Authentication failed via external API.', ['user' => $username, 'details' => json_encode(['api_message' => $message])]);
    header('Location: login.php');
    exit();
}

// --- Stage 5: Local Database Authorization ---
$stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = ? AND status = 'active'");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && $user['password_hash'] !== null) {
    // SUCCESS!
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

    AuditLogger::getLogger('auth')->info('Login successful (External Auth + Local Auth).', ['user' => $user['username']]);
    header('Location: index.php');
    exit();
} else {
    // User passed SSO but is not an authorized admin in our system.
    $_SESSION['login_error'] = 'You have been authenticated, but you are not authorized to access this application.';
    AuditLogger::getLogger('auth')->warning('Login blocked for unauthorized user.', ['user' => $username, 'details' => 'User passed external auth but is not authorized locally.']);
    header('Location: login.php');
    exit();
}
