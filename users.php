<?php
require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/CorrelationEngine.php';
require_once 'lib/LogHelper.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);
$correlationEngine = new CorrelationEngine($db);

// --- Users list filter (active/inactive/all) ---
$filter = isset($_GET['filter']) ? strtolower($_GET['filter']) : 'active';
$allowedFilters = ['active','inactive','all'];
if (!in_array($filter, $allowedFilters)) $filter = 'active';
$errors = [];
$success = '';
$editUser = null;
$searchTerm = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);

    // Fetch user info
    $stmt = $db->prepare("SELECT id, employee_id, email, first_name, last_name FROM users WHERE id=?");
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
            $sourceIds = [40]; // Default fallback
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
            // LOG: User marked for deletion
            LogHelper::logSecurity('User marked for deletion', 'warning', [
                'target_user_id' => $user['id'],
                'target_email' => $user['email'],
                'target_employee_id' => $user['employee_id'],
                'target_name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'sources_affected' => $sourceIds,
                'deleted_by' => $_SESSION['username'] ?? 'unknown'
            ]);
            
            LogHelper::logDatabaseChange('users', 'UPDATE', $user['id'], [
                'field' => 'status',
                'old_value' => 'active',
                'new_value' => 'inactive'
            ]);
            
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

    // Get old values for logging
    $oldStmt = $db->prepare("SELECT first_name, last_name, email, supervisor_email, status FROM users WHERE id=?");
    $oldStmt->execute([$id]);
    $oldValues = $oldStmt->fetch(PDO::FETCH_ASSOC);

    // Basic validation
    if (!$first_name || !$last_name || !$email || !$supervisor_email || !$status) {
        $errors[] = "All fields are required.";
    } else {
        $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=?, supervisor_email=?, status=? WHERE id=?");
        if ($stmt->execute([$first_name, $last_name, $email, $supervisor_email, $status, $id])) {
            
            // LOG: User updated
            LogHelper::logUserAction('User updated', $id, [
                'updated_by' => $_SESSION['username'] ?? 'unknown',
                'changes' => [
                    'first_name' => ['old' => $oldValues['first_name'], 'new' => $first_name],
                    'last_name' => ['old' => $oldValues['last_name'], 'new' => $last_name],
                    'email' => ['old' => $oldValues['email'], 'new' => $email],
                    'supervisor_email' => ['old' => $oldValues['supervisor_email'], 'new' => $supervisor_email],
                    'status' => ['old' => $oldValues['status'], 'new' => $status]
                ]
            ]);
            
            LogHelper::logDatabaseChange('users', 'UPDATE', $id, [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'status' => $status
            ]);
            
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

// Fetch users with optional search filtering and status filter
$params = [];
if (!empty($searchTerm)) {
    $searchPattern = '%' . $searchTerm . '%';
    $searchClause = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern];
} else {
    $searchClause = "1=1";
}

// status filter
if ($filter === 'active') {
    $statusClause = "u.status = 'active'";
} elseif ($filter === 'inactive') {
    $statusClause = "u.status != 'active'";
} else { // all
    $statusClause = "1=1";
}

$sql = "SELECT u.* FROM users u WHERE ($searchClause) AND ($statusClause) ORDER BY u.last_name, u.first_name";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get IDs of users pending deletion
$pendingStmt = $db->query("SELECT DISTINCT user_id FROM user_accounts WHERE status = 'pending_deletion'");
$pendingUserIds = $pendingStmt ? $pendingStmt->fetchAll(PDO::FETCH_COLUMN) : [];

include 'templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Users <small class="text-muted"><?= ucfirst($filter) ?></small></h2>
        <?php
            // build toggle URL (preserve search if present)
            if ($filter === 'active') {
                $toggleFilter = 'inactive';
                $toggleLabel = 'Show Inactive';
            } elseif ($filter === 'inactive') {
                $toggleFilter = 'all';
                $toggleLabel = 'Show All';
            } else {
                $toggleFilter = 'active';
                $toggleLabel = 'Show Active';
            }
            $toggleUrl = 'users.php?filter=' . $toggleFilter;
            if (!empty($searchTerm)) $toggleUrl .= '&search=' . urlencode($searchTerm);
        ?>
        <a href="<?= htmlspecialchars($toggleUrl) ?>" class="btn btn-secondary">
            <?= htmlspecialchars($toggleLabel) ?>
        </a>
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
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control"
                           placeholder="Search by name, email, or employee ID..."
                           value="<?= htmlspecialchars($searchTerm ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($searchTerm)): ?>
                        <a href="users.php?filter=<?= htmlspecialchars($filter) ?>" class="btn btn-secondary">Clear</a>
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
                        <a href="users.php?filter=<?= htmlspecialchars($filter) ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" class="btn btn-secondary">Cancel</a>
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

    <div class="table-responsive w-100">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
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
                        <td colspan="6" class="text-center py-4">
                            <i class="fas fa-inbox fa-2x mb-2 text-muted d-block"></i>
                            <?= !empty($searchTerm) ? 'No users found matching your search.' : 'No users found.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
                                <?php if (in_array($user['id'], $pendingUserIds)): ?>
                                    <span class="badge bg-danger ms-1">Pending Deletion</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['supervisor_email'] ?? '') ?></td>
                            <td>
                                <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= htmlspecialchars($user['status']) ?>
                                </span>
                            </td>
                            <td><?= !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '' ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="users.php?edit=<?= $user['id'] ?>&filter=<?= htmlspecialchars($filter) ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>"
                                       class="btn btn-warning" title="Edit user">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="user_detail.php?id=<?= $user['id'] ?>"
                                       class="btn btn-info" title="View details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="users.php?delete=<?= $user['id'] ?>&filter=<?= htmlspecialchars($filter) ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>"
                                       class="btn btn-danger"
                                       title="Delete user"
                                       onclick="return confirm('Are you sure you want to mark this user for deletion?\n\nThis will set the user to inactive and queue them for account cleanup.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="mt-3 text-muted">
        <small>Showing <?= count($users) ?> user(s)</small>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
