<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

// Check for Authorization header
if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authorization header missing']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'];
$token = str_replace('Bearer ', '', $authHeader);

try {
    $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));
    $userId = $decoded->sub;

    // Fetch the user details from the database
    $stmt = $pdo->prepare("SELECT username, email FROM admin_users WHERE id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode(['username' => $user['username'], 'email' => $user['email']]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token: ' . $e->getMessage()]);
}
