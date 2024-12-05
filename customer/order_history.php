<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$orders = getUserOrders($user_id);

// Get any status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Filter orders if status is set
if ($status_filter) {
    $orders = array_filter($orders, function($order) use ($status_filter) {
        return $order['status'] === $status_filter;
    });
}

$page_title = "Order History";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Order History</h1>

    <!-- Status Filter -->
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status:</label>
        <div class="flex gap-2">
            <a href="order_history.php" class="<?php echo $status_filter === '' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded-md text-sm">
                All
            </a>
            <a href="?status=pending" class="<?php echo $status_filter === 'pending' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded-md text-sm">
                Pending
            </a>
            <a href="?status=processing" class="<?php echo $status_filter === 'processing' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded-md text-sm">
                Processing
            </a>
            <a href="?status=shipped" class="<?php echo $status_filter === 'shipped' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded-md text-sm">
                Shipped
            </a>
            <a href="?status=delivered" class="<?php echo $status_filter === 'delivered' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded-md text-sm">
                Delivered
            </a>
            <a href="?status=cancelled" class="<?php echo $status_filter === 'cancelled' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded-md text-sm">
                Cancelled
            </a>
        </div>
    </div>

    <?php if (empty($orders)): ?>
    <div class="bg-gray-50 rounded-lg p-8 text-center">
        <p class="text-gray-600">No orders found.</p>
        <a href="../index.php" class="inline-block mt-4 text-blue-500 hover:text-blue-600">Continue Shopping</a>
    </div>
    <?php else: ?>
    <div class="grid gap-6">
        <?php foreach ($orders as $order): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-lg font-semibold">Order #<?php echo $order['id']; ?></h3>
                    <p class="text-gray-600">Placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                </div>
                <div class="text-right">
                    <span class="inline-block px-3 py-1 rounded-full text-sm
                        <?php
                        switch($order['status']) {
                            case 'pending':
                                echo 'bg-yellow-100 text-yellow-800';
                                break;
                            case 'processing':
                                echo 'bg-blue-100 text-blue-800';
                                break;
                            case 'shipped':
                                echo 'bg-purple-100 text-purple-800';
                                break;
                            case 'delivered':
                                echo 'bg-green-100 text-green-800';
                                break;
                            case 'cancelled':
                                echo 'bg-red-100 text-red-800';
                                break;
                        }
                        ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                    <p class="mt-2 font-semibold">Total: Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></p>
                </div>
            </div>

            <?php
            $order_items = getOrderItems($order['id']);
            ?>
            <div class="border-t pt-4">
                <h4 class="font-semibold mb-3">Order Items:</h4>
                <div class="grid gap-4">
                    <?php foreach ($order_items as $item): ?>
                    <div class="flex items-center gap-4">
                        <img src="../<?php echo $item['image_url']; ?>" alt="<?php echo $item['name']; ?>" class="w-16 h-16 object-cover rounded">
                        <div class="flex-1">
                            <h5 class="font-medium"><?php echo $item['name']; ?></h5>
                            <p class="text-gray-600">
                                Quantity: <?php echo $item['quantity']; ?> × 
                                Rp <?php echo number_format($item['price'], 0, ',', '.'); ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold">Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="border-t mt-4 pt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal:</span>
                    <span>Rp <?php echo number_format($order['total_amount'] - $order['shipping_cost'], 0, ',', '.'); ?></span>
                </div>
                <div class="flex justify-between text-sm mt-2">
                    <span class="text-gray-600">Shipping:</span>
                    <span>Rp <?php echo number_format($order['shipping_cost'], 0, ',', '.'); ?></span>
                </div>
                <div class="flex justify-between font-semibold mt-2 pt-2 border-t">
                    <span>Total:</span>
                    <span>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
