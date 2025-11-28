<?php
require_once 'bootstrap.php';
require_once 'auth_check.php';

$redirectParam = urlencode('users.php?filter=all');
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/CorrelationEngine.php';
require_once 'lib/LogHelper.php';

// Initialize database connection
$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$userManager = new UserManager($db);
$correlationEngine = new CorrelationEngine($db);

// Handle deletion approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_deleted'])) {
    $userId = intval($_POST['user_id']);
    $sourceId = intval($_POST['source_id']);

    try {
        // Get user and source details BEFORE deletion for logging
        $userStmt = $db->prepare("
            SELECT u.id, u.email, u.employee_id, u.first_name, u.last_name,
                   s.name as source_name, s.type as source_type
            FROM users u
            LEFT JOIN account_sources s ON s.id = ?
            WHERE u.id = ?
        ");
        $userStmt->execute([$sourceId, $userId]);
        $userDetails = $userStmt->fetch(PDO::FETCH_ASSOC);

        // Get account count being deleted
        $accountStmt = $db->prepare("SELECT COUNT(*) as count FROM user_accounts WHERE user_id = ? AND source_id = ?");
        $accountStmt->execute([$userId, $sourceId]);
        $accountCount = $accountStmt->fetchColumn();

        // Update defunct_users entry
        $stmt = $db->prepare("UPDATE defunct_users 
                              SET status='deleted', deleted_at=NOW() 
                              WHERE user_id=? AND source_id=?");
        $stmt->execute([$userId, $sourceId]);

        // Mark main user record as inactive
        $stmt = $db->prepare("UPDATE users 
                              SET status='inactive', updated_at=NOW() 
                              WHERE id=?");
        $stmt->execute([$userId]);

        // Mark only linked accounts for this source as deleted
        $stmtAccounts = $db->prepare("UPDATE user_accounts 
                                      SET status='deleted', updated_at=NOW() 
                                      WHERE user_id=? AND source_id=?");
        $stmtAccounts->execute([$userId, $sourceId]);

        // ===== LOG DELETION APPROVAL - CRITICAL SECURITY EVENT =====
        
        LogHelper::logSecurity('User deletion approved and executed', 'critical', [
            'target_user_id' => $userId,
            'target_email' => $userDetails['email'] ?? 'unknown',
            'target_employee_id' => $userDetails['employee_id'] ?? 'unknown',
            'target_name' => trim(($userDetails['first_name'] ?? '') . ' ' . ($userDetails['last_name'] ?? '')),
            'source_id' => $sourceId,
            'source_name' => $userDetails['source_name'] ?? 'unknown',
            'source_type' => $userDetails['source_type'] ?? 'unknown',
            'accounts_affected' => $accountCount,
            'approved_by' => $_SESSION['username'] ?? 'unknown',
            'approver_id' => $_SESSION['user_id'] ?? null
        ]);

        LogHelper::logDatabaseChange('defunct_users', 'UPDATE', $userId, [
            'user_id' => $userId,
            'source_id' => $sourceId,
            'field' => 'status',
            'old_value' => 'pending',
            'new_value' => 'deleted',
            'deleted_at' => date('Y-m-d H:i:s')
        ]);

        // Success message
        $successEmail = $userDetails['email'] ?? 'User';
        $successSource = $userDetails['source_name'] ?? 'source';
        $_SESSION['deletion_success'] = "User {$successEmail} marked as deleted for {$successSource}";
        
    } catch (Exception $e) {
        // Log error
        LogHelper::logError('Deletion approval failed', [
            'user_id' => $userId,
            'source_id' => $sourceId,
            'error' => $e->getMessage()
        ]);
        
        $_SESSION['deletion_error'] = "Failed to mark user as deleted: " . $e->getMessage();
    }

    // Redirect back
    header("Location: pending_deletions.php");
    exit;
}

// Get each user and source with pending status separately
$pendingStmt = $db->query("
    SELECT
        du.user_id,
        du.source_id,
        du.email,
        du.employee_id,
        s.name AS source_name,
        du.status
    FROM defunct_users du
    JOIN account_sources s ON du.source_id = s.id
    WHERE du.status = 'pending'
    ORDER BY du.email, s.name
");
$pendingUsers = $pendingStmt ? $pendingStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$pageTitle = "Pending Deletions";
include 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <h2>Users Pending Deletion</h2>
    
    <?php if (isset($_SESSION['deletion_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['deletion_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['deletion_success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['deletion_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['deletion_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['deletion_error']); ?>
    <?php endif; ?>
    
    <?php if (empty($pendingUsers)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> No users are currently pending deletion.
        </div>
    <?php else: ?>
        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong><?= count($pendingUsers) ?></strong> user deletion(s) require approval.
        </div>
        
        <div class="table-responsive w-100">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Email</th>
                        <th>Employee ID</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingUsers as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($user['employee_id'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($user['source_name']) ?></td>
                        <td>
                            <span class="badge bg-danger">Pending Deletion</span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="user_detail.php?user_id=<?= $user['user_id'] ?>&source_id=<?= $user['source_id'] ?>&pending=1&from=<?= $redirectParam ?>"
                                   class="btn btn-info" title="Review user details">
                                    <i class="fas fa-eye"></i> Review
                                </a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to approve deletion for <?= htmlspecialchars($user['email'] ?? 'this user') ?> from <?= htmlspecialchars($user['source_name']) ?>?\n\nThis action will mark the user as deleted and cannot be easily undone.');">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <input type="hidden" name="source_id" value="<?= $user['source_id'] ?>">
                                    <button type="submit" name="mark_deleted" class="btn btn-danger" title="Approve deletion">
                                        <i class="fas fa-trash-alt"></i> Approve Deletion
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div class="mt-4">
        <a href="users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-home"></i> Dashboard
        </a>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
