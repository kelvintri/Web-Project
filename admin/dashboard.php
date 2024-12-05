<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$products_count = count(getAllProducts());
$categories_count = count(getAllCategories());

// Get recent orders
try {
    $stmt = $pdo->query("SELECT COUNT(*) as order_count FROM orders");
    $orders_count = $stmt->fetch()['order_count'];
} catch(PDOException $e) {
    $orders_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - E-Commerce Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Dashboard</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Products Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold mb-2">Total Products</h3>
                <p class="text-3xl font-bold text-blue-500"><?php echo $products_count; ?></p>
                <a href="products.php" class="text-blue-500 hover:text-blue-600 mt-2 inline-block">Manage Products →</a>
            </div>

            <!-- Categories Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold mb-2">Total Categories</h3>
                <p class="text-3xl font-bold text-green-500"><?php echo $categories_count; ?></p>
                <a href="categories.php" class="text-green-500 hover:text-green-600 mt-2 inline-block">Manage Categories →</a>
            </div>

            <!-- Orders Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-xl font-semibold mb-2">Total Orders</h3>
                <p class="text-3xl font-bold text-purple-500"><?php echo $orders_count; ?></p>
                <a href="orders.php" class="text-purple-500 hover:text-purple-600 mt-2 inline-block">View Orders →</a>
            </div>
        </div>
    </div>
</body>
</html>
