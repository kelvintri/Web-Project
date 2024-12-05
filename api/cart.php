<?php
require_once '../includes/functions.php';
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please login to add items to cart']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate CSRF token
$headers = getallheaders();
if (!isset($headers['X-CSRF-Token']) || !validateCSRFToken($headers['X-CSRF-Token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit();
}

// Validate required fields
if (!isset($data['product_id']) || !isset($data['quantity'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$product_id = (int)$data['product_id'];
$quantity = (int)$data['quantity'];

// Validate quantity
if ($quantity < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid quantity']);
    exit();
}

// Check if product exists and has enough stock
try {
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit();
    }

    if ($product['stock'] < $quantity) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Not enough stock available']);
        exit();
    }

    // Add to cart
    if (addToCart($_SESSION['user_id'], $product_id, $quantity)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error adding to cart']);
    }

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}
