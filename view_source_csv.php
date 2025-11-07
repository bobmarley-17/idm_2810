<?php
require_once 'config/database.php';

// --- THE ONLY CHANGE IS HERE: Establish the database connection ---
$db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
// ------------------------------------------------------------------

// --- CONFIGURATION ---
define('CSV_BASE_PATH', '/var/www/html/idm_2810/uploads/');
// ---------------------

// 1. Get and CLEAN parameters from the URL
if (!isset($_GET['source_id'])) die("Error: No source ID was provided.");
$source_id = intval($_GET['source_id']);

$email_to_highlight = isset($_GET['email']) ? strtolower(trim(urldecode($_GET['email']))) : null;

// 2. Fetch file path (unchanged)
$stmt = $db->prepare("SELECT config FROM account_sources WHERE id = ? LIMIT 1");
$stmt->execute([$source_id]);
$source = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$source || empty($source['config'])) die("Error: This source does not have a backend CSV file configured.");
$configArray = json_decode($source['config'], true);
if (!isset($configArray['file_path']) || empty($configArray['file_path'])) die("Error: The 'file_path' is not set in this source's configuration.");
$filePath = $configArray['file_path'];

// 3. Security Checks (unchanged)
if (!file_exists($filePath) || !is_readable($filePath)) die("Error: The configured file does not exist or is not readable on the server.");
if (strpos(realpath($filePath), realpath(CSV_BASE_PATH)) !== 0) die("Security Error: Access to this file path is denied.");

// 4. Set browser header for HTML output
header('Content-Type: text/html; charset=utf-8');

// 5. REVISED HTML & HIGHLIGHTING LOGIC
echo '<!DOCTYPE html><html><head><title>CSV Content</title></head><body>';
echo '<pre>';

if (($handle = fopen($filePath, "r")) !== FALSE) {
    // Read header row to find the 'email' column index
    $headers = fgetcsv($handle);
    $email_col_index = false;
    if ($headers) {
        // Find the EMAIL column header
        foreach ($headers as $index => $header) {
            if (in_array(strtolower(trim($header)), ['email'])) {
                $email_col_index = $index;
                break;
            }
        }
        echo htmlspecialchars(implode(",", $headers)) . "\n";
    }

    // Read and process the rest of the file
    while (($line = fgets($handle)) !== FALSE) {
        $line = rtrim($line, "\r\n");
        if (empty($line)) continue;

        $should_highlight = false;
        // Check if we should highlight this line
        if ($email_to_highlight !== null && $email_col_index !== false) {
            $data = str_getcsv($line);

            if (isset($data[$email_col_index])) {
                // Compare EMAIL from CSV with email from URL
                $csv_email = strtolower(trim($data[$email_col_index]));
                if ($csv_email === $email_to_highlight) {
                    $should_highlight = true;
                }
            }
        }

        $safe_line = htmlspecialchars($line);

        // Print the line, wrapping it in a styled span if it matched
        if ($should_highlight) {
            echo '<span style="background-color: yellow;">' . $safe_line . '</span>' . "\n";
        } else {
            echo $safe_line . "\n";
        }
    }
    fclose($handle);
} else {
    echo "Error: Could not open the CSV file.";
}

echo '</pre>';
echo '</body></html>';

exit;
?>
