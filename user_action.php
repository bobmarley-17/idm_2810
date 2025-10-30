<?php
// user_action.php

// FIX: Standardize the file startup for security and session handling.
require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/AuditLogger.php';

// FIX: REMOVED the redundant database connection.
// $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // FIX: Add CSRF protection.
    if (!validate_csrf()) {
        die('Invalid security token.');
    }

    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';

    // --- Approve Deletion ---
    if (isset($_POST['approve_deletion'])) {
        $ids = [];
        if (isset($_POST['account_id'])) {
            $ids[] = intval($_POST['account_id']);
        }
        if (isset($_POST['account_ids']) && is_array($_POST['account_ids'])) {
            foreach ($_POST['account_ids'] as $aid) {
                $ids[] = intval($aid);
            }
        }
        
        if (!empty($ids)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            
            // --- THIS IS THE KEY CHANGE ---
            // Instead of DELETE, we now UPDATE the status and set the deletion_date.
            $stmt = $db->prepare("UPDATE user_accounts SET status = 'deleted', deletion_date = NOW() WHERE id IN ($in)");
            $stmt->execute($ids);
            
            // FIX: Add detailed audit logging for each deleted account.
            foreach ($ids as $accountId) {
                 AuditLogger::getLogger('user_management')->warning('User account approved for deletion (soft deleted).', [
                    'user' => $_SESSION['username'], 
                    'details' => json_encode(['user_account_id' => $accountId])
                ]);
            }

            // Your logic to update defunct_users can remain if needed, but a soft delete is often enough.
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    // --- Restore Account ---
    if (isset($_POST['restore_account'])) {
        $ids = [];
        // (Your logic to collect IDs is fine)
        if (!empty($ids)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            // FIX: When restoring, we must clear the deletion date and reset the status.
            $stmt = $db->prepare("UPDATE user_accounts SET pending_deletion = 0, status = 'active', deletion_date = NULL WHERE id IN ($in)");
            $stmt->execute($ids);

            // FIX: Add audit log for restoration.
            foreach ($ids as $accountId) {
                 AuditLogger::getLogger('user_management')->info('User account restored from pending deletion.', [
                    'user' => $_SESSION['username'], 
                    'details' => json_encode(['user_account_id' => $accountId])
                ]);
            }
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Fallback redirect if the script is accessed incorrectly.
header('Location: index.php');
exit;
