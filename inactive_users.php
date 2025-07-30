<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/CorrelationEngine.php';
include 'templates/header.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);
$correlationEngine = new CorrelationEngine($db);

$status = isset($_GET['status']) ? $_GET['status'] : 'inactive';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Prepare the query with search functionality
// Get source_id from URL if provided
$source_id = isset($_GET['source_id']) ? (int)$_GET['source_id'] : null;

// Get source name if source_id is provided
$source_name = '';
if ($source_id) {
    $sourceStmt = $db->prepare("SELECT name FROM account_sources WHERE id = ?");
    $sourceStmt->execute([$source_id]);
    $source = $sourceStmt->fetch(PDO::FETCH_ASSOC);
    $source_name = $source ? $source['name'] : '';
}

$query = "SELECT u.*, 
    GROUP_CONCAT(DISTINCT s.name) as source_names,
    GROUP_CONCAT(DISTINCT ua.status) as account_statuses
    FROM users u 
    LEFT JOIN user_accounts ua ON u.id = ua.user_id 
    LEFT JOIN account_sources s ON ua.source_id = s.id 
    WHERE u.status = :status" .
    ($source_id ? " AND EXISTS (SELECT 1 FROM user_accounts ua2 WHERE ua2.user_id = u.id AND ua2.source_id = :source_id)" : "");

if ($searchTerm) {
    $query .= " AND (u.email LIKE :search OR u.employee_id LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
}

$query .= " GROUP BY u.id ORDER BY u.updated_at DESC";

$stmt = $db->prepare($query);
$stmt->bindValue(':status', $status);
if ($searchTerm) {
    $stmt->bindValue(':search', "%$searchTerm%");
}
if ($source_id) {
    $stmt->bindValue(':source_id', $source_id);
}
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Inactive/Deleted Users</h2>
            <?php if ($source_name): ?>
            <p class="text-muted">Filtered by source: <?= htmlspecialchars($source_name) ?></p>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($source_id): ?>
            <a href="sources.php?source_id=<?= $source_id ?>" class="btn btn-secondary me-2">Back to Source</a>
            <?php endif; ?>
            <a href="users.php" class="btn btn-primary">View Active Users</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status === 'inactive' ? 'active' : ''; ?>" 
                               href="?status=inactive">Inactive Users</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status === 'deleted' ? 'active' : ''; ?>" 
                               href="?status=deleted">Deleted Users</a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <form class="d-flex" method="GET">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                        <input type="text" name="search" class="form-control" placeholder="Search users..." 
                               value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit" class="btn btn-outline-secondary ms-2">Search</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Sources</th>
                            <th>Account Statuses</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['source_names'] ?? 'None'); ?></td>
                            <td><?php echo htmlspecialchars($user['account_statuses'] ?? 'None'); ?></td>
                            <td><?php echo htmlspecialchars($user['updated_at']); ?></td>
                            <td>
                                <a href="user_detail.php?id=<?php echo $user['id']; ?>" 
                                   class="btn btn-sm btn-info">View Details</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No <?php echo $status; ?> users found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
