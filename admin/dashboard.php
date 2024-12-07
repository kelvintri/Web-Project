<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

// Get basic stats
$products_count = count(getAllProducts());
$categories_count = count(getAllCategories());

// Get orders statistics
try {
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as order_count FROM orders");
    $orders_count = $stmt->fetch()['order_count'];

    // Recent orders (last 5)
    $stmt = $pdo->query("
        SELECT o.*, u.username, u.email,
               COUNT(oi.id) as items_count,
               SUM(oi.quantity * oi.price) as total_amount
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();

    // Orders by status
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM orders
        GROUP BY status
    ");
    $orders_by_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Low stock products (less than 10 items)
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.stock < 10
        ORDER BY p.stock ASC
        LIMIT 5
    ");
    $low_stock_products = $stmt->fetchAll();

    // Total revenue
    $stmt = $pdo->query("
        SELECT SUM(total_amount) as total_revenue
        FROM orders
        WHERE status != 'cancelled'
    ");
    $total_revenue = $stmt->fetch()['total_revenue'] ?? 0;

    // Customer statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT user_id) as total_customers,
            AVG(total_amount) as avg_order_value
        FROM orders
        WHERE status != 'cancelled'
    ");
    $customer_stats = $stmt->fetch();

    // Top selling products
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.name,
            p.image_url,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.price) as total_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status != 'cancelled'
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $top_products = $stmt->fetchAll();

} catch(PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - E-Commerce Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" />
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Dashboard</h1>
            <div class="text-sm text-gray-500">Last updated: <?php echo date('M d, Y H:i'); ?></div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Revenue -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-semibold mb-2">Total Revenue</h3>
                        <p class="text-3xl font-bold text-green-500"><?php echo formatPrice($total_revenue); ?></p>
                    </div>
                    <div class="text-green-500">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">Avg. Order: <?php echo formatPrice($customer_stats['avg_order_value']); ?></p>
            </div>

            <!-- Total Orders -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-semibold mb-2">Total Orders</h3>
                        <p class="text-3xl font-bold text-blue-500"><?php echo $orders_count; ?></p>
                    </div>
                    <div class="text-blue-500">
                        <i class="fas fa-shopping-cart text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2">From <?php echo $customer_stats['total_customers']; ?> customers</p>
                <a href="orders.php" class="text-blue-500 hover:text-blue-600 mt-2 inline-block">View Orders →</a>
            </div>

            <!-- Products -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-semibold mb-2">Total Products</h3>
                        <p class="text-3xl font-bold text-purple-500"><?php echo $products_count; ?></p>
                    </div>
                    <div class="text-purple-500">
                        <i class="fas fa-box text-2xl"></i>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-2"><?php echo count($low_stock_products); ?> low stock items</p>
                <a href="products.php" class="text-purple-500 hover:text-purple-600 mt-2 inline-block">Manage Products →</a>
            </div>

            <!-- Categories -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-xl font-semibold mb-2">Categories</h3>
                        <p class="text-3xl font-bold text-yellow-500"><?php echo $categories_count; ?></p>
                    </div>
                    <div class="text-yellow-500">
                        <i class="fas fa-tags text-2xl"></i>
                    </div>
                </div>
                <a href="categories.php" class="text-yellow-500 hover:text-yellow-600 mt-2 inline-block">Manage Categories →</a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Orders with Quick Actions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Recent Orders</h2>
                    <a href="orders.php" class="text-blue-500 hover:text-blue-600">View All →</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Order ID</th>
                                <th class="px-4 py-2 text-left">Customer</th>
                                <th class="px-4 py-2 text-left">Amount</th>
                                <th class="px-4 py-2 text-left">Status</th>
                                <th class="px-4 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr class="border-t hover:bg-gray-50">
                                <td class="px-4 py-2">#<?php echo $order['id']; ?></td>
                                <td class="px-4 py-2">
                                    <div><?php echo htmlspecialchars($order['username']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                                </td>
                                <td class="px-4 py-2"><?php echo formatPrice($order['total_amount']); ?></td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-1 rounded text-sm 
                                        <?php echo match($order['status']) {
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'processing' => 'bg-blue-100 text-blue-800',
                                            'shipped' => 'bg-indigo-100 text-indigo-800',
                                            'delivered' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        }; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <button onclick="viewOrder(<?php echo $order['id']; ?>)" 
                                            class="text-blue-500 hover:text-blue-600 mr-2">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="updateOrderStatus(<?php echo $order['id']; ?>)" 
                                            class="text-green-500 hover:text-green-600">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Products -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Top Selling Products</h2>
                    <a href="products.php" class="text-blue-500 hover:text-blue-600">View All →</a>
                </div>
                <div class="space-y-4">
                    <?php foreach ($top_products as $product): ?>
                    <div class="flex items-center justify-between border-b pb-4">
                        <div class="flex items-center">
                            <?php if ($product['image_url']): ?>
                                <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     class="w-12 h-12 object-cover rounded">
                            <?php else: ?>
                                <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
                                    <span class="text-gray-500 text-xs">No image</span>
                                </div>
                            <?php endif; ?>
                            <div class="ml-4">
                                <h4 class="font-medium"><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p class="text-sm text-gray-500"><?php echo $product['total_sold']; ?> units sold</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-medium text-green-600"><?php echo formatPrice($product['total_revenue']); ?></p>
                            <button onclick="editProduct(<?php echo $product['id']; ?>)" 
                                    class="text-sm text-blue-500 hover:text-blue-600">
                                View Details
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize any remaining dashboard functionality here
    });
    </script>
</body>
</html>
