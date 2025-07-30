<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';
include 'templates/header.php';

// Get all sources for dropdown
$sources = $db->query("SELECT id, name FROM account_sources ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$selected_source = isset($_GET['source_id']) ? intval($_GET['source_id']) : 0;
$users = [];

if ($selected_source) {
    // Fetch correlated accounts (user_accounts joined to users)
    $stmt1 = $db->prepare("
        SELECT ua.account_id, ua.username, ua.email, ua.created_at, ua.updated_at, u.first_name, u.last_name, u.employee_id, u.status, 'correlated' as account_type
        FROM user_accounts ua
        LEFT JOIN users u ON ua.user_id = u.id
        WHERE ua.source_id = ?
    ");
    $stmt1->execute([$selected_source]);
    $correlated = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // Fetch uncorrelated accounts
    $stmt2 = $db->prepare("
        SELECT account_id, username, email, created_at, role_account_id, NULL as updated_at, NULL as first_name, NULL as last_name, NULL as employee_id, NULL as status, 'uncorrelated' as account_type
        FROM uncorrelated_accounts
        WHERE source_id = ?
    ");
    $stmt2->execute([$selected_source]);
    $uncorrelated = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // For uncorrelated accounts, map username from role_accounts.name if role_account_id is present
    foreach ($uncorrelated as &$ua) {
        if (!empty($ua['role_account_id'])) {
            $roleStmt = $db->prepare("SELECT name FROM role_accounts WHERE id = ? LIMIT 1");
            $roleStmt->execute([$ua['role_account_id']]);
            $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
            if ($role && !empty($role['name'])) {
                $ua['username'] = $role['name'];
            }
        }
    }
    unset($ua);

    // Merge and determine columns dynamically
    $accounts = array_merge($correlated, $uncorrelated);

    // If SSHRData source exists, build a lookup of email => username from it
    $sshrUsernames = [];
    $sshrSourceId = null;
    foreach ($sources as $src) {
        if (strtolower($src['name']) === 'sshrdata') {
            $sshrSourceId = $src['id'];
            break;
        }
    }
    if ($sshrSourceId) {
        $sshrStmt = $db->prepare("SELECT email, username FROM user_accounts WHERE source_id = ?");
        $sshrStmt->execute([$sshrSourceId]);
        foreach ($sshrStmt->fetchAll(PDO::FETCH_ASSOC) as $sshrRow) {
            if (!empty($sshrRow['email']) && !empty($sshrRow['username'])) {
                $sshrUsernames[strtolower($sshrRow['email'])] = $sshrRow['username'];
            }
        }
    }
    // For all accounts, if username is missing, fill from SSHRData by email
    if (!empty($sshrUsernames)) {
        foreach ($accounts as &$acc) {
            if (empty($acc['username']) && !empty($acc['email'])) {
                $emailKey = strtolower($acc['email']);
                if (isset($sshrUsernames[$emailKey])) {
                    $acc['username'] = $sshrUsernames[$emailKey];
                }
            }
        }
        unset($acc);
    }

    // Determine all columns present
    $allColumns = [];
    foreach ($accounts as $row) {
        foreach ($row as $col => $val) {
            $allColumns[$col] = true;
        }
    }
    // User-friendly headers
    $columnHeaders = [
        'account_id' => 'Account ID',
        'username' => 'Username',
        'email' => 'Email',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'status' => 'Status',
        'created_at' => 'Created',
        'updated_at' => 'Last Updated',
    ];
    // Only show columns that exist in the data
    $displayColumns = array_intersect_key($columnHeaders, $allColumns);
}

// CSV Export
if (isset($_GET['export']) && $selected_source) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="source_report_' . $selected_source . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['First Name', 'Last Name', 'Email', 'Employee ID', 'Status']);
    foreach ($users as $user) {
        fputcsv($out, [
            $user['first_name'],
            $user['last_name'],
            $user['email'],
            $user['employee_id'],
            $user['status']
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Source Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h2>Source Report</h2>
    <form method="get" class="mb-4">
        <label for="source_id" class="form-label">Select Source:</label>
        <select name="source_id" id="source_id" class="form-select" style="width:auto;display:inline-block;" required>
            <option value="">-- Choose Source --</option>
            <?php foreach ($sources as $src): ?>
                <option value="<?= $src['id'] ?>" <?= $selected_source == $src['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($src['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary ms-2">Generate</button>
        <?php if ($selected_source): ?>
            <a href="reports.php?source_id=<?= $selected_source ?>&export=1" class="btn btn-success ms-2">Export CSV</a>
        <?php endif; ?>
    </form>

    <?php if ($selected_source): ?>
        <h5><?= isset($accounts) ? count($accounts) : 0 ?> account(s) found for this source.</h5>
        <?php if (!empty($accounts)): ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <?php foreach ($displayColumns as $col => $header): ?>
                        <th><?= htmlspecialchars($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $row): ?>
                    <tr>
                        <?php foreach ($displayColumns as $col => $header): ?>
                            <td><?= htmlspecialchars($row[$col] ?? '') ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="alert alert-warning">No accounts found for this source.</div>
        <?php endif; ?>
    <?php elseif ($_GET): ?>
        <div class="alert alert-warning">No source selected or no users found.</div>
    <?php endif; ?>
</body>
</html>
