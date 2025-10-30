<?php
// /var/www/html/idm_2810/test_session2.php

echo "<h1>Session Test - Page 2</h1>";

// Start the session to try and retrieve the data
session_start();

// Check if the variable exists
if (isset($_SESSION['test_message'])) {
    echo "<p style='color: green; font-weight: bold;'>SUCCESS!</p>";
    echo "<p>I received the message: '" . htmlspecialchars($_SESSION['test_message']) . "'</p>";
    echo "<p>This means your server's session handling is working correctly.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>FAILURE!</p>";
    echo "<p>I could not find the message from Page 1.</p>";
    echo "<p>This confirms that your server is not saving session data between pages. This is the cause of your 'Invalid token' error.</p>";
}

// Clean up the session
session_destroy();
?>
