<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT tag_id, name FROM tags");
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['tags' => $tags]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve tags: ' . $e->getMessage()]);
}
