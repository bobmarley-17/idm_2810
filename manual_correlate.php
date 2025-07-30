<?php
require_once 'config/database.php';

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Invalid account ID.");
}

// Fetch account details
$stmt = $db->prepare("SELECT * FROM uncorrelated_accounts WHERE id = ?");
$stmt->execute([$id]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    die("Account not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $role_account_id = isset($_POST['role_account_id']) ? intval($_POST['role_account_id']) : 0;
    if ($user_id > 0) {
        // Insert into user_accounts
        $matched_by = json_encode(['manual' => true]);
        $insert = $db->prepare("INSERT INTO user_accounts (user_id, source_id, account_id, username, email, additional_data, matched_by) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), username=VALUES(username), email=VALUES(email), additional_data=VALUES(additional_data), matched_by=VALUES(matched_by), updated_at=NOW()");
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

        echo "<div class='alert alert-success'>Account manually correlated and removed from uncorrelated list.</div>";
        echo "<a href='index.php' class='btn btn-primary'>Back to Dashboard</a>";
        exit;
    } elseif ($role_account_id > 0) {
        // Assign to role account only
        $update = $db->prepare("UPDATE uncorrelated_accounts SET role_account_id = ? WHERE id = ?");
        $update->execute([$role_account_id, $id]);
        echo "<div class='alert alert-success'>Account assigned to role account.</div>";
        echo "<a href='index.php' class='btn btn-primary'>Back to Dashboard</a>";
        exit;
    } else {
        echo "<div class='alert alert-danger'>Please select a valid user or role account.</div>";
    }
}

// List users for manual correlation
$users = $db->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
// List role accounts
$roleAccounts = $db->query("SELECT id, name FROM role_accounts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Manual Correlation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <h2>Manual Correlation for Account ID <?= htmlspecialchars($account['account_id']) ?></h2>
    <form method="post" class="mb-3">
        <div class="mb-3">
            <label for="user_id" class="form-label">Select User:</label>
            <select name="user_id" id="user_id" class="form-select">
                <option value="">-- Select User --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>">
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="role_account_id" class="form-label">Or assign to Role Account:</label>
            <select name="role_account_id" id="role_account_id" class="form-select">
                <option value="">-- Select Role Account --</option>
                <?php foreach ($roleAccounts as $role): ?>
                    <option value="<?= $role['id'] ?>">
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Submit</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
    </form>
</body>
</html>
