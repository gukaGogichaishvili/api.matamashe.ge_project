<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT developer_id, name, website FROM developers");
    $developers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['developers' => $developers]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve developers: ' . $e->getMessage()]);
}
