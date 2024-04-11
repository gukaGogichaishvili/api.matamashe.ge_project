<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT genre_id, name FROM genres");
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['genres' => $genres]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve genres: ' . $e->getMessage()]);
}
