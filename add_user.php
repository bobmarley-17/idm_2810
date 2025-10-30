<?php
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/UserManager.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
$userManager = new UserManager($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = trim($_POST['employee_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $errors = [];

    // Basic validation
    if (!$employee_id) $errors[] = "Employee ID is required.";
    if (!$first_name) $errors[] = "First Name is required.";
    if (!$last_name) $errors[] = "Last Name is required.";
    if (!$email) $errors[] = "Email is required.";

    if (empty($errors)) {
        try {
            // Create user
            $userId = $userManager->createUser([
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
            $errors[] = "Error adding user: " . $e->getMessage();
        }
    }
}
?>

<?php include 'templates/header.php'; ?>

<div class="container mt-4">
    <h2>Add New User</h2>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="add_user.php" method="POST">
        <div class="mb-3">
            <label for="employee_id" class="form-label">Employee ID</label>
            <input type="text" id="employee_id" name="employee_id" class="form-control" required value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="first_name" class="form-label">First Name</label>
            <input type="text" id="first_name" name="first_name" class="form-control" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="last_name" class="form-label">Last Name</label>
            <input type="text" id="last_name" name="last_name" class="form-control" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">Add User</button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include 'templates/footer.php'; ?>

