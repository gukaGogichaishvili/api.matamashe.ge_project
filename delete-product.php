<?php

require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

// Extract product ID from the request
$inputData = json_decode(file_get_contents('php://input'), true);
$productId = $inputData['product_id'] ?? null;

if (!$productId || !filter_var($productId, FILTER_VALIDATE_INT)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Product ID is required and must be a valid integer.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Optional: Check if the product exists before attempting deletion
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE product_id = ?");
    $stmt->execute([$productId]);
    if ($stmt->fetchColumn() == 0) {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Product not found.']);
        $pdo->rollBack();
        exit;
    }

    // Fetch image URLs from the database before deleting
    $stmt = $pdo->prepare("SELECT url FROM ProductImages WHERE product_id = ?");
    $stmt->execute([$productId]);
    $imageUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete the physical image files
    foreach ($imageUrls as $imageUrl) {
        $filePath = parse_url($imageUrl, PHP_URL_PATH);
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath); // Delete the file
        }
    }

    // Delete the image records from the database
    $stmt = $pdo->prepare("DELETE FROM ProductImages WHERE product_id = ?");
    $stmt->execute([$productId]);

    // Delete related data first to maintain referential integrity
    $relatedTables = ['product_categories', 'product_genres', 'product_languages', 'product_subtitles', 'product_tags'];
    foreach ($relatedTables as $table) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE product_id = ?");
        $stmt->execute([$productId]);
    }
    
    // Delete related versions data
$stmt = $pdo->prepare("DELETE FROM versions WHERE product_id = ?");
$stmt->execute([$productId]);


    // Now delete the product itself
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->execute([$productId]);

    if ($stmt->rowCount() > 0) {
        $pdo->commit();
        http_response_code(200); // OK
        echo json_encode(['message' => 'Product deleted successfully.']);
    } else {
        $pdo->rollBack();
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to delete product.']);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
}

