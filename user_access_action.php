<?php
// user_access_action.php

require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/AuditLogger.php';

// --- Security and Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf() || empty($_POST['user_id']) || empty($_POST['action'])) {
    $_SESSION['message'] = 'Invalid request or session expired.';
    $_SESSION['message_type'] = 'danger';
    header('Location: index.php'); // Redirect to index
    exit();
}

$userId = $_POST['user_id'];
$action = $_POST['action'];
$adminUsername = $_SESSION['username'];

// --- Action Handler ---
try {
    switch ($action) {
        case 'grant':
            $oneTimePassword = bin2hex(random_bytes(8));
            $passwordHash = password_hash($oneTimePassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$passwordHash, $userId]);
            AuditLogger::getLogger('user_management')->info('Admin access granted.', ['user' => $adminUsername, 'details' => json_encode(['target_user_id' => $userId])]);
            $_SESSION['one_time_password'] = $oneTimePassword;
            $_SESSION['message'] = 'Access granted. Please provide the one-time password to the user.';
            $_SESSION['message_type'] = 'success';
            break;
        case 'revoke':
            $stmt = $db->prepare("UPDATE users SET password_hash = NULL WHERE id = ?");
            $stmt->execute([$userId]);
            AuditLogger::getLogger('user_management')->info('Admin access revoked.', ['user' => $adminUsername, 'details' => json_encode(['target_user_id' => $userId])]);
            $_SESSION['message'] = 'Access revoked successfully.';
            $_SESSION['message_type'] = 'warning';
            break;
        case 'change_password':
            if (empty($_POST['new_password'])) {
                $_SESSION['message'] = 'New password cannot be empty.';
                $_SESSION['message_type'] = 'danger';
                break;
            }
            $newPassword = $_POST['new_password'];
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newPasswordHash, $userId]);
            AuditLogger::getLogger('user_management')->info('User password changed by admin.', ['user' => $adminUsername, 'details' => json_encode(['target_user_id' => $userId])]);
            $_SESSION['message'] = 'Password changed successfully.';
            $_SESSION['message_type'] = 'success';
            break;
        default:
            $_SESSION['message'] = 'Unknown action.';
            $_SESSION['message_type'] = 'danger';
    }
} catch (PDOException $e) {
    error_log("Access action failed: " . $e->getMessage());
    $_SESSION['message'] = 'A database error occurred.';
    $_SESSION['message_type'] = 'danger';
}

// Redirect back to the main index page
header('Location: index.php');
exit();
