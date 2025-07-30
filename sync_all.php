<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';
include 'templates/header.php';

// Get all sources
$sources = $db->query("SELECT id, name FROM account_sources ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Sync All Sources</h2>";
echo "<ul>";

foreach ($sources as $src) {
    echo "<li>Syncing source: <b>" . htmlspecialchars($src['name']) . "</b>... ";
    // Call the Python script, passing the source ID
    $cmd = escapeshellcmd("python3 run_sync.py --source " . intval($src['id']));
    $output = shell_exec($cmd . " 2>&1");
    if (strpos($output, 'completed') !== false || strpos($output, 'Success') !== false) {
        echo "<span style='color:green'>Success</span>";
    } else {
        echo "<span style='color:red'>Failed</span><br><pre>" . htmlspecialchars($output) . "</pre>";
    }
    echo "</li>";
}

echo "</ul>";
echo "<p>All sources processed.</p>";
?>