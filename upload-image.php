<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_FILES['image']) || $_FILES['image']['error'] != UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or file upload error.']);
    exit;
}

// Validate file 
$fileType = mime_content_type($_FILES['image']['tmp_name']);
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
];
$maxFileSize = 1 * 1024 * 1024; // 1MB

// Validate MIME type
if (!array_key_exists($fileType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type.']);
    exit;
}

if (!getimagesize($_FILES['image']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image content.']);
    exit;
}

if ($_FILES['image']['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds limit.']);
    exit;
}

// Generate a unique name 
$extension = $allowedTypes[$fileType];
$fileName = uniqid("img_", true) . '.' . $extension; // Prefixed with "img_" for easier identification
$filePath = TEMP_DIR . $fileName;

// Move file to temp dir
if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
    // Generate a URL to access the uploaded file
    $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/' . $filePath;

    // Database insertion
    $stmt = $pdo->prepare("INSERT INTO temp_file_uploads (url) VALUES (?)");
    $stmt->execute([$url]);

    echo json_encode(['message' => 'Image uploaded successfully.', 'url' => $url]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to move uploaded file.']);
}
?>
