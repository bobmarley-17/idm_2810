<?php
// inactive_users.php - REVISED LOGIC v2

// FIX: Standardize the file startup for security, sessions, and database connection.
require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';

// FIX: REMOVED the redundant, insecure database connection. We now use '$db' from bootstrap.

$status = $_GET['status'] ?? 'inactive'; // Default to the "pending" view
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- Build the SQL Query Based on the New, Correct Logic ---

// This part of the query is common to both views. It gets the user and a summary of their account statuses.
$baseQuery = "
    SELECT 
        u.id, u.employee_id, u.first_name, u.last_name, u.email, u.updated_at,
        GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS source_names,
        SUM(CASE WHEN ua.status = 'active' THEN 1 ELSE 0 END) as active_account_count
    FROM users u
    LEFT JOIN user_accounts ua ON u.id = ua.user_id
    LEFT JOIN account_sources s ON ua.source_id = s.id
";

// Common WHERE clause for filtering by search term.
$whereClause = " WHERE 1=1 ";
if ($searchTerm) {
    $whereClause .= " AND (u.email LIKE :search OR u.employee_id LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
}

$groupByClause = " GROUP BY u.id ";
$orderByClause = " ORDER BY u.updated_at DESC";

// --- Logic for the Two Different Tabs ---

if ($status === 'inactive') {
    // "INACTIVE / PENDING DELETION" Tab
    // A user is here if they have a 'pending' record in the defunct_users table. This is your "to-do" list.
    // This logic from your original file was correct for this tab's purpose.
    $query = $baseQuery . 
             " JOIN defunct_users d ON u.id = d.user_id AND d.status = 'pending' " .
             $whereClause . 
             $groupByClause . 
             $orderByClause;

} else { // Default to 'deleted' view
    // "DELETED" Tab
    // A user is here if their defunct record is 'deleted' AND they have ZERO active accounts left.
    $status = 'deleted'; // Ensure status is set correctly for the active tab
    $query = $baseQuery . 
             " JOIN defunct_users d ON u.id = d.user_id AND d.status = 'deleted' " .
             $whereClause . 
             $groupByClause . 
             " HAVING active_account_count = 0 " . // THIS IS THE CRUCIAL NEW CONDITION
             $orderByClause;
}

$stmt = $db->prepare($query);

// Bind the search term if it exists.
if ($searchTerm) {
    $searchPattern = "%$searchTerm%";
    $stmt->bindValue(':search', $searchPattern);
}

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Inactive/Deleted Users";
include 'templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Inactive & Defunct Users</h2>
            <p class="text-muted">Review users pending deletion or view historical records of fully deleted users.</p>
        </div>
        <div>
            <a href="users.php" class="btn btn-primary">View Active Users</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link <?= $status === 'inactive' ? 'active' : '' ?>" href="?status=inactive">
                                Pending Deletion
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $status === 'deleted' ? 'active' : '' ?>" href="?status=deleted">
                                Fully Deleted Archive
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <form class="d-flex" method="GET">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit" class="btn btn-outline-secondary ms-2">Search</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Sources</th>
                            <th>Active Accounts</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No users found in this category.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['employee_id']) ?></td>
                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars(str_replace(',', ', ', $user['source_names'] ?? 'None')) ?></td>
                                <td>
                                    <span class="badge bg-<?= $user['active_account_count'] > 0 ? 'success' : 'secondary' ?>">
                                        <?= (int)$user['active_account_count'] ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($user['updated_at'])) ?></td>
                                <td>
                                    <a href="user_detail.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
