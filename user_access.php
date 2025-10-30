<?php
// user_access.php

require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';

// Check for user ID
if (empty($_GET['id'])) {
    header('Location: users.php');
    exit();
}
$userId = $_GET['id'];

// Fetch user data from the database
$stmt = $db->prepare("SELECT id, username, first_name, last_name, email, password_hash FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['message'] = 'User not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: users.php');
    exit();
}

$pageTitle = "Access Control: " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
require_once 'templates/header.php';
?>

<div class="container mt-4">

    <!-- Display any success/error messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Display the one-time password if it was just generated -->
    <?php if (isset($_SESSION['one_time_password'])): ?>
        <div class="alert alert-warning">
            <strong>One-Time Password:</strong> 
            <code style="font-size: 1.2rem;"><?= htmlspecialchars($_SESSION['one_time_password']) ?></code>
            <p class="mb-0 mt-2">Please copy this password and provide it to the user. It will only be shown once.</p>
        </div>
        <?php unset($_SESSION['one_time_password']); ?>
    <?php endif; ?>

    <!-- Title and back button -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Admin Access Control</h2>
        <a href="user_detail.php?id=<?= $user['id'] ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to User Details
        </a>
    </div>

    <!-- Main Content Card -->
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span>Managing access for: <strong><?= htmlspecialchars($user['username']) ?></strong></span>
            <span>(User ID: <?= $user['id'] ?>)</span>
        </div>
        <div class="card-body">
            <h5 class="card-title">Current Status</h5>
            <?php if ($user['password_hash'] !== null): ?>
                <!-- User HAS access -->
                <p class="text-success"><i class="fas fa-check-circle"></i> Login access is <strong>ENABLED</strong>.</p>
            <?php else: ?>
                <!-- User does NOT have access -->
                <p class="text-muted"><i class="fas fa-times-circle"></i> Login access is <strong>DISABLED</strong>.</p>
            <?php endif; ?>
            
            <hr>
            
            <h5 class="card-title mt-4">Actions</h5>
            
            <?php if ($user['password_hash'] !== null): ?>
                <!-- Actions for users who HAVE access -->
                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <i class="fas fa-key"></i> Change Password
                </button>
                <form action="user_access_action.php" method="POST" class="d-inline-block ms-2" onsubmit="return confirm('Are you sure you want to revoke access for this user?');">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="revoke">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-user-slash"></i> Revoke Access
                    </button>
                </form>
            <?php else: ?>
                <!-- Action for users who DO NOT have access -->
                <form action="user_access_action.php" method="POST" class="d-inline-block">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="grant">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-check"></i> Grant Access & Generate Password
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Password for <?= htmlspecialchars($user['username']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="user_access_action.php" method="POST">
          <?php echo csrf_input(); ?>
          <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
          <input type="hidden" name="action" value="change_password">
          <div class="modal-body">
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save New Password</button>
          </div>
      </form>
    </div>
  </div>
</div>

<?php require_once 'templates/footer.php'; ?>
