<?php
require_once 'config/database.php';
require_once 'connectors/XMLConnector.php';

header('Content-Type: application/json');

try {
    // Check for uploaded file or file path
    if (isset($_FILES['xml_file'])) {
        $file = $_FILES['xml_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload failed with error code " . $file['error']);
        }

        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ['application/xml', 'text/xml'])) {
            throw new Exception("Invalid file type. Expected XML, got: " . $mimeType);
        }

        $filePath = $file['tmp_name'];
    } elseif (isset($_POST['file_path'])) {
        $filePath = $_POST['file_path'];
    } else {
        throw new Exception("Neither file upload nor file path provided");
    }

    // Detect fields using XMLConnector
    $result = XMLConnector::detectFields($filePath);
    
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
