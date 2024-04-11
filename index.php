<?php
// Log errors to a specific file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/my_errors.log');

header('Access-Control-Allow-Origin: *'); // Adjust this as necessary
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE'); // Add or remove methods as per your requirement
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Respond to preflight request by ending script execution and sending the headers above
    exit(0);
}

require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');

// Define your routes and their corresponding files and middleware
$routes = [
    'login' => [
        'file' => '/login.php',
        'middleware' => [],
    ],
    'logout' => [
        'file' => '/logout.php',
        'middleware' => ['authenticate'], // Changed from validateToken to authenticate
    ],
    'fetch-user' => [
        'file' => '/fetch-user.php',
        'middleware' => [], // Changed from validateToken to authenticate
    ],
    'refresh-token' => [
        'file' => '/refresh-token.php',
        'middleware' => ['authenticate'], // Changed from validateToken to authenticate
    ],
    'create-product' => [
        'file' => '/create-product.php',
        'middleware' => ['authenticate'], // Changed from validateToken to authenticate
    ],
    'edit-product' => [
        'file' => '/edit-product.php', // Assuming your edit-product logic is in this file
        'middleware' => ['authenticate'], // Protect this route with authentication middleware
    ],
      'delete-product' => [
        'file' => '/delete-product.php', // Assuming your edit-product logic is in this file
        'middleware' => ['authenticate'], // Protect this route with authentication middleware
    ],
    'upload-image' => [
        'file' => '/upload-image.php', // Assuming your edit-product logic is in this file
        'middleware' => ['authenticate'], // Protect this route with authentication middleware
    ],
    
      'delete-uploaded-image' => [
        'file' => '/delete-uploaded-image.php', // Assuming your edit-product logic is in this file
        'middleware' => ['authenticate'], // Protect this route with authentication middleware
    ],
    
    'list-products' => [
        'file' => '/list-products.php', // Assuming your edit-product logic is in this file
        'middleware' => [], // Protect this route with authentication middleware
    ],
    'list-categories' => [
        'file' => '/list-categories.php', // Assuming your edit-product logic is in this file
        'middleware' => [], // Protect this route with authentication middleware
    ],
    'list-genres' => [
        'file' => '/list-genres.php', // Assuming your edit-product logic is in this file
        'middleware' => [], // Protect this route with authentication middleware
    ],
    'list-tags' => [
        'file' => '/list-tags.php', // Assuming your edit-product logic is in this file
        'middleware' => [], // Protect this route with authentication middleware
    ],
    'list-developers' => [
        'file' => '/list-developers.php', // Assuming your edit-product logic is in this file
        'middleware' => [], // Protect this route with authentication middleware
    ],
    'list-subtitles' => [
        'file' => '/list-subtitles.php', // Assuming your edit-product logic is in this file
        'middleware' => [], // Protect this route with authentication middleware
    ],
    'list-languages' => [
        'file' => '/list-languages.php', // Assuming your edit-product logic is in this file
        'middleware' => [], // Protect this route with authentication middleware
    ],
];

// Standardize the request URI by trimming leading and trailing slashes
$requestUri = trim($_SERVER['REQUEST_URI'], '/');

// Load middleware for the requested route
foreach ($routes[$requestUri]['middleware'] as $middlewareFunction) {
    // Assuming a single middleware function named authenticate, directly call it.
    // If you had multiple middleware functions, you might load them differently.
    require_once __DIR__ . "/middleware.php"; // Include the file where authenticate function is defined
    $middlewareFunction(); // Call the middleware function directly
}

// Check if the route exists
if (!array_key_exists($requestUri, $routes)) {
    http_response_code(404);
    echo json_encode(['error' => 'Resource not found.']);
    exit;
}

// Load the requested route file
require __DIR__ . $routes[$requestUri]['file'];
