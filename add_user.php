<?php
require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/UserManager.php';
require_once 'lib/LogHelper.php';

$userManager = new UserManager($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_type = trim($_POST['id_type'] ?? '');
    $employee_id = trim($_POST['employee_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $errors = [];

    // Basic validation
    if (!$id_type) $errors[] = "ID Type is required.";
    if (!$employee_id) $errors[] = "ID Value is required.";
    if (!$first_name) $errors[] = "First Name is required.";
    if (!$last_name) $errors[] = "Last Name is required.";
    if (!$email) $errors[] = "Email is required.";

    if (empty($errors)) {
        try {
            // Create user (employee_id stores the value, id_type tells us what type)
            $userId = $userManager->createUser([
                'employee_id' => $employee_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'status' => 'active'
            ]);

            // LOG: User created
            LogHelper::logUserAction('User created', $userId, [
                'id_type' => $id_type,
                'employee_id' => $employee_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'created_by' => $_SESSION['username'] ?? 'unknown'
            ]);

            LogHelper::logDatabaseChange('users', 'INSERT', $userId, [
                'id_type' => $id_type,
                'employee_id' => $employee_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'status' => 'active'
            ]);

            $_SESSION['message'] = "User added successfully.";
            $_SESSION['message_type'] = "success";

            header("Location: users.php");
            exit;
            
        } catch (Exception $e) {
            LogHelper::logError('User creation failed', [
                'id_type' => $id_type,
                'employee_id' => $employee_id,
                'email' => $email,
                'error' => $e->getMessage(),
                'attempted_by' => $_SESSION['username'] ?? 'unknown'
            ]);
            
            $errors[] = "Error adding user: " . $e->getMessage();
        }
    }
}

include 'templates/header.php';
?>

<div class="container mt-4">
    <h2>Add New User</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="add_user.php" method="POST">
                
                <!-- ID Type Dropdown + ID Value -->
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="id_type" class="form-label">ID Type <span class="text-danger">*</span></label>
                        <select id="id_type" name="id_type" class="form-select" required>
                            <option value="">-- Select --</option>
                            <option value="employee_id" <?= (($_POST['id_type'] ?? '') === 'employee_id') ? 'selected' : '' ?>>Employee ID</option>
                            <option value="rt" <?= (($_POST['id_type'] ?? '') === 'rt') ? 'selected' : '' ?>>RT</option>
                            <option value="cmr" <?= (($_POST['id_type'] ?? '') === 'cmr') ? 'selected' : '' ?>>CMR</option>
                        </select>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label for="employee_id" class="form-label">ID Value <span class="text-danger">*</span></label>
                        <input type="text" id="employee_id" name="employee_id" class="form-control" required 
                               value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>">
                    </div>
                </div>

                <!-- First Name + Last Name -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required 
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required 
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    </div>
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <!-- Buttons -->
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus me-1"></i> Add User
                </button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>
