<?php

require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

$inputData = json_decode(file_get_contents('php://input'), true);

if (empty($inputData['product_id']) || !filter_var($inputData['product_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400);
    echo json_encode(['error' => 'Product ID is required and must be an integer.']);
    exit;
}

$productId = $inputData['product_id'];

if (empty($inputData['name']) || !is_string($inputData['name']) || trim($inputData['name']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Product name is required and must be a string.']);
    exit;
}

if (empty($inputData['description']) || !is_string($inputData['description']) || trim($inputData['description']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Product description is required and must be a string.']);
    exit;
}

if (empty($inputData['developer_id']) || !filter_var($inputData['developer_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
    http_response_code(400);
    echo json_encode(['error' => 'Developer ID is required and must be a positive integer.']);
    exit;
}

// Example validation logic for versions
if (isset($inputData['versions']) && is_array($inputData['versions'])) {
    foreach ($inputData['versions'] as $version) {
        if (empty($version['version']) || !is_string($version['version'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Each version must have a version identifier.']);
            exit;
        }
        // Add more validations as necessary (e.g., for price and income)
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Version data must be provided as an array.']);
    exit;
}


try {
    $pdo->beginTransaction();

    // Fetch existing image URLs
    $stmt = $pdo->prepare("SELECT url FROM ProductImages WHERE product_id = ?");
    $stmt->execute([$productId]);
    $existingUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $newUrls = $inputData['images'] ?? [];
    
    // URLs to delete
    $urlsToDelete = array_diff($existingUrls, $newUrls);
    
    // Delete unneeded image files and their records
    foreach ($urlsToDelete as $urlToDelete) {
        $stmt = $pdo->prepare("DELETE FROM ProductImages WHERE url = ? AND product_id = ?");
        $stmt->execute([$urlToDelete, $productId]);
        // Add file deletion logic here if storing files on server
    }

    // Update product details
    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, developer_id = ? WHERE product_id = ?");
    $stmt->execute([$inputData['name'], $inputData['description'], $inputData['developer_id'], $productId]);
    
    // Assume insertRelationalData function handles clearing and re-inserting relational data
    insertRelationalData($pdo, $productId, $inputData);

    $pdo->commit();
    echo json_encode(['message' => 'Product updated successfully']);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update product: ' . $e->getMessage()]);
    exit;
}


function insertRelationalData($pdo, $productId, $inputData) {
    $relations = [
        'categories' => 'product_categories',
        'genres' => 'product_genres',
        'languages' => 'product_languages',
        'subtitles' => 'product_subtitles',
        'tags' => 'product_tags'
        // No changes needed for these existing relations
    ];

    // Existing relational data handling remains unchanged...

    // Handling versions - Clear existing versions for the product
    $stmt = $pdo->prepare("DELETE FROM versions WHERE product_id = ?");
    $stmt->execute([$productId]);

    // Insert new versions
    if (isset($inputData['versions']) && is_array($inputData['versions'])) {
        foreach ($inputData['versions'] as $version) {
            $insertStmt = $pdo->prepare("INSERT INTO versions (product_id, version, price, income) VALUES (?, ?, ?, ?)");
            $insertStmt->execute([
                $productId, 
                $version['version'], 
                $version['price'], 
                $version['income'] // Assuming income is also a field you want to update
            ]);
        }
    }
}

