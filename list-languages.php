<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT language_id, name FROM languages");
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['languages' => $languages]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve languages: ' . $e->getMessage()]);
}
