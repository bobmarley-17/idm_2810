<?php
// alumni_report_generate.php

require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/AuditLogger.php';

// --- 1. Validate Input ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf()) {
    die("Invalid request.");
}

if (empty($_POST['start_date']) || empty($_POST['end_date']) || empty($_FILES['alumni_file']) || $_FILES['alumni_file']['error'] !== UPLOAD_ERR_OK) {
    die("Error: Missing required fields or file upload error.");
}

$startDate = $_POST['start_date'];
$endDate = $_POST['end_date'];
$alumniFilePath = $_FILES['alumni_file']['tmp_name'];

// --- 2. Parse the Alumni List ---
// Read the uploaded CSV and store all alumni emails in a PHP set for fast lookups.
$alumniEmails = [];
if (($handle = fopen($alumniFilePath, "r")) !== FALSE) {
    while (($data = fgetcsv($handle)) !== FALSE) {
        if (isset($data[0]) && filter_var(trim($data[0]), FILTER_VALIDATE_EMAIL)) {
            $alumniEmails[strtolower(trim($data[0]))] = true; // Use email as key for O(1) lookups
        }
    }
    fclose($handle);
}

if (empty($alumniEmails)) {
    die("The provided alumni file is empty or could not be read.");
}

try {
    // Log the action
    AuditLogger::getLogger('user_management')->info('Alumni access exception report downloaded.', [
        'user' => $_SESSION['username'],
        'details' => json_encode(['start_date' => $startDate, 'end_date' => $endDate, 'alumni_count' => count($alumniEmails)])
    ]);

    // --- 3. Set Headers for CSV Download ---
    $filename = "alumni_exception_report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    // Define and write the CSV header row (with our new 'Highlight' column)
    $header = [
        'Highlight', 'UserID', 'EmployeeID', 'FirstName', 'LastName', 'UserEmail', 'UserStatus',
        'SourceName', 'AccountUsername', 'AccountStatus', 'AccountUpdated'
    ];
    fputcsv($output, $header);

    // --- 4. Fetch the Data ---
    // Get all accounts that were active at any point within the date range.
    $stmt = $db->prepare("
        SELECT 
            u.id AS user_id, u.employee_id, u.first_name, u.last_name, u.email AS user_email,
            u.status AS user_status, s.name AS source_name, ua.username AS account_username,
            ua.status AS account_status, ua.updated_at AS account_updated
        FROM users u
        JOIN user_accounts ua ON u.id = ua.user_id
        JOIN account_sources s ON ua.source_id = s.id
        WHERE 
            ua.status = 'active' AND 
            ua.updated_at BETWEEN ? AND ?
        ORDER BY u.last_name, u.first_name, s.name
    ");
    $stmt->execute([$startDate, $endDate]);

    // --- 5. Process and Write Rows ---
    // Loop through the results, check against the alumni list, and write to the CSV.
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        
        $isAlumni = isset($alumniEmails[strtolower($row['user_email'])]);
        $highlight = '';

        // THIS IS THE CORE LOGIC: Highlight if the account is active AND the user is an alumnus.
        if ($row['account_status'] === 'active' && $isAlumni) {
            $highlight = 'EXCEPTION: ACTIVE ACCOUNT FOR ALUMNI';
        }

        $csvRow = [
            $highlight,
            $row['user_id'], $row['employee_id'], $row['first_name'], $row['last_name'],
            $row['user_email'], $row['user_status'], $row['source_name'],
            $row['account_username'], $row['account_status'], $row['account_updated']
        ];
        fputcsv($output, $csvRow);
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    error_log("Alumni report generation failed: " . $e->getMessage());
    die("A database error occurred while generating the report.");
}
