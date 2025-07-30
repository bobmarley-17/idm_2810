<?php
header('Content-Type: application/json');

$response = ['success' => false];

try {
    if (!isset($_POST['file_path'])) {
        throw new Exception('File path not provided');
    }

    $filePath = $_POST['file_path'];
    $hasHeaders = isset($_POST['has_headers']) && $_POST['has_headers'] === '1';

    // Security checks
    if (empty($filePath)) {
        throw new Exception('File path is empty');
    }

    if (!file_exists($filePath)) {
        throw new Exception('File does not exist at: ' . $filePath);
    }

    if (!is_readable($filePath)) {
        throw new Exception('File is not readable');
    }

    // Open the file
    $file = fopen($filePath, 'r');
    if (!$file) {
        throw new Exception('Could not open file');
    }

    // Read the first line
    $firstLine = fgets($file);
    fclose($file);

    if ($firstLine === false) {
        throw new Exception('Could not read file contents');
    }

    // Parse CSV line
    $headers = str_getcsv($firstLine);

    if (!is_array($headers)) {
        throw new Exception('Could not parse CSV headers');
    }

    // Trim whitespace from headers
    $headers = array_map('trim', $headers);

    // If no headers, generate default column names
    if (!$hasHeaders) {
        $headers = array_map(function($i) {
            return 'column_' . ($i + 1);
        }, array_keys($headers));
    }

    $response = [
        'success' => true,
        'headers' => $headers
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
exit;