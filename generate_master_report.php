#!/usr/bin/php
<?php
// generate_master_report.php
// This script is designed to be run from the command line (CLI) via a cron job.

// --- CONFIGURATION ---
// TODO: Set the absolute path where you want to save the reports.
// Ensure this directory exists and the user running the cron job has write permissions to it.
$savePath = '/var/www/html/idm_2810/reports/master_reports/';
// --------------------

// Since this script is in the root, the paths to other files are simpler.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/AuditLogger.php';

// Check if running from the command line to prevent web access
if (php_sapi_name() !== 'cli') {
    die("Access Denied. This script can only be run from the command line.");
}

// Check if the save path exists and is writable
if (!is_dir($savePath) || !is_writable($savePath)) {
    $errorMsg = "CRON JOB FAILED: The specified save path '$savePath' does not exist or is not writable.";
    error_log($errorMsg);
    // Log to a system channel if you have one, otherwise use a general logger
    AuditLogger::getLogger('system_errors')->critical('Master Report generation failed.', ['error' => $errorMsg]);
    exit(1); // Exit with an error code
}

// Define the filename with the current date
$filename = $savePath . "master_audit_report_" . date('Y-m-d') . ".csv";
echo "Starting report generation. Output file: $filename\n";

try {
    $fileHandle = fopen($filename, 'w');
    if ($fileHandle === false) {
        throw new Exception("Could not open file '$filename' for writing.");
    }

    // Define and write the CSV header row
    $header = [
        'UserID', 'EmployeeID', 'FirstName', 'LastName', 'UserEmail', 'UserStatus',
        'SourceName', 'SourceCategory', 'AccountID', 'AccountUsername', 'AccountEmail',
        'AccountStatus', 'AccountCreated', 'AccountUpdated', 'DeletionDate'
    ];
    fputcsv($fileHandle, $header);

    // Prepare and execute the master SQL query
    $stmt = $db->query("
        SELECT 
            u.id AS user_id, u.employee_id, u.first_name, u.last_name, u.email AS user_email,
            u.status AS user_status, s.name AS source_name, s.category AS source_category,
            ua.account_id, ua.username AS account_username, ua.email AS account_email,
            ua.status AS account_status, ua.created_at AS account_created,
            ua.updated_at AS account_updated, ua.deletion_date
        FROM users u
        LEFT JOIN user_accounts ua ON u.id = ua.user_id
        LEFT JOIN account_sources s ON ua.source_id = s.id
        ORDER BY u.last_name, u.first_name, s.name
    ");

    $rowCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $csvRow = [
            $row['user_id'], $row['employee_id'], $row['first_name'], $row['last_name'],
            $row['user_email'], $row['user_status'], $row['source_name'], $row['source_category'],
            $row['account_id'], $row['account_username'], $row['account_email'],
            $row['account_status'], $row['account_created'], $row['account_updated'],
            $row['deletion_date']
        ];
        fputcsv($fileHandle, $csvRow);
        $rowCount++;
    }

    fclose($fileHandle);
    
    $successMsg = "Successfully generated master report with $rowCount rows.";
    echo "$successMsg\n";
    AuditLogger::getLogger('system_events')->info($successMsg, ['file' => $filename]);
    exit(0); // Exit with a success code

} catch (Exception $e) {
    $errorMsg = "CRON JOB FAILED: " . $e->getMessage();
    error_log($errorMsg);
    AuditLogger::getLogger('system_errors')->critical('Master Report generation failed.', ['error' => $e->getMessage()]);
    if (isset($fileHandle) && is_resource($fileHandle)) { fclose($fileHandle); }
    if (isset($filename) && file_exists($filename)) { unlink($filename); }
    exit(1); // Exit with an error code
}
