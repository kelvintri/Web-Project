<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Gaming Gear Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? '../index.php' : 'index.php'; ?>" class="text-xl font-bold text-gray-800">
                        Gaming Gear Store
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? '../cart.php' : 'cart.php'; ?>" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-shopping-cart"></i> Cart
                        </a>
                        <div class="relative group">
                            <button class="text-gray-600 hover:text-gray-800">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                            </button>
                            <div class="absolute right-0 w-48 py-2 mt-2 bg-white rounded-md shadow-xl hidden group-hover:block">
                                <a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? 'manage_address.php' : 'customer/manage_address.php'; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    Manage Addresses
                                </a>
                                <a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? 'order_history.php' : 'customer/order_history.php'; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    Order History
                                </a>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? '../admin/index.php' : 'admin/index.php'; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    Admin Dashboard
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? '../logout.php' : 'logout.php'; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? '../login.php' : 'login.php'; ?>" class="text-gray-600 hover:text-gray-800">Login</a>
                        <a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? '../register.php' : 'register.php'; ?>" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="min-h-screen"><?php // Main content will go here ?>
