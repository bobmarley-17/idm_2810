
<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/CorrelationEngine.php';
include 'templates/header.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);
$correlationEngine = new CorrelationEngine($db);

// Fetch only active users
$query = "SELECT u.*, GROUP_CONCAT(DISTINCT s.name) as source_names
    FROM users u 
    LEFT JOIN user_accounts ua ON u.id = ua.user_id 
    LEFT JOIN account_sources s ON ua.source_id = s.id 
    WHERE u.status = 'active'
    GROUP BY u.id";

$usersStmt = $db->query($query);
$users = $usersStmt ? $usersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

$errors = [];
$success = '';
$editUser = null;
$searchTerm = '';

if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);

    // Fetch user info
    $stmt = $db->prepare("SELECT id, employee_id, email FROM users WHERE id=?");
    $stmt->execute([$deleteId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Set user status to inactive
        $update = $db->prepare("UPDATE users SET status='inactive' WHERE id=?");
        $ok = $update->execute([$deleteId]);

        // Get all source_ids for this user
        $sourceStmt = $db->prepare("SELECT DISTINCT source_id FROM user_accounts WHERE user_id = ?");
        $sourceStmt->execute([$user['id']]);
        $sourceIds = $sourceStmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($sourceIds)) {
            $sourceIds = [40]; // Default fallback, adjust to your baseline source_id
        }

        $allOk = true;
        foreach ($sourceIds as $sourceId) {
            $defunct = $db->prepare("
                INSERT INTO defunct_users (user_id, source_id, employee_id, email, deleted_at, status)
                VALUES (?, ?, ?, ?, NOW(), 'pending')
                ON DUPLICATE KEY UPDATE status='pending', deleted_at=NOW()
            ");
            $allOk = $allOk && $defunct->execute([$user['id'], $sourceId, $user['employee_id'], $user['email']]);
        }

        if ($ok && $allOk) {
            $success = "User marked as inactive and pending deletion.";
        } else {
            $errors[] = "Failed to mark user as inactive/pending deletion.";
        }
    } else {
        $errors[] = "User not found.";
    }
}


// Handle Edit: Show form
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editUser) {
        $errors[] = "User not found.";
        $editUser = null;
    }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = intval($_POST['id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $supervisor_email = trim($_POST['supervisor_email']);
    $status = trim($_POST['status']);

    // Basic validation
    if (!$first_name || !$last_name || !$email || !$supervisor_email || !$status) {
        $errors[] = "All fields are required.";
    } else {
        $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=?, supervisor_email=?, status=? WHERE id=?");
        if ($stmt->execute([$first_name, $last_name, $email, $supervisor_email, $status, $id])) {
            $success = "User updated successfully.";
            $editUser = null; // Hide edit form after update
        } else {
            $errors[] = "Failed to update user.";
        }
    }
}

// Handle Search
if (isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
}

// Fetch users with optional search filtering
if (!empty($searchTerm)) {
    $sql = "SELECT * FROM users WHERE 
            first_name LIKE ? OR 
            last_name LIKE ? OR 
            email LIKE ? OR 
            employee_id LIKE ? OR 
            CONCAT(first_name, ' ', last_name) LIKE ?
            ORDER BY last_name, first_name";
    $searchPattern = '%' . $searchTerm . '%';
    $stmt = $db->prepare($sql);
    $stmt->execute([$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern]);
} else {
    $stmt = $db->query("SELECT * FROM users ORDER BY last_name, first_name");
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get IDs of users pending deletion
$pendingStmt = $db->query("SELECT DISTINCT user_id FROM user_accounts WHERE status = 'pending_deletion'");
$pendingUserIds = $pendingStmt ? $pendingStmt->fetchAll(PDO::FETCH_COLUMN) : [];
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Users</h2>
        <a href="inactive_users.php" class="btn btn-secondary">View Inactive/Deleted Users</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success ?? '') ?></div>
    <?php endif; ?>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-header">Search Users</div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name, email, or employee ID..." 
                           value="<?= htmlspecialchars($searchTerm ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($searchTerm)): ?>
                        <a href="users.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($editUser): ?>
        <!-- Edit User Form -->
        <div class="card mb-4">
            <div class="card-header">Edit User</div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                    <?php if (!empty($searchTerm)): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($searchTerm ?? '') ?>">
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($editUser['first_name']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($editUser['last_name']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($editUser['email']) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Supervisor Email</label>
                        <input type="email" name="supervisor_email" class="form-control" required value="<?= htmlspecialchars($editUser['supervisor_email']) ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Status</label>
                        <input type="text" name="status" class="form-control" required value="<?= htmlspecialchars($editUser['status']) ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="update_user" class="btn btn-primary">Update</button>
                        <a href="users.php<?= !empty($searchTerm) ? '?search=' . urlencode($searchTerm) : '' ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Results Summary -->
    <?php if (!empty($searchTerm)): ?>
        <div class="alert alert-info">
            Showing <?= count($users) ?> result(s) for "<?= htmlspecialchars($searchTerm ?? '') ?>"
        </div>
    <?php endif; ?>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th style="width: 15%">Name</th>
                <th style="width: 20%">Email</th>
                <th style="width: 15%">Supervisor Email</th>
                <th style="width: 10%">Status</th>
                <th style="width: 15%">Created Date</th>
                <th style="width: 20%">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" class="text-center">
                        <?= !empty($searchTerm) ? 'No users found matching your search.' : 'No users found.' ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                            <?php if (in_array($user['id'], $pendingUserIds)): ?>
                                <span class="badge bg-danger ms-1">Pending Deletion</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['supervisor_email'] ?? '') ?></td>
                        <td><?= htmlspecialchars($user['status']) ?></td>
                        <td><?= !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : ''  ?></td>
			
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="users.php?edit=<?= $user['id'] ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                                   class="btn btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="user_detail.php?id=<?= $user['id'] ?>" 
                                   class="btn btn-info"><i class="fas fa-eye"></i></a>
                                <a href="users.php?delete=<?= $user['id'] ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Delete this user and all linked accounts?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'templates/footer.php'; ?>
