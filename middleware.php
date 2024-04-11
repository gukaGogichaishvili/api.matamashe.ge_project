<?php
require_once 'config.php'; // Make sure this imports the PDO instance
require_once 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authenticate() {
    // Check if the Authorization header is set
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization token is missing']);
        exit;
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    $jwt = null;

    // Extract the token from the Authorization header
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $jwt = $matches[1];
    }

    // Token extraction failed
    if (!$jwt) {
        http_response_code(401);
        echo json_encode(['error' => 'Token not found in the request']);
        exit;
    }

    try {
        // Decode the JWT
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));

        global $pdo;
        // Validate the token against the sessions table
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE user_id = :user_id AND token = :token AND expires_at > NOW()");
        $stmt->execute([':user_id' => $decoded->sub, ':token' => $jwt]);
        $session = $stmt->fetch();

        if (!$session) {
            http_response_code(401);
            echo json_encode(['error' => 'Session not valid or expired']);
            exit;
        }

        // Optionally set user information as a global or pass along to the request handler
        // $GLOBALS['user_id'] = $decoded->sub;

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => "Invalid token: " . $e->getMessage()]);
        exit;
    }
}

// Usage: Call authenticate() at the start of each script that requires token validation
