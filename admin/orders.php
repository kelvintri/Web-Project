<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/functions.php';

// Define BASE_URL constant if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://final.test');
}

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
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $orders = $stmt->fetchAll();
} catch(PDOException $e) {
    $orders = [];
    $error = 'Error fetching orders: ' . $e->getMessage();
}

// Get order items separately for each order
foreach ($orders as &$order) {
    try {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name, p.image_url
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute([$order['id']]);
        $order['order_items'] = $stmt->fetchAll();
    } catch(PDOException $e) {
        $order['order_items'] = [];
        $error = 'Error fetching order items: ' . $e->getMessage();
    }
}
unset($order);

$page_title = "Manage Orders";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer>
    const BASE_URL = '<?php echo BASE_URL; ?>';

    function formatPrice(amount) {
        return 'Rp ' + Number(amount).toLocaleString('id-ID');
    }

    function showOrderDetails(order, orderItems) {
        const modal = document.getElementById('orderModal');
        const modalContent = document.getElementById('modalContent');
        
        const statusClasses = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'processing': 'bg-blue-100 text-blue-800',
            'shipped': 'bg-indigo-100 text-indigo-800',
            'delivered': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800'
        };

        const content = `
            <div class="p-6">
                <div class="mb-6">
                    <h4 class="text-lg font-semibold mb-2">Customer Information</h4>
                    <p><strong>Name:</strong> ${order.username}</p>
                    <p><strong>Email:</strong> ${order.email}</p>
                </div>

                <div class="mb-6">
                    <h4 class="text-lg font-semibold mb-2">Shipping Information</h4>
                    <p><strong>Name:</strong> ${order.shipping_name}</p>
                    <p><strong>Phone:</strong> ${order.shipping_phone}</p>
                    <p><strong>Address:</strong> ${order.shipping_address}</p>
                    <p><strong>City:</strong> ${order.shipping_city}</p>
                    <p><strong>Postal Code:</strong> ${order.shipping_postal_code}</p>
                </div>

                <div class="mb-6">
                    <h4 class="text-lg font-semibold mb-2">Order Items</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left">Product</th>
                                    <th class="px-4 py-2 text-left">Price</th>
                                    <th class="px-4 py-2 text-left">Quantity</th>
                                    <th class="px-4 py-2 text-left">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${orderItems.map(item => `
                                    <tr>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center">
                                                <img src="${BASE_URL}/${item.image_url}" alt="${item.name}" class="w-12 h-12 object-cover rounded mr-2">
                                                <span>${item.name}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">${formatPrice(item.price)}</td>
                                        <td class="px-4 py-2">${item.quantity}</td>
                                        <td class="px-4 py-2">${formatPrice(item.price * item.quantity)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <div class="text-right">
                        <p class="text-lg"><strong>Total Amount:</strong> ${formatPrice(order.total_amount)}</p>
                    </div>
                </div>

                <div class="mt-6 border-t pt-4">
                    <h4 class="text-lg font-semibold mb-2">Update Status</h4>
                    <form method="POST" class="flex items-center gap-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="order_id" value="${order.id}">
                        <select name="status" class="rounded border-gray-300 shadow-sm p-2">
                            ${['pending', 'processing', 'shipped', 'delivered', 'cancelled'].map(status => 
                                `<option value="${status}" ${order.status === status ? 'selected' : ''}>${
                                    status.charAt(0).toUpperCase() + status.slice(1)
                                }</option>`
                            ).join('')}
                        </select>
                        <button type="submit" name="update_status" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Update Status
                        </button>
                    </form>
                </div>
            </div>
        `;
        
        modalContent.innerHTML = content;
        modal.classList.remove('hidden');
    }

    function closeModal() {
        const modal = document.getElementById('orderModal');
        modal.classList.add('hidden');
    }

    // Close modal when clicking outside
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('orderModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal();
            }
        });
    });
    </script>
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
                            <button onclick='showOrderDetails(<?php echo json_encode($order); ?>, <?php echo json_encode($order["order_items"]); ?>)'
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
    <div id="orderModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" style="backdrop-filter: blur(4px);">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto relative">
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
</body>
</html>
