<?php
// user_action.php - FINAL CORRECTED VERSION

require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/AuditLogger.php';

// Security: Ensure it's a POST request and has a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf()) {
    die('Invalid request or security token.');
}

$redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'index.php';

try {
    // --- Approve Deletion Action ---
    if (isset($_POST['approve_deletion'])) {
        $accountIds = [];
        // This is assuming your form sends an array of account IDs to delete.
        if (isset($_POST['account_ids']) && is_array($_POST['account_ids'])) {
            $accountIds = array_map('intval', $_POST['account_ids']);
        }

        if (!empty($accountIds)) {
            $inClause = implode(',', array_fill(0, count($accountIds), '?'));
            
            // --- THIS IS THE CRITICAL FIX for the Deletion Date ---
            // We UPDATE the record to set its status and timestamp the deletion. WE DO NOT DELETE IT.
            $stmt = $db->prepare(
                "UPDATE user_accounts SET status = 'deleted', pending_deletion = 0, deletion_date = NOW() WHERE id IN ($inClause)"
            );
            $stmt->execute($accountIds);
            
            $_SESSION['message'] = $stmt->rowCount() . ' account(s) have been marked as deleted.';
            $_SESSION['message_type'] = 'success';

            foreach ($accountIds as $accountId) {
                 AuditLogger::getLogger('user_management')->warning('User account approved for deletion (soft deleted).', [
                    'user' => $_SESSION['username'], 
                    'details' => json_encode(['user_account_id' => $accountId])
                ]);
            }
        }
    }
    
    // --- Restore Account Action ---
    if (isset($_POST['restore_account'])) {
        $accountIds = [];
        if (isset($_POST['account_ids']) && is_array($_POST['account_ids'])) {
            $accountIds = array_map('intval', $_POST['account_ids']);
        }

        if (!empty($accountIds)) {
            $inClause = implode(',', array_fill(0, count($accountIds), '?'));
            $stmt = $db->prepare("UPDATE user_accounts SET pending_deletion = 0, status = 'active', deletion_date = NULL WHERE id IN ($inClause)");
            $stmt->execute($accountIds);
            // ... (rest of restore logic) ...
        }
    }

} catch (PDOException $e) {
    error_log("User action failed: " . $e->getMessage());
    $_SESSION['message'] = 'A database error occurred.';
    $_SESSION['message_type'] = 'danger';
}

header('Location: ' . $redirectUrl);
exit();
