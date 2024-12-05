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
function getAllProducts($search = '', $category_filter = '') {
    global $pdo;
    
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($category_filter) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_filter;
    }
    
    $sql .= " ORDER BY p.name ASC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
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
        // First check if product has enough stock
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product || $product['stock'] < $quantity) {
            return false;
        }

        // Check if item already exists in cart
        $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $cart_item = $stmt->fetch();

        if ($cart_item) {
            // Update existing cart item
            $new_quantity = $cart_item['quantity'] + $quantity;
            if ($new_quantity > $product['stock']) {
                return false;
            }
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            return $stmt->execute([$new_quantity, $user_id, $product_id]);
        } else {
            // Add new cart item
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            return $stmt->execute([$user_id, $product_id, $quantity]);
        }
    } catch(PDOException $e) {
        error_log("Error in addToCart: " . $e->getMessage());
        return false;
    }
}

function updateCartItemQuantity($user_id, $product_id, $quantity) {
    global $pdo;
    try {
        // Check if product has enough stock
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product || $product['stock'] < $quantity) {
            return false;
        }

        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        return $stmt->execute([$quantity, $user_id, $product_id]);
    } catch(PDOException $e) {
        error_log("Error in updateCartItemQuantity: " . $e->getMessage());
        return false;
    }
}

function removeFromCart($user_id, $product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        return $stmt->execute([$user_id, $product_id]);
    } catch(PDOException $e) {
        error_log("Error in removeFromCart: " . $e->getMessage());
        return false;
    }
}

function getCartItems($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.stock, p.image_url 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error in getCartItems: " . $e->getMessage());
        return [];
    }
}

function getCartTotal($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT SUM(c.quantity * p.price) as total
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    } catch(PDOException $e) {
        error_log("Error in getCartTotal: " . $e->getMessage());
        return 0;
    }
}

function getCartItemCount($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch(PDOException $e) {
        error_log("Error in getCartItemCount: " . $e->getMessage());
        return 0;
    }
}

// Shipping Address functions
function getUserAddresses($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM shipping_addresses 
            WHERE user_id = ? 
            ORDER BY COALESCE(is_default, 0) DESC, created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error in getUserAddresses: " . $e->getMessage());
        return [];
    }
}

function getDefaultAddress($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM shipping_addresses 
            WHERE user_id = ? AND COALESCE(is_default, 0) = 1 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Error in getDefaultAddress: " . $e->getMessage());
        return null;
    }
}

function getAddressById($address_id, $user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM shipping_addresses 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$address_id, $user_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Error in getAddressById: " . $e->getMessage());
        return null;
    }
}

function addAddress($user_id, $data) {
    global $pdo;
    try {
        // Begin transaction
        $pdo->beginTransaction();

        // If this is set as default, unset other default addresses
        if (!empty($data['is_default'])) {
            $stmt = $pdo->prepare("
                UPDATE shipping_addresses 
                SET is_default = NULL 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
        }

        // Insert new address
        $stmt = $pdo->prepare("
            INSERT INTO shipping_addresses (
                user_id, name, phone, address, city, 
                postal_code, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $data['name'],
            $data['phone'],
            $data['address'],
            $data['city'],
            $data['postal_code'],
            !empty($data['is_default']) ? 1 : NULL
        ]);

        // Commit transaction
        $pdo->commit();
        return true;
    } catch(PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error in addAddress: " . $e->getMessage() . "\nData: " . print_r($data, true));
        return false;
    }
}

function updateAddress($address_id, $user_id, $data) {
    global $pdo;
    try {
        // Begin transaction
        $pdo->beginTransaction();

        // If this is set as default, unset other default addresses
        if (!empty($data['is_default'])) {
            $stmt = $pdo->prepare("
                UPDATE shipping_addresses 
                SET is_default = NULL 
                WHERE user_id = ? AND id != ?
            ");
            $stmt->execute([$user_id, $address_id]);
        }

        // Update address
        $stmt = $pdo->prepare("
            UPDATE shipping_addresses 
            SET name = ?, 
                phone = ?, 
                address = ?, 
                city = ?, 
                postal_code = ?, 
                is_default = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['phone'],
            $data['address'],
            $data['city'],
            $data['postal_code'],
            !empty($data['is_default']) ? 1 : NULL,
            $address_id,
            $user_id
        ]);

        // Commit transaction
        $pdo->commit();
        return true;
    } catch(PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error in updateAddress: " . $e->getMessage() . "\nData: " . print_r($data, true));
        return false;
    }
}

function deleteAddress($address_id, $user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            DELETE FROM shipping_addresses 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$address_id, $user_id]);
        return true;
    } catch(PDOException $e) {
        error_log("Error in deleteAddress: " . $e->getMessage());
        return false;
    }
}

// Order functions
function getOrderById($order_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   sa.name as shipping_name,
                   sa.phone as shipping_phone,
                   sa.address as shipping_address,
                   sa.city as shipping_city,
                   sa.postal_code as shipping_postal_code
            FROM orders o
            LEFT JOIN shipping_addresses sa ON o.shipping_address_id = sa.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Error in getOrderById: " . $e->getMessage());
        return null;
    }
}

function getOrderItems($order_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name, p.image_url
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error getting order items: " . $e->getMessage());
        return [];
    }
}

function getUserOrders($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, a.name as shipping_name, a.address, a.city, a.postal_code, a.phone
            FROM orders o
            LEFT JOIN shipping_addresses a ON o.shipping_address_id = a.id
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error getting user orders: " . $e->getMessage());
        return [];
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

function formatPrice($price) {
    return 'Rp ' . number_format($price, 0, ',', '.');
}

?>
