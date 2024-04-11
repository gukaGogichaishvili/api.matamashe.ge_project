<?php
require_once 'config.php'; 

header('Content-Type: application/json');


// Check and sanitize 
if (!isset($_POST['filename']) || empty(trim($_POST['filename']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Filename is required.']);
    exit;
}

$filename = basename(trim($_POST['filename'])); // Use basename to prevent directory traversal
$filePath = TEMP_DIR . $filename;


if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found.']);
    exit;
}

//  delete 
if (unlink($filePath)) {
    echo json_encode(['message' => 'File deleted successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete the file.']);
}
?>
