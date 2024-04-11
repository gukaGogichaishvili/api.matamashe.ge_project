<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

function logout() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // Proceed only if $authHeader is not empty
    if (!empty($authHeader)) {
        $jwt = str_replace('Bearer ', '', $authHeader);

        try {
            global $pdo;
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE token = :token");
            $stmt->execute([':token' => $jwt]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Logged out successfully']);
            } else {
                echo json_encode(['message' => 'Session already terminated or not found']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'An error occurred during logout']);
        }
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization header not found']);
    }
}

logout();
