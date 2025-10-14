<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/CorrelationEngine.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);
$correlationEngine = new CorrelationEngine($db);


// Count distinct users in defunct_users with pending status
$pendingUsersStmt = $db->query("
    SELECT COUNT(DISTINCT user_id) 
    FROM defunct_users 
    WHERE status = 'pending'
");
$pendingCount = $pendingUsersStmt ? (int)$pendingUsersStmt->fetchColumn() : 0;
?>
<?php
// Get statistics
$totalActiveUsers = $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$totalInactiveUsers = $db->query("SELECT COUNT(*) FROM users WHERE status != 'active'")->fetchColumn();
$totalSources = $db->query("SELECT COUNT(*) FROM account_sources")->fetchColumn();
$totalRules = $db->query("SELECT COUNT(*) FROM correlation_rules")->fetchColumn();
$recentUsers = $db->query("
    SELECT u.*
    FROM users u
    LEFT JOIN defunct_users d ON d.email = u.email OR d.employee_id = u.employee_id
    WHERE u.status = 'active' AND (d.status IS NULL OR d.status != 'deleted')
    ORDER BY u.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);


// Get last sync status and total users in SSHRData
$sshrUserCountStmt = $db->query("
    SELECT COUNT(*) as total 
    FROM user_accounts ua 
    JOIN account_sources s ON ua.source_id = s.id 
    WHERE LOWER(s.name) = 'sshrdata'
");
$totalUsers = $sshrUserCountStmt ? (int)$sshrUserCountStmt->fetchColumn() : 0;

$lastSync = $db->query("
    SELECT s.name, s.last_sync,
        (
            SELECT COUNT(*) FROM user_accounts ua WHERE ua.source_id = s.id
        ) + (
            SELECT COUNT(*) FROM uncorrelated_accounts uca WHERE uca.source_id = s.id AND uca.role_account_id IS NOT NULL
        ) AS accounts
    FROM account_sources s
    ORDER BY s.last_sync DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Dashboard";
include 'templates/header.php';
?>

<div class="container-fluid px-4 py-4">
    <h2>Identity Management Dashboard</h2>
    
    <div class="row mt-4">
        <!-- Stats Cards -->
        <div class="col">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title">Users</h5>
                    <p class="card-text display-4"><?php echo $totalActiveUsers; ?></p>
                    <div class="d-flex flex-column">
                        <a href="users.php" class="text-white">View Active Users</a>
                        <?php if ($totalInactiveUsers > 0): ?>
                        <a href="inactive_users.php" class="text-white">View <?php echo $totalInactiveUsers; ?> inactive/deleted</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title">Data Sources</h5>
                    <p class="card-text display-5"><?= $totalSources ?></p>
                    <a href="sources.php" class="text-white">Manage</a>
                </div>
            </div>
        </div>
        
        <div class="col">
            <div class="card text-white bg-info h-100">
                <div class="card-body">
                    <h5 class="card-title">Correlation Rules</h5>
                    <p class="card-text display-5"><?= $totalRules ?></p>
                    <a href="sources.php" class="text-white">Configure</a>
                </div>
            </div>
        </div>
        
        <div class="col">
            <div class="card text-white bg-secondary h-100">
                <div class="card-body">
                    <h5 class="card-title">Reports</h5>                    
                    <a href="reports.php" class="text-white">Generate</a>
                </div>
            </div>
        </div>


        <!-- Action Required Card -->
        <div class="col">
            <?php
            $actionCardBg = $pendingCount > 0 ? 'bg-danger text-white' : 'bg-white text-danger border-danger';
            $actionCardText = $pendingCount > 0 ? 'Users Pending Deletion' : 'No Action Required';
            ?>
            <div class="card h-100 <?= $actionCardBg ?> border border-2 border-danger">
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <h5 class="card-title">Action Required</h5>
                    <p class="card-text display-6 fw-bold mb-1">
                        <?= $pendingCount ?>
                    </p>
                    <span class="mb-2 small">
                        <?= $actionCardText ?>
                    </span>
                    <a href="pending_deletions.php" class="btn btn-outline-light btn-sm <?= $pendingCount > 0 ? '' : 'text-danger border-danger' ?>">Review</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card text-white bg-warning h-100">
                <div class="card-body">
                    <h5 class="card-title">Sync Status</h5>
                    <p class="card-text">
                        <?php if ($lastSync && $lastSync[0]['last_sync']): ?>
                            Last: <?= date('M j, H:i', strtotime($lastSync[0]['last_sync'])) ?>
                        <?php else: ?>
                            Never synced
                        <?php endif; ?>
                    </p>
                    <a href="sync.php" class="text-white">Run Sync</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <!-- Recent Users -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Recently Added Users</span>
                    <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td>
                                    <a href="user_detail.php?id=<?= $user['id'] ?>">
				<?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
			    </a>
			</td>
			<td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                                <td><?= date('M j', strtotime($user['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Last Sync Status -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Recent Sync Activity</span>
                    <a href="sync.php" class="btn btn-sm btn-outline-primary">Sync Now</a>
                </div>
                <div class="card-body">
                    <?php if ($lastSync): ?>
                        <div class="list-group">
                            <?php foreach ($lastSync as $source): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($source['name']) ?></strong>
                                    <span class="badge bg-primary rounded-pill">
                                        <?php if ($source['name'] === 'SSHRData'): ?>
                                                <?= $totalUsers ?> accounts
                                        <?php else: ?>
                                                <?= $source['accounts'] ?> accounts
                                        <?php endif; ?>

                                    </span>
                                </div>
                                <small class="text-muted">
                                    <?= $source['last_sync'] ? 
                                        'Last sync: ' . date('M j, H:i', strtotime($source['last_sync'])) : 
                                        'Never synced' ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-sync fa-2x mb-2"></i>
                            <p>No sync activity yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    Quick Actions
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <a href="sources.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-server me-2"></i>Manage Sources
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="sources.php?source_id=1" class="btn btn-outline-success w-100">
                                <i class="fas fa-sliders-h me-2"></i>Configure Rules
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="sync.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-sync me-2"></i>Run Sync
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="#" class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-user-plus me-2"></i>Add User
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Uncorrelated Accounts Section -->
    <div class="row mt-4">
        <div class="col-12 mb-2">
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addRoleModal">Add Role Account</button>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    Uncorrelated Accounts
                </div>
                <div class="card-body">
                    <?php
                    // Fetch role accounts
                    $roleStmt = $db->query("SELECT id, name, description FROM role_accounts ORDER BY name");
                    $roleAccounts = $roleStmt ? $roleStmt->fetchAll(PDO::FETCH_ASSOC) : [];

                    $uncorrStmt = $db->query("
                        SELECT ua.id, ua.source_id, s.name AS source_name, ua.account_id, ua.username, ua.email, ua.created_at, ra.name AS role_name
                        FROM uncorrelated_accounts ua
                        LEFT JOIN account_sources s ON ua.source_id = s.id
                        LEFT JOIN role_accounts ra ON ua.role_account_id = ra.id
                        WHERE ua.role_account_id IS NULL
                        ORDER BY ua.created_at DESC
                        LIMIT 20
                    ");
                    if ($uncorrStmt === false) {
                        $errorInfo = $db->errorInfo();
                        echo '<div class="alert alert-danger">SQL Error: ' . htmlspecialchars($errorInfo[2] ?? '') . '</div>';
                        $uncorrAccounts = [];
                    } else {
                        $uncorrAccounts = $uncorrStmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    if ($uncorrAccounts):
                    ?>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Source</th>
                                <th>Account ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role Account</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uncorrAccounts as $acc): ?>
                            <tr>
                                <td><?= $acc['id'] ?></td>
                                <td><?= htmlspecialchars($acc['source_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($acc['account_id'] ?? '') ?></td>
                                <td><?= htmlspecialchars($acc['username'] ?? '') ?></td>
                                <td><?= htmlspecialchars($acc['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($acc['role_name'] ?? '') ?></td>
                                <td><?= date('M j, H:i', strtotime($acc['created_at'])) ?></td>
                                <td>
                                    <a href="manual_correlate.php?id=<?= $acc['id'] ?>" class="btn btn-sm btn-primary">Correlate</a>
                                    <form method="post" action="assign_role.php" style="display:inline-block">
                                        <input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                                        <select name="role_account_id" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                            <option value="">Assign Role</option>
                                            <?php foreach ($roleAccounts as $role): ?>
                                                <option value="<?= $role['id'] ?>" <?= ($acc['role_name'] == $role['name']) ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p class="text-muted">No uncorrelated accounts found.</p>
                    <?php endif; ?>

<!-- Add Role Account Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="create_role_account.php">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addRoleModalLabel">Add Role Account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="role_name" class="form-label">Role Name</label>
            <input type="text" class="form-control" id="role_name" name="role_name" required>
          </div>
          <div class="mb-3">
            <label for="role_desc" class="form-label">Description</label>
            <textarea class="form-control" id="role_desc" name="role_desc" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Role Account</button>
        </div>
      </div>
    </form>
  </div>
</div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal (unchanged) -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="add_user.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="employee_id" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
