<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $order_id = (int)$_POST['order_id'];
        $status = sanitizeInput($_POST['status']);
        
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            if ($stmt->execute([$status, $order_id])) {
                $success = 'Order status updated successfully';
            }
        } catch(PDOException $e) {
            $error = 'Error updating order status';
        }
    }
}

// Get all orders with user and shipping information
try {
    $stmt = $pdo->query("
        SELECT o.*, u.username, u.email,
               sa.name as shipping_name, sa.phone as shipping_phone,
               sa.address as shipping_address, sa.city as shipping_city,
               sa.postal_code as shipping_postal_code,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN shipping_addresses sa ON o.shipping_address_id = sa.id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll();
} catch(PDOException $e) {
    $orders = [];
    $error = 'Error fetching orders';
}

// Function to get order items
function getOrderItems($order_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        return [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Manage Orders</h1>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">#<?php echo $order['id']; ?></td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['username']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $order['item_count']; ?> items</td>
                        <td class="px-6 py-4 whitespace-nowrap">$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <form action="orders.php" method="POST" class="flex items-center space-x-2">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="status" 
                                        class="text-sm border-gray-300 rounded focus:outline-none focus:border-blue-500"
                                        onchange="this.form.submit()">
                                    <?php 
                                    $statuses = ['pending', 'processing', 'completed', 'cancelled'];
                                    foreach ($statuses as $status):
                                    ?>
                                        <option value="<?php echo $status; ?>" 
                                                <?php echo $order['status'] === $status ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_status" class="text-blue-600 hover:text-blue-900">
                                    Update
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="toggleOrderDetails(<?php echo $order['id']; ?>)"
                                    class="text-blue-600 hover:text-blue-900">
                                View Details
                            </button>
                        </td>
                    </tr>
                    <tr id="order-details-<?php echo $order['id']; ?>" class="hidden bg-gray-50">
                        <td colspan="7" class="px-6 py-4">
                            <div class="text-sm">
                                <h4 class="font-medium mb-2">Order Items:</h4>
                                <ul class="list-disc list-inside mb-4">
                                    <?php foreach (getOrderItems($order['id']) as $item): ?>
                                        <li>
                                            <?php echo htmlspecialchars($item['product_name']); ?> 
                                            (<?php echo $item['quantity']; ?> x $<?php echo number_format($item['price'], 2); ?>)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <h4 class="font-medium mb-2">Shipping Information:</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?></p>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                                    </div>
                                    <div>
                                        <p><strong>City:</strong> <?php echo htmlspecialchars($order['shipping_city']); ?></p>
                                        <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($order['shipping_postal_code']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleOrderDetails(orderId) {
            const detailsRow = document.getElementById(`order-details-${orderId}`);
            detailsRow.classList.toggle('hidden');
        }
    </script>
</body>
</html>
