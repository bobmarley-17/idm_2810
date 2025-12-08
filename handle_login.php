<?php
// handle_login.php - External Auth Only with Whitelist

require_once 'bootstrap.php';
require_once 'config/database.php';
require_once 'lib/LogHelper.php';

// --- Stage 1: Security and Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf()) {
    $_SESSION['login_error'] = 'Invalid request or session expired.';
    
    LogHelper::logSecurity('Login attempt with invalid request/CSRF', 'warning', [
        'reason' => 'Invalid method or CSRF token'
    ]);
    
    header('Location: login.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Username and password are required.';
    
    LogHelper::logAuth('Login attempt with empty credentials', $username ?: 'unknown', false, [
        'reason' => 'Empty username or password'
    ]);
    
    header('Location: login.php');
    exit();
}

// --- Stage 2: Call the External Authentication API ---
$authUrl = "https://auth.qa.int.untd.com/bin/sso?async=true&action=parms&type=login&username=" . urlencode($username) . "&password=" . urlencode($password);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $authUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

// WARNING: Bypass SSL for internal testing only
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$responseXml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($responseXml === false || $httpCode !== 200) {
    error_log("Auth API call failed. HTTP Code: " . $httpCode . " Error: " . $curlError);
    $_SESSION['login_error'] = 'The authentication service is currently unavailable. Please try again later.';
    
    LogHelper::logError('External auth service unavailable', [
        'username' => $username,
        'http_code' => $httpCode,
        'curl_error' => $curlError
    ]);
    
    header('Location: login.php');
    exit();
}

// --- Stage 3: Parse the XML Response ---
try {
    $fixedXmlString = preg_replace('/&(?![a-zA-Z]{2,6};|#[0-9]{2,4};)/', '&amp;', $responseXml);

    $xml = @simplexml_load_string($fixedXmlString);
    if ($xml === false) {
        throw new Exception("Failed to parse XML response from auth server.");
    }

    $result = (string)$xml->result[0];
    $message = (string)$xml->message[0];

} catch (Exception $e) {
    error_log($e->getMessage() . " Raw response: " . $responseXml);
    $_SESSION['login_error'] = 'An unexpected error occurred while parsing the auth response.';
    
    LogHelper::logError('Auth response parsing failed', [
        'username' => $username,
        'error' => $e->getMessage()
    ]);
    
    header('Location: login.php');
    exit();
}

// --- Stage 4: Authentication Check ---
if ($result !== 'true') {
    $_SESSION['login_error'] = htmlspecialchars($message);
    
    LogHelper::logAuth('Login failed - invalid credentials', $username, false, [
        'api_message' => $message,
        'auth_result' => $result
    ]);
    
    header('Location: login.php');
    exit();
}

// --- Stage 5: Whitelist Authorization ---
$authorizedUsers = [
    'akadium',
    'imohammed',
    'sgurubilli',
    'krishnamurala',
];

if (in_array($username, $authorizedUsers, true)) {
    // SUCCESS!
    session_regenerate_id(true);
    
    // FIX: Use 0 for external users to prevent SQL Integer errors
    // (The database column user_id is INT, strings or huge numbers break it)
    $_SESSION['user_id'] = 0; 
    $_SESSION['username'] = $username;
    $_SESSION['last_activity'] = time();

    // LOG: Login successful
    LogHelper::logAuth('Login successful', $username, true, [
        'auth_method' => 'external_sso',
        'whitelist_check' => 'passed',
        'user_id' => 0
    ]);

    header('Location: index.php');
    exit();
    
} else {
    // User authenticated but not authorized
    $_SESSION['login_error'] = 'You have been authenticated, but you are not authorized to access this application.';
    
    LogHelper::logSecurity('Unauthorized user blocked', 'warning', [
        'username' => $username,
        'reason' => 'User passed external auth but not in whitelist',
        'whitelist_check' => 'failed'
    ]);
    
    header('Location: login.php');
    exit();
}
?>
