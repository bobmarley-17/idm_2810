<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/CorrelationEngine.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);
$correlationEngine = new CorrelationEngine($db);


// Get all distinct users from defunct_users with status 'pending'
$pendingStmt = $db->query("
    SELECT DISTINCT 
        du.user_id,
        du.email,
        du.employee_id,
        GROUP_CONCAT(s.name) as source_names,
        COUNT(DISTINCT du.source_id) as pending_count,
        COUNT(DISTINCT CASE WHEN du.status = 'deleted' THEN du.source_id END) as deleted_count
    FROM defunct_users du
    JOIN account_sources s ON du.source_id = s.id
    WHERE du.status = 'pending'
    GROUP BY du.user_id, du.email, du.employee_id
    ORDER BY du.email
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
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['employee_id']) ?></td>
                    <td>
                        <span class="badge bg-danger"><?= $user['pending_count'] ?> Pending</span>
                        <?php if ($user['deleted_count'] > 0): ?>
                            <span class="badge bg-secondary"><?= $user['deleted_count'] ?> Deleted</span>
                        <?php endif; ?>
                        <br>
                        <small class="text-muted"><?= htmlspecialchars($user['source_names']) ?></small>
                    </td>
                    <td>
                        <a href="user_detail.php?user_id=<?= $user['user_id'] ?>&pending=1" class="btn btn-sm btn-outline-danger">Review</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php include 'templates/footer.php'; ?>
