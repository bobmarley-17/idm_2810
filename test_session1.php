<?php
// /var/www/html/idm_2810/test_session1.php

echo "<h1>Session Test - Page 1</h1>";

// Start a session
session_start();

// Set a variable in the session
$_SESSION['test_message'] = "Hello from Page 1!";

echo "<p>Session started. I've stored a message.</p>";
echo "<p><a href='test_session2.php'>Click here to go to Page 2</a></p>";
?>
