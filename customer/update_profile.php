<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid request';
        header('Location: profile.php');
        exit();
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        
        // Validate inputs
        if (empty($username) || empty($email)) {
            $_SESSION['error'] = 'Username and email are required';
        } else {
            try {
                // Check if email is already taken by another user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $_SESSION['error'] = 'Email is already taken';
                } else {
                    // Update user profile
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    if ($stmt->execute([$username, $email, $_SESSION['user_id']])) {
                        $_SESSION['success'] = 'Profile updated successfully';
                    } else {
                        $_SESSION['error'] = 'Error updating profile';
                    }
                }
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Database error occurred';
            }
        }
    } 
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error'] = 'All password fields are required';
        } 
        elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = 'New passwords do not match';
        }
        elseif (strlen($new_password) < 6) {
            $_SESSION['error'] = 'Password must be at least 6 characters long';
        }
        else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!password_verify($current_password, $user['password'])) {
                    $_SESSION['error'] = 'Current password is incorrect';
                } else {
                    // Update password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                        $_SESSION['success'] = 'Password changed successfully';
                    } else {
                        $_SESSION['error'] = 'Error changing password';
                    }
                }
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Database error occurred';
            }
        }
    }
    
    header('Location: profile.php');
    exit();
}
