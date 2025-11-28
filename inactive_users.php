<?php
// inactive_users.php - View inactive and deleted users

require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/LogHelper.php'; // Available if needed later

$status = $_GET['status'] ?? 'inactive'; // Default to the "inactive" view
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- Build the SQL Query Based on Status ---

// Common base query
$baseQuery = "
    SELECT
        u.id, u.employee_id, u.first_name, u.last_name, u.email, u.updated_at,
        GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS source_names,
        SUM(CASE WHEN ua.status = 'active' THEN 1 ELSE 0 END) as active_account_count
    FROM users u
    LEFT JOIN user_accounts ua ON u.id = ua.user_id
    LEFT JOIN account_sources s ON ua.source_id = s.id
";

// Common WHERE clause for search
$whereClause = " WHERE 1=1 ";
if ($searchTerm) {
    $whereClause .= " AND (u.email LIKE :search OR u.employee_id LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
}

$groupByClause = " GROUP BY u.id ";
$orderByClause = " ORDER BY u.updated_at DESC";

// --- Logic for the Two Different Tabs ---

if ($status === 'inactive') {
    // "INACTIVE / PENDING DELETION" Tab
    $query = $baseQuery .
             " JOIN defunct_users d ON u.id = d.user_id AND d.status = 'pending' " .
             $whereClause .
             $groupByClause .
             $orderByClause;

} else { // 'deleted' view
    $status = 'deleted';
    // "DELETED" Tab - users with deleted status and zero active accounts
    $query = $baseQuery .
             " JOIN defunct_users d ON u.id = d.user_id AND d.status = 'deleted' " .
             $whereClause .
             $groupByClause .
             " HAVING active_account_count = 0 " .
             $orderByClause;
}

$stmt = $db->prepare($query);

// Bind search term if exists
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
            <h2>Inactive & Deleted Users</h2>
            <p class="text-muted mb-0">
                <?= $status === 'inactive' ? 'Users pending deletion approval' : 'Users with deleted accounts' ?>
                <?php if ($searchTerm): ?>
                    - Searching for: <strong><?= htmlspecialchars($searchTerm) ?></strong>
                <?php endif; ?>
            </p>
        </div>
        <div>
            <a href="users.php" class="btn btn-primary">
                <i class="fas fa-users"></i> View Active Users
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link <?= $status === 'inactive' ? 'active' : '' ?>" 
                               href="?status=inactive<?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?>">
                                <i class="fas fa-clock"></i> Pending Deletion
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $status === 'deleted' ? 'active' : '' ?>" 
                               href="?status=deleted<?= $searchTerm ? '&search=' . urlencode($searchTerm) : '' ?>">
                                <i class="fas fa-trash-alt"></i> Deleted
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <form class="d-flex" method="GET">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name, email, ID..." 
                               value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm): ?>
                            <a href="?status=<?= htmlspecialchars($status) ?>" class="btn btn-outline-danger ms-1">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle"></i> 
                    Showing <strong><?= count($users) ?></strong> user(s)
                </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
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
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <p class="mb-0">
                                    <?= $searchTerm ? 'No users found matching your search.' : 'No users found in this category.' ?>
                                </p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['employee_id']) ?></td>
                                <td><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($user['source_names'] ?? 'None') ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $user['active_account_count'] > 0 ? 'success' : 'secondary' ?>">
                                        <?= (int)$user['active_account_count'] ?>
                                    </span>
                                </td>
                                <td><?= $user['updated_at'] ? date('M j, Y H:i', strtotime($user['updated_at'])) : 'N/A' ?></td>
                                <td>
                                    <a href="user_detail.php?id=<?= $user['id'] ?>" 
                                       class="btn btn-sm btn-info" 
                                       title="View user details">
                                        <i class="fas fa-eye"></i> Details
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
    
    <div class="mt-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
        <?php if ($status === 'inactive'): ?>
            <a href="pending_deletions.php" class="btn btn-warning">
                <i class="fas fa-exclamation-triangle"></i> Review Pending Deletions
            </a>
        <?php endif; ?>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
