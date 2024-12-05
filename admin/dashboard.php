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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Dashboard</h1>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Revenue -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <h3 class="text-xl font-semibold mb-2">Total Revenue</h3>
                <p class="text-3xl font-bold text-green-500"><?php echo formatPrice($total_revenue); ?></p>
            </div>

            <!-- Total Orders -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <h3 class="text-xl font-semibold mb-2">Total Orders</h3>
                <p class="text-3xl font-bold text-blue-500"><?php echo $orders_count; ?></p>
                <a href="orders.php" class="text-blue-500 hover:text-blue-600 mt-2 inline-block">View Orders →</a>
            </div>

            <!-- Products -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <h3 class="text-xl font-semibold mb-2">Total Products</h3>
                <p class="text-3xl font-bold text-purple-500"><?php echo $products_count; ?></p>
                <a href="products.php" class="text-purple-500 hover:text-purple-600 mt-2 inline-block">Manage Products →</a>
            </div>

            <!-- Categories -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                <h3 class="text-xl font-semibold mb-2">Categories</h3>
                <p class="text-3xl font-bold text-yellow-500"><?php echo $categories_count; ?></p>
                <a href="categories.php" class="text-yellow-500 hover:text-yellow-600 mt-2 inline-block">Manage Categories →</a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Recent Orders</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Order ID</th>
                                <th class="px-4 py-2 text-left">Customer</th>
                                <th class="px-4 py-2 text-left">Amount</th>
                                <th class="px-4 py-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr class="border-t">
                                <td class="px-4 py-2">#<?php echo $order['id']; ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($order['username']); ?></td>
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="orders.php" class="text-blue-500 hover:text-blue-600 mt-4 inline-block">View All Orders →</a>
            </div>

            <!-- Low Stock Alert -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Low Stock Alert</h2>
                <div class="space-y-4">
                    <?php foreach ($low_stock_products as $product): ?>
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
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-medium <?php echo $product['stock'] <= 5 ? 'text-red-600' : 'text-yellow-600'; ?>">
                                <?php echo $product['stock']; ?> in stock
                            </p>
                            <button onclick="editProduct(<?php echo $product['id']; ?>)" 
                                    class="text-sm text-blue-500 hover:text-blue-600">
                                Update Stock
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Orders Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Orders by Status</h2>
                    <div class="flex items-center gap-4">
                        <select id="chartType" class="text-sm border border-gray-300 rounded px-2 py-1">
                            <option value="doughnut">Doughnut</option>
                            <option value="pie">Pie</option>
                            <option value="bar">Bar</option>
                        </select>
                    </div>
                </div>
                <div class="relative" style="height: 300px;">
                    <canvas id="ordersChart"></canvas>
                </div>
            </div>

            <!-- Orders Summary -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold mb-4">Orders Summary</h2>
                <div class="space-y-4">
                    <?php foreach ($orders_by_status as $status => $count): ?>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="w-3 h-3 rounded-full mr-2 
                                <?php echo match($status) {
                                    'pending' => 'bg-yellow-400',
                                    'processing' => 'bg-blue-400',
                                    'completed' => 'bg-green-400',
                                    'cancelled' => 'bg-red-400',
                                    default => 'bg-gray-400'
                                }; ?>"></span>
                            <span class="capitalize"><?php echo $status; ?></span>
                        </div>
                        <div class="font-medium">
                            <?php echo $count; ?> orders
                            (<?php echo round(($count / $orders_count) * 100); ?>%)
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    let ordersChart = null;

    function createChart(type = 'doughnut') {
        const ctx = document.getElementById('ordersChart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (ordersChart) {
            ordersChart.destroy();
        }

        // Create new chart
        ordersChart = new Chart(ctx, {
            type: type,
            data: {
                labels: <?php echo json_encode(array_map('ucfirst', array_keys($orders_by_status))); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($orders_by_status)); ?>,
                    backgroundColor: [
                        '#FCD34D', // pending - yellow
                        '#60A5FA', // processing - blue
                        '#34D399', // completed - green
                        '#F87171'  // cancelled - red
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20
                        }
                    }
                },
                ...(type === 'bar' ? {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                } : {})
            }
        });
    }

    // Initialize chart
    createChart('doughnut');

    // Handle chart type change
    document.getElementById('chartType').addEventListener('change', function() {
        createChart(this.value);
    });

    // Function to redirect to product edit
    function editProduct(productId) {
        window.location.href = `products.php?edit=${productId}`;
    }
    </script>
</body>
</html>
