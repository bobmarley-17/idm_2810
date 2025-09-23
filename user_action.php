<?php
require_once 'config/database.php';
$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Approve Deletion
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
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            // Remove accounts and optionally update defunct_users status
            $stmt = $db->prepare("DELETE FROM user_accounts WHERE id IN ($in)");
            $stmt->execute($ids);
            // Optionally, mark defunct_users as deleted if all accounts for user are deleted
            $userStmt = $db->prepare("SELECT user_id FROM user_accounts WHERE id = ? LIMIT 1");
            foreach ($ids as $accountId) {
                $userStmt->execute([$accountId]);
                $userId = $userStmt->fetchColumn();
                if ($userId) {
                    $check = $db->prepare("SELECT COUNT(*) FROM user_accounts WHERE user_id = ?");
                    $check->execute([$userId]);
                    if ($check->fetchColumn() == 0) {
                        // Find defunct_users row by email or employee_id
                        $user = $db->query("SELECT email, employee_id FROM users WHERE id = $userId")->fetch(PDO::FETCH_ASSOC);
                        if ($user) {
                            $upd = $db->prepare("UPDATE defunct_users SET status = 'deleted' WHERE (email = ? OR employee_id = ?)");
                            $upd->execute([$user['email'], $user['employee_id']]);
                        }
                    }
                }
            }
            }
        }
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    // Restore
    if (isset($_POST['restore_account'])) {
        $ids = [];
        if (isset($_POST['account_id'])) {
            $ids[] = intval($_POST['account_id']);
        }
        if (isset($_POST['account_ids']) && is_array($_POST['account_ids'])) {
            foreach ($_POST['account_ids'] as $aid) {
                $ids[] = intval($aid);
            }
        }
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("UPDATE user_accounts SET pending_deletion = 0 WHERE id IN ($in)");
            $stmt->execute($ids);
        }
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
}
header('Location: index.php');
exit;

