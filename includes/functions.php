<?php
session_start();

// Database connection
require_once __DIR__ . '/../config/database.php';

// Authentication functions
function register($username, $email, $password) {
    global $pdo;
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        return $stmt->execute([$username, $email, $hashed_password]);
    } catch(PDOException $e) {
        return false;
    }
}

function login($email, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    } catch(PDOException $e) {
        return false;
    }
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Product functions
function getAllProducts() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

function getProductById($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// Category functions
function getAllCategories() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM categories");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Cart functions
function addToCart($user_id, $product_id, $quantity = 1) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        return $stmt->execute([$user_id, $product_id, $quantity, $quantity]);
    } catch(PDOException $e) {
        return false;
    }
}

function getCartItems($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT c.*, p.name, p.price, p.image_url FROM cart c 
                              JOIN products p ON c.product_id = p.id 
                              WHERE c.user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

// Shipping address functions
function getUserAddresses($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}

function getDefaultAddress($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE user_id = ? AND is_default = 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

function getAddressById($address_id, $user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE id = ? AND user_id = ?");
        $stmt->execute([$address_id, $user_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return null;
    }
}

// Utility functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
