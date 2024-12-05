<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    exit('Unauthorized');
}

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get order details
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.username, u.email,
               sa.name as shipping_name, sa.phone as shipping_phone,
               sa.address as shipping_address, sa.city as shipping_city,
               sa.postal_code as shipping_postal_code
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN shipping_addresses sa ON o.shipping_address_id = sa.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        exit('Order not found');
    }

    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.price
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
} catch(PDOException $e) {
    http_response_code(500);
    exit('Error loading order details');
}

// Calculate subtotal
$subtotal = array_reduce($items, function($carry, $item) {
    return $carry + ($item['quantity'] * $item['price']);
}, 0);

// Fixed shipping cost
$shipping = 20000; // Rp 20.000
?>

<div class="space-y-6">
    <!-- Order Items -->
    <div>
        <h4 class="font-medium mb-3">Order Items</h4>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="space-y-3">
                <?php foreach ($items as $item): ?>
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-medium"><?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="text-gray-500 text-sm">(<?php echo $item['quantity']; ?>x)</span>
                        </div>
                        <div class="text-gray-900">
                            <?php echo formatPrice($item['quantity'] * $item['price']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Order Summary -->
            <div class="mt-4 pt-4 border-t">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal</span>
                    <span><?php echo formatPrice($subtotal); ?></span>
                </div>
                <div class="flex justify-between text-sm mt-2">
                    <span class="text-gray-600">Shipping</span>
                    <span><?php echo formatPrice($shipping); ?></span>
                </div>
                <div class="flex justify-between font-medium mt-2 pt-2 border-t">
                    <span>Total</span>
                    <span><?php echo formatPrice($subtotal + $shipping); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Information -->
    <div>
        <h4 class="font-medium mb-3">Customer Information</h4>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-600">Username:</span>
                    <span class="ml-2"><?php echo htmlspecialchars($order['username']); ?></span>
                </div>
                <div>
                    <span class="text-gray-600">Email:</span>
                    <span class="ml-2"><?php echo htmlspecialchars($order['email']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Shipping Information -->
    <div>
        <h4 class="font-medium mb-3">Shipping Information</h4>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="grid gap-3 text-sm">
                <div>
                    <span class="text-gray-600">Name:</span>
                    <span class="ml-2"><?php echo htmlspecialchars($order['shipping_name']); ?></span>
                </div>
                <div>
                    <span class="text-gray-600">Phone:</span>
                    <span class="ml-2"><?php echo htmlspecialchars($order['shipping_phone']); ?></span>
                </div>
                <div>
                    <span class="text-gray-600">Address:</span>
                    <span class="ml-2"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                </div>
                <div>
                    <span class="text-gray-600">City:</span>
                    <span class="ml-2"><?php echo htmlspecialchars($order['shipping_city']); ?></span>
                </div>
                <div>
                    <span class="text-gray-600">Postal Code:</span>
                    <span class="ml-2"><?php echo htmlspecialchars($order['shipping_postal_code']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Status -->
    <div>
        <h4 class="font-medium mb-3">Order Status</h4>
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="grid gap-3 text-sm">
                <div>
                    <span class="text-gray-600">Status:</span>
                    <span class="ml-2 capitalize"><?php echo htmlspecialchars($order['status']); ?></span>
                </div>
                <div>
                    <span class="text-gray-600">Order Date:</span>
                    <span class="ml-2"><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
