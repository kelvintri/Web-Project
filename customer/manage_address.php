<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'profile.php';
$success = false;

if (!validateCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error'] = 'Invalid request';
    header('Location: ' . $redirect_url);
    exit();
}

// Handle add new address
if (isset($_POST['add_address'])) {
    $name = sanitizeInput($_POST['name']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $postal_code = sanitizeInput($_POST['postal_code']);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    try {
        // If setting as default, unset other default addresses
        if ($is_default) {
            $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }

        // Insert new address
        $stmt = $pdo->prepare("
            INSERT INTO shipping_addresses (user_id, name, phone, address, city, postal_code, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $success = $stmt->execute([$_SESSION['user_id'], $name, $phone, $address, $city, $postal_code, $is_default]);
        
        if ($success) {
            $_SESSION['success'] = 'Address added successfully';
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error saving address';
    }
}

// Handle delete address
if (isset($_POST['delete_address'])) {
    $address_id = (int)$_POST['address_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM shipping_addresses WHERE id = ? AND user_id = ?");
        $success = $stmt->execute([$address_id, $_SESSION['user_id']]);
        
        if ($success) {
            $_SESSION['success'] = 'Address deleted successfully';
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error deleting address';
    }
}

// Handle set default address
if (isset($_POST['set_default'])) {
    $address_id = (int)$_POST['address_id'];
    try {
        // First, unset all default addresses
        $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Set the selected address as default
        $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
        $success = $stmt->execute([$address_id, $_SESSION['user_id']]);
        
        if ($success) {
            $_SESSION['success'] = 'Default address updated successfully';
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error updating default address';
    }
}

header('Location: ' . $redirect_url);
exit();
