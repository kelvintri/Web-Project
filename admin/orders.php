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

$page_title = "Manage Orders";
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
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
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
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo formatPrice($order['total_amount']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 rounded-full text-xs font-medium
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
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="showOrderDetails(<?php echo $order['id']; ?>)" 
                                    class="text-blue-600 hover:text-blue-800">
                                View Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center p-6 border-b sticky top-0 bg-white z-10">
                    <h3 class="text-lg font-semibold">Order Details</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="modalContent" class="relative">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
    function showOrderDetails(orderId) {
        const modal = document.getElementById('orderModal');
        const modalContent = document.getElementById('modalContent');
        
        // Show loading state
        modalContent.innerHTML = `
            <div class="flex justify-center items-center p-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>
        `;
        modal.classList.remove('hidden');
        
        // Fetch order details
        fetch(`get_order_details.php?id=${orderId}`)
            .then(response => response.text())
            .then(html => {
                modalContent.innerHTML = html;
            })
            .catch(error => {
                modalContent.innerHTML = `
                    <div class="p-6 text-center text-red-500">
                        Error loading order details. Please try again.
                    </div>
                `;
            });
    }

    function closeModal() {
        document.getElementById('orderModal').classList.add('hidden');
    }

    // Close modal when clicking outside
    document.getElementById('orderModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
    </script>
</body>
</html>
