<?php
// refresh-token.php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

// Extract the token from the Authorization header
if (!isset($_SERVER['HTTP_AUTHORIZATION']) || !preg_match('/Bearer\s(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authorization header not found or invalid']);
    exit;
}

$jwt = $matches[1];

try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
    $currentTime = time();

    // Ensure the token is close to expiration before allowing a refresh
    if (($decoded->exp - $currentTime) <= 900) { // 5 minutes before expiration
        // Invalidate old token by removing it from the database
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE token = :token");
        $stmt->execute([':token' => $jwt]);

        // Issue a new token
        $newPayload = [
            "iss" => $decoded->iss,
            "aud" => $decoded->aud,
            "iat" => $currentTime,
            "exp" => $currentTime + (24 * 60 * 60), // 24 hours
            "sub" => $decoded->sub
        ];

        $newJwt = JWT::encode($newPayload, JWT_SECRET_KEY, 'HS256');

        // Store the new token in the database associated with the user
        $stmt = $pdo->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (:user_id, :token, FROM_UNIXTIME(:expires_at))");
        $stmt->execute([
            ':user_id' => $decoded->sub,
            ':token' => $newJwt,
            ':expires_at' => $newPayload['exp']
        ]);

        echo json_encode(['message' => 'Token refreshed', 'token' => $newJwt]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Token not ready for refresh']);
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token: ' . $e->getMessage()]);
    exit;
}
