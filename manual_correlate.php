<?php
require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/LogHelper.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    $_SESSION['message'] = "Invalid account ID.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php");
    exit;
}

// Fetch account details
$stmt = $db->prepare("SELECT ua.*, s.name as source_name FROM uncorrelated_accounts ua LEFT JOIN account_sources s ON ua.source_id = s.id WHERE ua.id = ?");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    $_SESSION['message'] = "Account not found.";
    $_SESSION['message_type'] = "danger";
    header("Location: index.php");
    exit;
}

$success = false;
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $role_account_id = intval($_POST['role_account_id'] ?? 0);
    
    if ($user_id > 0) {
        // Get user details for logging
        $userStmt = $db->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $userDetails = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        // Insert into user_accounts
        $matched_by = json_encode(['manual' => true, 'correlated_by' => $_SESSION['username'] ?? 'unknown']);
        $insert = $db->prepare("
            INSERT INTO user_accounts (user_id, source_id, account_id, username, email, additional_data, matched_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
                user_id=VALUES(user_id), 
                username=VALUES(username), 
                email=VALUES(email), 
                additional_data=VALUES(additional_data), 
                matched_by=VALUES(matched_by), 
                updated_at=NOW()
        ");
        $insert->execute([
            $user_id,
            $account['source_id'],
            $account['account_id'],
            $account['username'],
            $account['email'],
            $account['account_data'],
            $matched_by
        ]);

        // Remove from uncorrelated_accounts
        $delete = $db->prepare("DELETE FROM uncorrelated_accounts WHERE id = ?");
        $delete->execute([$id]);

        // LOG: Manual correlation to user
        LogHelper::logUserAction('Manual correlation performed', $user_id, [
            'uncorrelated_account_id' => $id,
            'account_id' => $account['account_id'],
            'account_email' => $account['email'],
            'account_username' => $account['username'],
            'source_id' => $account['source_id'],
            'source_name' => $account['source_name'] ?? 'Unknown',
            'correlated_to_user' => $userDetails['email'] ?? 'Unknown',
            'correlated_by' => $_SESSION['username'] ?? 'unknown'
        ]);

        LogHelper::logDatabaseChange('user_accounts', 'INSERT', null, [
            'user_id' => $user_id,
            'account_id' => $account['account_id'],
            'source_id' => $account['source_id'],
            'correlation_type' => 'manual'
        ]);

        $_SESSION['message'] = "Account successfully correlated to " . htmlspecialchars($userDetails['first_name'] . ' ' . $userDetails['last_name']);
        $_SESSION['message_type'] = "success";
        header("Location: index.php");
        exit;
        
    } elseif ($role_account_id > 0) {
        // Get role account details for logging
        $roleStmt = $db->prepare("SELECT name FROM role_accounts WHERE id = ?");
        $roleStmt->execute([$role_account_id]);
        $roleName = $roleStmt->fetchColumn();
        
        // Assign to role account
        $update = $db->prepare("UPDATE uncorrelated_accounts SET role_account_id = ? WHERE id = ?");
        $update->execute([$role_account_id, $id]);

        // LOG: Role assignment
        LogHelper::logConfig('Account assigned to role', [
            'uncorrelated_account_id' => $id,
            'account_id' => $account['account_id'],
            'account_email' => $account['email'],
            'source_id' => $account['source_id'],
            'source_name' => $account['source_name'] ?? 'Unknown',
            'role_account_id' => $role_account_id,
            'role_name' => $roleName,
            'assigned_by' => $_SESSION['username'] ?? 'unknown'
        ]);

        LogHelper::logDatabaseChange('uncorrelated_accounts', 'UPDATE', $id, [
            'role_account_id' => $role_account_id,
            'role_name' => $roleName
        ]);

        $_SESSION['message'] = "Account assigned to role: " . htmlspecialchars($roleName);
        $_SESSION['message_type'] = "success";
        header("Location: index.php");
        exit;
        
    } else {
        $error = "Please select a valid user or role account.";
    }
}

// List users for manual correlation
$users = $db->query("SELECT id, first_name, last_name, email FROM users WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

// List role accounts
$roleAccounts = $db->query("SELECT id, name, description FROM role_accounts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

include 'templates/header.php';
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Manual Correlation</li>
        </ol>
    </nav>

    <h2><i class="fas fa-link me-2"></i>Manual Correlation</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Account Details Card -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-info-circle me-2"></i>Account Details
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th style="width: 140px;">Account ID:</th>
                            <td><code><?= htmlspecialchars($account['account_id']) ?></code></td>
                        </tr>
                        <tr>
                            <th>Username:</th>
                            <td><?= htmlspecialchars($account['username'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?= htmlspecialchars($account['email'] ?? 'N/A') ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th style="width: 140px;">Source:</th>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($account['source_name'] ?? 'Unknown') ?></span></td>
                        </tr>
                        <tr>
                            <th>Source ID:</th>
                            <td><?= $account['source_id'] ?></td>
                        </tr>
                        <tr>
                            <th>Created:</th>
                            <td><?= date('M j, Y H:i', strtotime($account['created_at'])) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($account['account_data'])): ?>
                <details class="mt-2">
                    <summary class="text-primary" style="cursor: pointer;">
                        <i class="fas fa-code me-1"></i>View Raw Account Data
                    </summary>
                    <pre class="mt-2 p-3 bg-light rounded" style="font-size: 0.85rem; max-height: 200px; overflow: auto;"><?= htmlspecialchars(json_encode(json_decode($account['account_data']), JSON_PRETTY_PRINT)) ?></pre>
                </details>
            <?php endif; ?>
        </div>
    </div>

    <!-- Correlation Form -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-user-check me-2"></i>Select Correlation Target
        </div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <!-- Correlate to User -->
                    <div class="col-md-6">
                        <div class="card h-100 border-primary">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-user me-2"></i>Correlate to User
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">Link this account to an existing user in the system.</p>
                                <label for="user_id" class="form-label">Select User:</label>
                                <select name="user_id" id="user_id" class="form-select">
                                    <option value="">-- Select User --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> 
                                            (<?= htmlspecialchars($user['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Assign to Role Account -->
                    <div class="col-md-6">
                        <div class="card h-100 border-secondary">
                            <div class="card-header bg-secondary text-white">
                                <i class="fas fa-user-tag me-2"></i>Assign to Role Account
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">Assign this account to a service/role account (e.g., system accounts).</p>
                                <label for="role_account_id" class="form-label">Select Role Account:</label>
                                <select name="role_account_id" id="role_account_id" class="form-select">
                                    <option value="">-- Select Role Account --</option>
                                    <?php foreach ($roleAccounts as $role): ?>
                                        <option value="<?= $role['id'] ?>">
                                            <?= htmlspecialchars($role['name']) ?>
                                            <?php if ($role['description']): ?>
                                                - <?= htmlspecialchars(substr($role['description'], 0, 50)) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i> Submit Correlation
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Clear the other dropdown when one is selected
document.getElementById('user_id').addEventListener('change', function() {
    if (this.value) {
        document.getElementById('role_account_id').value = '';
    }
});

document.getElementById('role_account_id').addEventListener('change', function() {
    if (this.value) {
        document.getElementById('user_id').value = '';
    }
});
</script>

<?php include 'templates/footer.php'; ?>
