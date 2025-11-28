<?php
require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/LogHelper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id = intval($_POST['account_id'] ?? 0);
    $role_account_id = intval($_POST['role_account_id'] ?? 0);
    
    if ($account_id > 0 && $role_account_id > 0) {
        try {
            // Get account details for logging
            $accountStmt = $db->prepare("
                SELECT ua.*, s.name as source_name 
                FROM uncorrelated_accounts ua 
                LEFT JOIN account_sources s ON ua.source_id = s.id 
                WHERE ua.id = ?
            ");
            $accountStmt->execute([$account_id]);
            $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get role account details for logging
            $roleStmt = $db->prepare("SELECT name, description FROM role_accounts WHERE id = ?");
            $roleStmt->execute([$role_account_id]);
            $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
            
            // Update the uncorrelated account
            $stmt = $db->prepare("UPDATE uncorrelated_accounts SET role_account_id = ? WHERE id = ?");
            $stmt->execute([$role_account_id, $account_id]);
            
            // LOG: Role assignment
            LogHelper::logConfig('Role account assigned', [
                'uncorrelated_account_id' => $account_id,
                'account_id' => $account['account_id'] ?? 'Unknown',
                'account_email' => $account['email'] ?? 'Unknown',
                'account_username' => $account['username'] ?? 'Unknown',
                'source_id' => $account['source_id'] ?? null,
                'source_name' => $account['source_name'] ?? 'Unknown',
                'role_account_id' => $role_account_id,
                'role_name' => $role['name'] ?? 'Unknown',
                'assigned_by' => $_SESSION['username'] ?? 'unknown'
            ]);
            
            LogHelper::logDatabaseChange('uncorrelated_accounts', 'UPDATE', $account_id, [
                'field' => 'role_account_id',
                'old_value' => $account['role_account_id'] ?? null,
                'new_value' => $role_account_id,
                'role_name' => $role['name'] ?? 'Unknown'
            ]);
            
            $_SESSION['message'] = "Account assigned to role: " . htmlspecialchars($role['name'] ?? 'Unknown');
            $_SESSION['message_type'] = "success";
            
        } catch (Exception $e) {
            // LOG: Error
            LogHelper::logError('Role assignment failed', [
                'account_id' => $account_id,
                'role_account_id' => $role_account_id,
                'error' => $e->getMessage(),
                'attempted_by' => $_SESSION['username'] ?? 'unknown'
            ]);
            
            $_SESSION['message'] = "Failed to assign role: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Invalid account or role selection.";
        $_SESSION['message_type'] = "warning";
    }
}

header('Location: index.php');
exit;
