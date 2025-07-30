<?php
require_once 'config/database.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id = intval($_POST['account_id']);
    $role_account_id = intval($_POST['role_account_id']);
    if ($account_id > 0 && $role_account_id > 0) {
        $stmt = $db->prepare("UPDATE uncorrelated_accounts SET role_account_id = ? WHERE id = ?");
        $stmt->execute([$role_account_id, $account_id]);
        header('Location: index.php');
        exit;
    }
}
header('Location: index.php');
exit;
