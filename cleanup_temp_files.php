<?php
require_once 'config.php'; // Make sure this path is correct

// Specify the age threshold for deleting files (in seconds). Here, 86400 seconds = 24 hours.
$thresholdSeconds = 86400;

// SQL query to select files older than the threshold
$sql = "SELECT id, url FROM temp_file_uploads WHERE created_at < NOW() - INTERVAL ? SECOND";

// Prepare and execute the query
$stmt = $pdo->prepare($sql);
$stmt->execute([$thresholdSeconds]);
$filesToDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($filesToDelete as $file) {
    $filePath = $_SERVER['DOCUMENT_ROOT'] . parse_url($file['url'], PHP_URL_PATH);
    
    // Check if the file exists and delete it
    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            echo "Deleted: " . $filePath . PHP_EOL;
        } else {
            echo "Failed to delete: " . $filePath . PHP_EOL;
        }
    }

    // Remove the record from the database
    $delStmt = $pdo->prepare("DELETE FROM temp_file_uploads WHERE id = ?");
    $delStmt->execute([$file['id']]);
}

echo "Cleanup process completed." . PHP_EOL;
?>
