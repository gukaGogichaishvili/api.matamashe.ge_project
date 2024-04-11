<?php
require_once 'config.php';
require_once 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

// Sanitize input
$input = json_decode(file_get_contents('php://input'), true);

$username = filter_var($input['username'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password = filter_var($input['password'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$user_ip = $_SERVER['REMOTE_ADDR']; // Get the user's IP address

// Basic validation
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, password_hash, failed_login_attempts, last_login_attempt FROM admin_users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    // Implement logic to check if the user is temporarily locked out from too many failed attempts
    if ($user && $user['failed_login_attempts'] >= 10) {
        // Check if the last failed attempt was more than 10 minutes ago
        $last_attempt_time = strtotime($user['last_login_attempt']);
        if (time() - $last_attempt_time < 600) { // 600 seconds = 10 minutes
            throw new Exception('Too many failed login attempts. Please try again later.');
        }
    }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Increment failed login attempts
        $stmt = $pdo->prepare("UPDATE admin_users SET failed_login_attempts = failed_login_attempts + 1, last_login_attempt = NOW(), last_login_ip = :user_ip WHERE username = :username");
        $stmt->execute([':user_ip' => $user_ip, ':username' => $username]);
        
        throw new Exception('Invalid credentials.');
    }
    
    // Login is successful, reset failed_login_attempts and update last login info
    $stmt = $pdo->prepare("UPDATE admin_users SET failed_login_attempts = 0, last_login_attempt = NOW(), user_ip = :user_ip WHERE id = :user_id");
    $stmt->execute([':user_ip' => $user_ip, ':user_id' => $user['id']]);


    // Generate JWT
    $issuedAt = time();
    $expirationTime = $issuedAt + 86400; // Valid for 24 hours
    $payload = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'sub' => $user['id'],
    ];

    $jwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');

     $stmt = $pdo->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (:user_id, :token, FROM_UNIXTIME(:expires_at)) ON DUPLICATE KEY UPDATE token = :token, expires_at = FROM_UNIXTIME(:expires_at)");
$stmt->execute([
    ':user_id' => $user['id'],
    ':token' => $jwt,
    ':expires_at' => $expirationTime
]);


    echo json_encode(['message' => 'Login successful.', 'token' => $jwt]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
