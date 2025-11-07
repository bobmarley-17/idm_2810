<?php
require_once 'config/database.php';
require_once 'lib/UserManager.php';

// Fetch sources for dropdown early since needed for export filename
$sources = $db->query("SELECT id, name FROM account_sources ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$selected_source = isset($_GET['source_id']) ? intval($_GET['source_id']) : 0;
$status_filter = $_GET['status_filter'] ?? '';
$sortBy = $_GET['sortBy'] ?? 'account_id';
$order = strtolower($_GET['order'] ?? 'asc');
$allowedSortColumns = ['account_id', 'username', 'email', 'first_name', 'last_name', 'status', 'created', 'created_at', 'updated', 'updated_at'];
if (!in_array($sortBy, $allowedSortColumns)) {
    $sortBy = 'account_id';
}
if ($order !== 'desc') {
    $order = 'asc';
}

// CSV Export block - must be before any output
if (isset($_GET['export']) && $selected_source) {
    // --- PART 1 & 2 (These are unchanged and correct) ---
    // The code for reading the backend CSV and fetching DB data is correct and remains here.
    $stmt_config = $db->prepare("SELECT config FROM account_sources WHERE id = ? LIMIT 1");
    $stmt_config->execute([$selected_source]);
    $source_config_row = $stmt_config->fetch(PDO::FETCH_ASSOC);
    $original_data_by_email = [];
    $original_csv_headers = [];
    if ($source_config_row && !empty($source_config_row['config'])) {
        $configArray = json_decode($source_config_row['config'], true);
        if (isset($configArray['file_path']) && file_exists($configArray['file_path'])) {
            $filePath = $configArray['file_path'];
            if (($handle = fopen($filePath, "r")) !== FALSE) {
                $headers = fgetcsv($handle, 1000, ",");
                if ($headers !== FALSE) {
                    $original_csv_headers = $headers;
                    $email_header_name = null;
                    foreach ($headers as $header) { if (strtolower(trim($header)) === 'email') { $email_header_name = $header; break; } }
                    if ($email_header_name) {
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            if (count($headers) === count($data)) {
                                $row_data = array_combine($headers, $data);
                                if (!empty($row_data[$email_header_name])) {
                                    $email = strtolower(trim($row_data[$email_header_name]));
                                    $original_data_by_email[$email] = $row_data;
                                }
                            }
                        }
                    }
                    fclose($handle);
                }
            }
        }
    }
    $sourceName = "export";
    foreach ($sources as $src) { if ($src['id'] == $selected_source) { $sourceName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $src['name']); break; } }
    $sql = "SELECT ua.account_id, ua.username, ua.email, ua.created_at, ua.updated_at, u.first_name, u.last_name, u.employee_id, u.status, 'correlated' AS account_type FROM user_accounts ua LEFT JOIN users u ON ua.user_id = u.id WHERE ua.source_id = ?";
    if ($status_filter === 'active') { $sql .= " AND u.status = 'active' "; } elseif ($status_filter === 'inactive') { $sql .= " AND u.status != 'active' "; }
    $dbSortCol = $sortBy; if ($dbSortCol === 'created') $dbSortCol = 'ua.created_at'; if ($dbSortCol === 'updated') $dbSortCol = 'ua.updated_at';
    $sql .= " ORDER BY $dbSortCol $order ";
    $stmt = $db->prepare($sql); $stmt->execute([$selected_source]); $correlated = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt2 = $db->prepare("SELECT account_id, username, email, created_at, role_account_id, NULL AS updated_at, NULL AS first_name, NULL AS last_name, NULL AS employee_id, NULL AS status, 'uncorrelated' AS account_type FROM uncorrelated_accounts WHERE source_id = ?");
    $stmt2->execute([$selected_source]); $uncorrelated = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($uncorrelated as &$ua) { if (!empty($ua['role_account_id'])) { $roleStmt = $db->prepare("SELECT name FROM role_accounts WHERE id = ? LIMIT 1"); $roleStmt->execute([$ua['role_account_id']]); $role = $roleStmt->fetch(PDO::FETCH_ASSOC); if ($role && !empty($role['name'])) { $ua['username'] = $role['name']; } } } unset($ua);
    $accounts = array_merge($correlated, $uncorrelated);


    // --- PART 3: BUILD AND STREAM THE CUSTOMIZED CSV ---
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $sourceName . '_custom_report.csv"');
    header('Pragma: no-cache'); header('Expires: 0');
    $out = fopen('php://output', 'w');

    $final_headers = $original_csv_headers;
    $live_headers_to_add = ['Status', 'Created', 'Last Updated'];
    $final_headers = array_merge($final_headers, $live_headers_to_add);
    fputcsv($out, $final_headers);

    foreach ($accounts as $user) {
        $current_email = strtolower(trim($user['email'] ?? ''));
        $matched_original_data = $original_data_by_email[$current_email] ?? null;

        $final_row = [];

        foreach ($original_csv_headers as $header) {
            $value = strip_tags($matched_original_data[$header] ?? '');

            // --- THIS IS THE NEW LOGIC ---
            // If the current column is 'Host name', reverse the flattening by replacing semicolons with newlines.
            if ($header === 'Host name') {
                $value = str_replace(';', "\n", $value);
            }
            // --- END OF NEW LOGIC ---

            $final_row[] = $value;
        }
        
        $final_row[] = strip_tags($user['status'] ?? '');
        $final_row[] = strip_tags($user['created_at'] ?? '');
        $final_row[] = strip_tags($user['updated_at'] ?? '');
        
        fputcsv($out, $final_row);
    }
    
    fclose($out);
    exit;
}
// Normal page rendering below

include 'templates/header.php';

$users = [];
if ($selected_source) {
    // Base query for correlated accounts with optional status filter and sorting
    $sql = "
        SELECT ua.account_id, ua.username, ua.email, ua.created_at, ua.updated_at,
               u.first_name, u.last_name, u.employee_id, u.status, 'correlated' AS account_type
        FROM user_accounts ua
        LEFT JOIN users u ON ua.user_id = u.id
        WHERE ua.source_id = ?
    ";
    if ($status_filter === 'active') {
        $sql .= " AND u.status = 'active' ";
    } elseif ($status_filter === 'inactive') {
        $sql .= " AND u.status != 'active' ";
    }
    // Map sort columns
    $dbSortCol = $sortBy;
    if ($dbSortCol === 'created') $dbSortCol = 'ua.created_at';
    if ($dbSortCol === 'updated') $dbSortCol = 'ua.updated_at';
    $sql .= " ORDER BY $dbSortCol $order ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$selected_source]);
    $correlated = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Uncorrelated accounts
    $stmt2 = $db->prepare("
        SELECT account_id, username, email, created_at, role_account_id,
               NULL AS updated_at, NULL AS first_name, NULL AS last_name,
               NULL AS employee_id, NULL AS status, 'uncorrelated' AS account_type
        FROM uncorrelated_accounts
        WHERE source_id = ?
    ");
    $stmt2->execute([$selected_source]);
    $uncorrelated = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    // Map role accounts to username
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
    $accounts = array_merge($correlated, $uncorrelated);
    // Determine columns present
    $allColumns = [];
    foreach ($accounts as $row) {
        foreach ($row as $key => $val) {
            $allColumns[$key] = true;
        }
    }
    // Column headers for display
    $columnHeaders = [
        'account_id' => 'Account ID',
        'username' => 'Username',
        'email' => 'Email',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'status' => 'Status',
        'created' => 'Created',
        'updated' => 'Last Updated',
    ];
    if (isset($allColumns['created_at'])) {
        $allColumns['created'] = true;
    }
    if (isset($allColumns['updated_at'])) {
        $allColumns['updated'] = true;
    }
    $displayColumns = array_intersect_key($columnHeaders, $allColumns);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Source Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        th a {
            color: inherit;
            text-decoration: none;
        }
        th a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <h2>Source Report</h2>
    <form method="get" class="mb-4">
        <label for="source_id" class="form-label">Select Source:</label>
        <select name="source_id" id="source_id" class="form-select" style="width:auto;display:inline-block" required>
            <option value="">-- Choose --</option>
            <?php foreach ($sources as $src): ?>
                <option value="<?= $src['id'] ?>" <?= $selected_source == $src['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($src['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="status_filter" class="form-label ms-3">Account Status:</label>
        <select name="status_filter" id="status_filter" class="form-select" style="width:auto;display:inline-block">
            <option value="" <?= $status_filter === '' ? 'selected' : '' ?>>All</option>
            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-primary ms-2">Generate</button>
        <?php if ($selected_source): ?>
        <a href="reports_source.php?source_id=<?= $selected_source ?>&status_filter=<?= htmlspecialchars($status_filter) ?>&export=1" class="btn btn-success ms-2">Export CSV</a>
        <?php endif; ?>
    </form>
    <?php if ($selected_source): ?>
        <p><?= count($accounts ?? []) ?> account(s) found for this source.</p>
        <?php if (!empty($accounts)): ?>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <?php foreach ($displayColumns as $col => $header):
                        $nextOrder = ($sortBy === $col && $order === 'asc') ? 'desc' : 'asc';
                        $arrow = '';
                        if ($sortBy === $col) {
                            $arrow = $order === 'asc' ? '▲' : '▼';
                        }
                    ?>
                    <th>
                        <a href="?source_id=<?= $selected_source ?>&status_filter=<?= htmlspecialchars($status_filter) ?>&sortBy=<?= $col ?>&order=<?= $nextOrder ?>">
                            <?= htmlspecialchars($header) ?> <?= $arrow ?>
                        </a>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                <tr>
                    <?php foreach ($displayColumns as $col => $header): ?>
                    <td><?= htmlspecialchars($a[$col] ?? ($a[$col.'_at'] ?? '')) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert alert-warning">No accounts found.</div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">Please select a source.</div>
    <?php endif; ?>
</div>
</body>
</html>

