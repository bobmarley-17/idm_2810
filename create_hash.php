<?php
// Define the password you want to use.
$passwordToSet = 'T3st@123'; 

// Generate a secure hash from that password.
$hash = password_hash($passwordToSet, PASSWORD_DEFAULT);

echo "<h3>Password Hash Generated</h3>";
echo "<p>For password: " . htmlspecialchars($passwordToSet) . "</p>";
echo "<p>Copy this hash. You will use it in the next SQL command:</p>";
echo "<pre style='background:#eee; padding:10px; border:1px solid #999;'>" . htmlspecialchars($hash) . "</pre>";
?>
