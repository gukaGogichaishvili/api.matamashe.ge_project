<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT category_id, name FROM categories");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['categories' => $categories]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve categories: ' . $e->getMessage()]);
}
