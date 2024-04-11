<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT subtitle_id, name FROM subtitles");
    $subtitles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['subtitles' => $subtitles]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve subtitles: ' . $e->getMessage()]);
}
