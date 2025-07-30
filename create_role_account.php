<?php
require_once 'config/database.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['role_name'] ?? '');
    $desc = trim($_POST['role_desc'] ?? '');
    if ($name !== '') {
        $stmt = $db->prepare("INSERT INTO role_accounts (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $desc]);
    }
}
header('Location: index.php');
exit;
