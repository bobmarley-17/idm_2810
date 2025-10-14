<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/CorrelationEngine.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_deleted'])) {
    $userId = intval($_POST['user_id']);
    $sourceId = intval($_POST['source_id']);

    // Update defunct_users entry
    $stmt = $db->prepare("UPDATE defunct_users
                          SET status='deleted', deleted_at=NOW()
                          WHERE user_id=? AND source_id=?");
    $stmt->execute([$userId, $sourceId]);

    // Also mark main user record as inactive
    $stmt = $db->prepare("UPDATE users
                          SET status='inactive', updated_at=NOW()
                          WHERE id=?");
    $stmt->execute([$userId]);

    // Mark only linked accounts for this source as deleted
    $stmtAccounts = $db->prepare("UPDATE user_accounts SET status='deleted' WHERE user_id=? AND source_id=?");
    $stmtAccounts->execute([$userId, $sourceId]);

    //Mark all linked accounts as deleted
    //$stmt = $db->prepare("UPDATE user_accounts SET status='deleted' WHERE user_id=?");
    //$stmt->execute([$userId]);

    // Redirect back with success
    header("Location: pending_deletions.php?msg=marked_deleted");
    exit;
}

//if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_deleted'])) {
//    $userId = intval($_POST['user_id']);
//    $sourceId = intval($_POST['source_id']);
//    $stmt = $db->prepare("UPDATE defunct_users SET status='deleted', deleted_at=NOW() WHERE user_id=? AND source_id=?");
//    $stmt->execute([$userId, $sourceId]);
    // Optionally: add a success message or reload page
//}


$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);
$correlationEngine = new CorrelationEngine($db);

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
<div class="container mt-4">
    <h2>Users Pending Deletion</h2>
    <?php if (empty($pendingUsers)): ?>
        <div class="alert alert-success">No users are currently pending deletion.</div>
    <?php else: ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingUsers as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($user['employee_id'] ?? '') ?></td>
                    <td>
                        <span class="badge bg-danger">Pending - <?= htmlspecialchars($user['source_name']) ?></span>
                    </td>
                    <td>
                        <a href="user_detail.php?user_id=<?= $user['user_id'] ?>&source_id=<?= $user['source_id'] ?>&pending=1" class="btn btn-sm btn-info">Review</a>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                            <input type="hidden" name="source_id" value="<?= $user['source_id'] ?>">
                            <button type="submit" name="mark_deleted" class="btn btn-sm btn-outline-success"
                                onclick="return confirm('Confirm deletion for <?= htmlspecialchars($user['source_name']) ?>?')">
                                Mark <?= htmlspecialchars($user['source_name']) ?> as Deleted
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php include 'templates/footer.php'; ?>

