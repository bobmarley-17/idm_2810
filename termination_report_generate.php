<?php
// exception_report_generate.php - FINAL CONFIRMED VERSION

require_once 'bootstrap.php';
require_once 'auth_check.php';
require_once 'config/database.php';
require_once 'lib/AuditLogger.php';
require_once 'vendor/autoload.php';

use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Box\Spout\Common\Entity\Style\Color;

// --- 1. Validate Input ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf()) { die("Invalid request."); }
if (empty($_FILES['termination_file']) || $_FILES['termination_file']['error'] !== UPLOAD_ERR_OK) { die("Error: File upload failed."); }
$terminationFilePath = $_FILES['termination_file']['tmp_name'];

// --- 2. Parse the Termination File to get a list of terminated usernames ---
$terminatedUsernames = [];
try {
    // This logic correctly handles both plain text and zipped XLSX files.
    $fileSignature = file_get_contents($terminationFilePath, false, null, 0, 2);
    if ($fileSignature === 'PK') { // It's an XLSX file
        $zip = new ZipArchive;
        if ($zip->open($terminationFilePath) !== TRUE) throw new Exception("Could not open XLSX file.");
        
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sharedStringsXml === false || $sheetXml === false) throw new Exception("Invalid XLSX file structure.");

        $sharedStrings = [];
        $stringsXml = simplexml_load_string($sharedStringsXml);
        foreach ($stringsXml->si as $si) { $sharedStrings[] = (string)$si->t; }
        
        $worksheetXml = simplexml_load_string($sheetXml);
        // Assuming 'username' is the 3rd column (C). Adjust if necessary.
        foreach ($worksheetXml->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                if (preg_match('/^C\d+/', (string)$cell['r'])) {
                    if (isset($cell->v)) {
                        $cellValue = (string)$cell->v;
                        if (isset($cell['t']) && $cell['t'] == 's') {
                            $username = strtolower(trim($sharedStrings[(int)$cellValue]));
                            if (!empty($username)) { $terminatedUsernames[$username] = true; }
                        }
                    }
                }
            }
        }
        $zip->close();
    } else { // It's a plain text/TSV file
        $fileHandle = fopen($terminationFilePath, "r");
        if ($fileHandle === false) throw new Exception("Could not open termination file.");
        fgets($fileHandle); // Skip header
        while (($line = fgets($fileHandle)) !== false) {
            $sanitizedLine = preg_replace('/\s+/', "\t", trim($line));
            $columns = explode("\t", $sanitizedLine);
            if (isset($columns[2])) { // Assuming username is the 3rd column
                $username = strtolower(trim($columns[2]));
                if (!empty($username)) { $terminatedUsernames[$username] = true; }
            }
        }
        fclose($fileHandle);
    }
} catch (Exception $e) { die("Error processing uploaded file: " . $e->getMessage()); }

if (empty($terminatedUsernames)) { die("No usernames were found in the termination file."); }

// --- 3. Generate the Styled Excel Report ---
try {
    AuditLogger::getLogger('user_management')->info('Exception report downloaded (XLSX).', ['user' => $_SESSION['username']]);

    $filename = "exception_report_" . date('Y-m-d') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $writer = WriterEntityFactory::createXLSXWriter();
    $writer->openToBrowser($filename);

    $headerStyle = (new StyleBuilder())->setFontBold()->build();
    $defaultRowStyle = (new StyleBuilder())->build();
    $highlightStyle = (new StyleBuilder())->setBackgroundColor(Color::YELLOW)->build();

    $header = ['UserID', 'EmployeeID', 'FullName', 'UserEmail', 'SourceName', 'AccountUsername', 'AccountStatus', 'LastUpdated'];
    $writer->addRow(WriterEntityFactory::createRowFromArray($header, $headerStyle));

    // --- 4. Fetch Master Data and Write to Excel ---
    $stmt = $db->query("
        SELECT 
            u.id AS user_id, u.employee_id, u.first_name, u.last_name, u.email AS user_email,
            s.name AS source_name, ua.username AS account_username,
            ua.status AS account_status, ua.updated_at AS account_updated
        FROM users u
        LEFT JOIN user_accounts ua ON u.id = ua.user_id
        LEFT JOIN account_sources s ON ua.source_id = s.id
        ORDER BY u.last_name, u.first_name, s.name
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        
        // ============================ YOUR CORE LOGIC IS HERE ============================
        
        // 1. Get the AccountUsername from the Master Report row
        $accountUsername = strtolower($row['account_username'] ?? '');
        
        // 2. Check if this AccountUsername exists in the list of terminated usernames
        $isTerminated = isset($terminatedUsernames[$accountUsername]);
        
        $currentRowStyle = $defaultRowStyle; 
        
        // 3. If the account is 'active' AND the username was found in the termination list, apply the highlight.
        if ($row['account_status'] === 'active' && $isTerminated) {
            $currentRowStyle = $highlightStyle;
        }
        
        // ==================================================================================

        $rowData = [
            $row['user_id'], $row['employee_id'], $row['first_name'] . ' ' . $row['last_name'],
            $row['user_email'], $row['source_name'], $row['account_username'],
            $row['account_status'], $row['account_updated']
        ];
        
        $dataRow = WriterEntityFactory::createRowFromArray($rowData, $currentRowStyle);
        $writer->addRow($dataRow);
    }

    $writer->close();
    exit();

} catch (Exception $e) {
    error_log("Exception report generation failed: " . $e->getMessage());
    die("A critical error occurred while generating the report.");
}
