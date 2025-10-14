<?php
require_once 'config/database.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="master_identity_report.csv"');

$output = fopen('php://output', 'w');

// Write CSV headers in your specified order
fputcsv($output, [
    'Source Name', 'Email', 'Username', 'First Name', 'Last Name', 'Status', 'Supervisor Email', 'Source Type',
    'User ID',
    'Account ID', 'Account Email', 'Account Status',
    'Created At', 'Updated At'
]);

$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);

$stmt = $db->query("
    SELECT
        s.name AS source_name,
        ua.email AS account_email,
        ua.username AS account_username,
        u.first_name,
        u.last_name,
        u.status AS user_status,
        u.supervisor_email,
        s.type AS source_type,
        u.id AS user_id,
        ua.account_id,
        ua.email AS account_email_dup,
        ua.status AS account_status,
        ua.created_at,
        ua.updated_at
    FROM users u
    LEFT JOIN user_accounts ua ON ua.user_id = u.id
    LEFT JOIN account_sources s ON s.id = ua.source_id
    ORDER BY s.name, u.id
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['source_name'],
        $row['account_email'],
        $row['account_username'],
        $row['first_name'],
        $row['last_name'],
        $row['user_status'],
        $row['supervisor_email'],
        $row['source_type'],
        $row['user_id'],
        $row['account_id'],
        $row['account_email_dup'],
        $row['account_status'],
        $row['created_at'],
        $row['updated_at']
    ]);
}

fclose($output);
exit;

