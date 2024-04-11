<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

$inputData = json_decode(file_get_contents('php://input'), true);

// Validate required fields and types
if (!isset($inputData['name'], $inputData['description']) ||
    !is_string($inputData['name']) || trim($inputData['name']) === '' ||
    !isset($inputData['description']) || !is_string($inputData['description']) || strlen(trim($inputData['description'])) < 1 || strlen(trim($inputData['description'])) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Description must be a string between 1 and 5000 characters.']);
    exit;
}

// Sanitize 'name' and 'description' for safe HTML display
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Usage
$inputData['name'] = sanitizeInput($inputData['name']);
$inputData['description'] = sanitizeInput($inputData['description']);


// Sanitize and validate 'discount'
if (isset($inputData['discount'])) {
    $inputData['discount'] = filter_var($inputData['discount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

// Assuming $inputData is your array containing all the input fields

// Sanitize numerical fields (prices and incomes)
foreach ($inputData['versions'] as &$version) {
    $version['price'] = filter_var($version['price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $version['income'] = filter_var($version['income'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    // Sanitize version names
    $version['version'] = filter_var($version['version'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}
unset($version);

// Sanitize developer info if it's a string (new developer name)
if (isset($inputData['developer_id']) && !is_numeric($inputData['developer_id'])) {
    $inputData['developer_id'] = filter_var($inputData['developer_id'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

// Sanitize tags if they are provided as strings
if (isset($inputData['tags']) && is_array($inputData['tags'])) {
    foreach ($inputData['tags'] as &$tag) {
        if (is_string($tag)) {
            $tag = filter_var($tag, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    }
    unset($tag); // Unset the reference to avoid side effects
}


// Sanitize image URLs
if (isset($inputData['images']) && is_array($inputData['images'])) {
    foreach ($inputData['images'] as &$imageUrl) {
        $imageUrl = filter_var($imageUrl, FILTER_SANITIZE_URL);
    }
    unset($imageUrl);
}

// New Developer logic
$developerId = null;
if (isset($inputData['developer_id'])) {
    if (is_numeric($inputData['developer_id'])) {
        $developerId = intval($inputData['developer_id']);
    } elseif (is_string($inputData['developer_id']) && !empty(trim($inputData['developer_id']))) {
        // Insert new developer and get ID
        $stmt = $pdo->prepare('INSERT INTO developers (name) VALUES (?)');
        $stmt->execute([trim($inputData['developer_id'])]);
        $developerId = $pdo->lastInsertId();
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Developer ID must be an integer or a non-empty string']);
        exit;
    }
}

if (isset($inputData['discount']) && (!is_numeric($inputData['discount']) || $inputData['discount'] < 0)) {
    http_response_code(400);
    echo json_encode(['error' => 'Discount must be a non-negative number.']);
    exit;
}

$fieldsToCheck = ['genres', 'languages', 'subtitles', 'categories']; // Categories included for completeness
foreach ($fieldsToCheck as $field) {
    if (isset($inputData[$field]) && !is_array($inputData[$field])) {
        http_response_code(400);
        echo json_encode(['error' => ucfirst($field) . ' must be an array.']);
        exit;
    }
    // Validate each item in the array if it's numeric and positive
    foreach ($inputData[$field] as $id) {
        if (!is_numeric($id) || $id < 1) {
            http_response_code(400);
            echo json_encode(['error' => 'Each ID in ' . $field . ' must be a positive integer.']);
            exit;
        }
    }
}

if (isset($inputData['tags']) && !is_array($inputData['tags'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tags must be an array.']);
    exit;
}
foreach ($inputData['tags'] as $tag) {
    if (!is_string($tag) && !is_numeric($tag)) { // Assuming tags can be new (string) or existing (numeric ID)
        http_response_code(400);
        echo json_encode(['error' => 'Each tag must be a string (new tag) or numeric ID (existing tag).']);
        exit;
    }
    // Additional validation can be added here if needed, e.g., string length for new tags
}

if (isset($inputData['versions']) && is_array($inputData['versions'])) {
    foreach ($inputData['versions'] as $version) {
        if (!isset($version['price'], $version['income']) ||
            !is_numeric($version['price']) || $version['price'] < 0 ||
            !is_numeric($version['income']) || $version['income'] < 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Each version must include non-negative numeric price and income.']);
            exit;
        }
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Versions must be provided as an array.']);
    exit;
}
if (isset($inputData['available']) && !in_array($inputData['available'], ['now', 'preorder'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Available must be either "now" or "preorder".']);
    exit;
}



try {
    $pdo->beginTransaction();

    // Insert product
    $stmt = $pdo->prepare('INSERT INTO products (name, description, developer_id, available, discount, quantity) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $inputData['name'],
        $inputData['description'],
        $developerId,
        $inputData['available'] ?? null,
        $inputData['discount'] ?? null,
        $inputData['quantity'] ?? null
    ]);
    $productId = $pdo->lastInsertId();

    // Handle version and price
    if (isset($inputData['versions']) && is_array($inputData['versions'])) {
        foreach ($inputData['versions'] as $version) {
            $stmt = $pdo->prepare('INSERT INTO versions (product_id, version, price, income) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                $productId,
                $version['version'],
                $version['price'] ?? null,
                $version['income'] ?? null
            ]);
        }
    }

    // Handle images
    if (isset($inputData['images']) && is_array($inputData['images'])) {
        foreach ($inputData['images'] as $imageUrl) {
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => "Invalid image URL: $imageUrl"]);
                exit;
            }
        }
    }
    
    // Handle images
if (isset($inputData['images']) && is_array($inputData['images'])) {
    foreach ($inputData['images'] as $imageUrl) {
        // Parse the URL to get the path component
        $parsedUrl = parse_url($imageUrl);
        $tempPath = $_SERVER['DOCUMENT_ROOT'] . $parsedUrl['path'];

        // Generate a new filename to prevent conflicts
        $filename = basename($tempPath);
        $permanentFilePath = PERMANENT_DIR . $filename;

    // Move the file from temp to permanent
    if (rename($tempPath, $permanentFilePath)) {
        // Construct the new URL
        $newUrl = str_replace('/temp/', '/permanent/', $imageUrl);

        // Insert the new URL into the database
        $stmt = $pdo->prepare('INSERT INTO ProductImages (product_id, url) VALUES (?, ?)');
        $stmt->execute([$productId, $newUrl]);
    } else {
        // Handle failure to move the file
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => "Failed to move image to permanent storage: $filename"]);
        exit;
    }
    }
}



    // Insert relational data (categories, genres, etc.) if exists
    insertRelationalData($pdo, $productId, $inputData);

    $pdo->commit();
    http_response_code(201);
    echo json_encode(['message' => 'Product created successfully', 'product_id' => $productId]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create product: ' . $e->getMessage()]);
    exit;
}

// Validate foreign keys, existsInTable, and insertRelationalData functions here (unchanged)



    function validateForeignKeys($pdo, $inputData) {
        $errors = [];
    
        // Developer ID
        if (isset($inputData['developer_id']) && !existsInTable($pdo, 'developers', 'developer_id', $inputData['developer_id'])) {
            $errors['developer_id'] = 'Invalid developer ID.';
        }
    
        // Dynamically check each relation (categories, genres, languages, subtitles, tags)
        $relationFields = [
            'categories' => 'category_id',
            'genres' => 'genre_id',
            'languages' => 'language_id',
            'subtitles' => 'subtitle_id',
            'tags' => 'tag_id'
        ];
    
        foreach ($relationFields as $field => $idColumn) {
            if (isset($inputData[$field])) {
                foreach ($inputData[$field] as $id) {
                    if (!existsInTable($pdo, $field, $idColumn, $id)) {
                        $errors[$field][] = 'Invalid ' . $field . ' ID: ' . $id;
                    }
                }
            }
        }
    
        return $errors;
    }
    


function existsInTable($pdo, $table, $column, $value) {
    // Function remains unchanged
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
    $stmt->execute([$value]);
    return $stmt->fetchColumn() > 0;
}



function insertRelationalData($pdo, $productId, $inputData) {
    // Helper function for batch inserts
    function batchInsertRelations($pdo, $productId, $tableName, $columnIdName, $data) {
        if(empty($data)) return;

        $insertValues = [];
        $insertParams = [];
        foreach ($data as $id) {
            $insertParams[] = "(?, ?)";
            $insertValues[] = $productId;
            $insertValues[] = $id;
        }

        $query = "INSERT INTO {$tableName} (product_id, {$columnIdName}) VALUES " . implode(', ', $insertParams);
        $stmt = $pdo->prepare($query);
        $stmt->execute($insertValues);
    }

    // Predefined relations
    $relations = [
        'categories' => ['table' => 'product_categories', 'column' => 'categories_id', 'data' => $inputData['categories'] ?? []],
        'genres' => ['table' => 'product_genres', 'column' => 'genres_id', 'data' => $inputData['genres'] ?? []],
        'languages' => ['table' => 'product_languages', 'column' => 'languages_id', 'data' => $inputData['languages'] ?? []],
        'subtitles' => ['table' => 'product_subtitles', 'column' => 'subtitles_id', 'data' => $inputData['subtitles'] ?? []],
    ];

    foreach ($relations as $key => $relation) {
        batchInsertRelations($pdo, $productId, $relation['table'], $relation['column'], $relation['data']);
    }

    // Handling tags with special considerations
    if (isset($inputData['tags']) && is_array($inputData['tags'])) {
        $tagIds = [];
        foreach ($inputData['tags'] as $tag) {
            $itemId = null; // Reset $itemId for each tag

            // Process for new or existing tags
            $tagStmt = $pdo->prepare("SELECT tag_id FROM tags WHERE name = ?");
            $tagStmt->execute([$tag]);
            $itemId = $tagStmt->fetchColumn();

            if (!$itemId) { // Tag does not exist, insert it
                $tagStmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                $tagStmt->execute([$tag]);
                $itemId = $pdo->lastInsertId();
            }

            // Check if the tag-product relation already exists to avoid duplicates
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM product_tags WHERE product_id = ? AND tags_id = ?");
            $checkStmt->execute([$productId, $itemId]);
            if ($checkStmt->fetchColumn() == 0) {
                $tagIds[] = $itemId; // Add tag ID for batch insertion if not already related
            }
        }

        // Perform batch insertion for tag-product relations
        if (!empty($tagIds)) {
            batchInsertRelations($pdo, $productId, 'product_tags', 'tags_id', $tagIds);
        }
    }
}







