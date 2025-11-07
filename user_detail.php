<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);
$userId = $_GET['user_id'] ?? ($_GET['id'] ?? 0);
$user = $userManager->getUserWithAccounts($userId);

// Immediately check if a user was found.
if (!$user) {
    header("Location: users.php");
    exit;
}

// Check defunct status from defunct_users table
$defunctStatus = null;
$defunctStmt = $db->prepare("SELECT status FROM defunct_users WHERE email = ? OR employee_id = ? LIMIT 1");
$defunctStmt->execute([$user['email'], $user['employee_id']]);
if ($row = $defunctStmt->fetch(PDO::FETCH_ASSOC)) {
    $defunctStatus = $row['status'];
}

$fromPending = isset($_GET['pending']) && $_GET['pending'] == 1;

// Check if this user has any linked accounts with pending deletion
$pending = false;
$isAnyActive = false;
foreach ($user['accounts'] as $acc) {
    if (isset($acc['id'])) {
        $stmt = $db->prepare("SELECT pending_deletion, status FROM user_accounts WHERE id = ?");
        $stmt->execute([$acc['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if ($row['pending_deletion']) {
                $pending = true;
            }
            if ($row['status'] === 'active') {
                $isAnyActive = true;
            }
        }
    }
}

include 'templates/header.php';
?>

<h2>User Details: <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
    <?php if ($pending): ?>
        <span class="badge bg-danger ms-2">Pending Deletion</span>
    <?php endif; ?>

    <?php if (!$isAnyActive && $defunctStatus === 'deleted'): ?>
        <span class="badge bg-secondary ms-2">Deleted</span>
    <?php elseif ($isAnyActive): ?>
        <span class="badge bg-success ms-2">Active</span>
    <?php elseif ($defunctStatus === 'pending'): ?>
        <span class="badge bg-danger ms-2">Defunct (Pending)</span>
    <?php endif; ?>
    <a href="user_access.php?id=<?= $user['id'] ?>" class="btn btn-primary">
        <i class="fas fa-user-shield"></i> Manage Login Access
    </a>
</h2>

<?php if ($fromPending): ?>
    <div class="alert alert-warning">
        You are reviewing this user as part of the pending deletions workflow. All accounts from all sources are shown below for admin review.
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <h4>Basic Information</h4>
        <table class="table">
            <!-- ... Basic info table rows ... -->
             <tr><th>Username</th><td><?= htmlspecialchars($user['username'] ?? '') ?></td></tr>
             <tr><th>First Name</th><td><?= htmlspecialchars($user['first_name'] ?? '') ?></td></tr>
             <tr><th>Last Name</th><td><?= htmlspecialchars($user['last_name'] ?? '') ?></td></tr>
             <tr><th>Email</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
             <tr><th>Supervisor Email</th><td><?= htmlspecialchars($user['supervisor_email'] ?? 'Not Assigned') ?></td></tr>
             <tr><th>Status</th><td><?= htmlspecialchars($isAnyActive ? 'active' : 'deleted') ?></td></tr>
        </table>
    </div>
</div>

<h4>Linked Accounts</h4>

<?php if ($pending): ?>
    <!-- ... Pending deletion form ... -->
<?php endif; ?>

<table class="table">
    <thead>
        <tr>
            <th>Source</th>
            <th>Category</th>
            <th>User ID</th>
            <th>Deletion Date</th>
            <th>Username</th>
            <th>Email</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($user['accounts'] as $account): ?>
        <tr<?php
            $pending_row = false; // Use a different variable name to avoid conflict
            if (isset($account['id'])) {
                $stmt_pending = $db->prepare("SELECT pending_deletion FROM user_accounts WHERE id = ?");
                $stmt_pending->execute([$account['id']]);
                if ($stmt_pending->fetchColumn()) {
                    $pending_row = true;
                }
            }
            echo $pending_row ? ' class="table-danger"' : '';
        ?>>

            <!-- ========== THE ONLY MODIFIED CODE BLOCK ========== -->
            <td>
                <?php if (!empty($account['source_id']) && !empty($account['source_name'])): ?>
                    <a href="view_source_csv.php?source_id=<?= $account['source_id'] ?>&email=<?= urlencode($user['email']) ?>"
                       title="Click to view the raw backend CSV file and highlight this user's email">
                        <?= htmlspecialchars($account['source_name']) ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($account['source_name'] ?? 'N/A') ?>
                <?php endif; ?>
            </td>
            <!-- ============================================= -->

            <td><?= htmlspecialchars($account['category'] ?? '') ?></td>
            <td><?= htmlspecialchars($account['user_id'] ?? '') ?></td>
            <td><?= !empty($account['deletion_date']) ? htmlspecialchars(date('M j, Y', strtotime($account['deletion_date']))) : '' ?></td>
            <td><?= htmlspecialchars($account['username'] ?? '') ?></td>
            <td><?= htmlspecialchars($account['email'] ?? '') ?></td>
            <td>
                <?php if ($pending_row): ?>
                    <span class="badge bg-danger">Pending Deletion</span>
                <?php elseif (isset($account['status'])): ?>
                    <?php
                    $badgeClass = 'bg-secondary';
                    if ($account['status'] === 'active') {
                        $badgeClass = 'bg-success';
                    } elseif ($account['status'] === 'inactive') {
                        $badgeClass = 'bg-warning';
                    }
                    ?>
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($account['status'])) ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- The hidden form and script have been removed -->

<?php include 'templates/footer.php'; ?>
