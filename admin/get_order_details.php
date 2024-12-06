<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    exit('Unauthorized');
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
        SELECT oi.*, p.name, p.image_url
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
} catch(PDOException $e) {
    http_response_code(500);
    exit('Error loading order details');
}

// Calculate totals
$subtotal = array_reduce($items, function($carry, $item) {
    return $carry + ($item['quantity'] * $item['price']);
}, 0);
?>

<div class="p-6 border-b">
    <div class="flex flex-wrap gap-6 justify-between items-start">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <h3 class="text-lg font-semibold">Order #<?php echo $order['id']; ?></h3>
                <span class="px-3 py-1 rounded-full text-sm font-medium
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
            </div>
            <p class="text-gray-600">
                <i class="far fa-calendar-alt mr-2"></i>
                <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?>
            </p>
            <p class="text-gray-600 mt-1">
                INV/<?php echo date('Ymd', strtotime($order['created_at'])); ?>/<?php echo $order['id']; ?>
            </p>
        </div>

        <!-- Status Update Form -->
        <div>
            <form method="POST" class="flex gap-2">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                <select name="status" class="text-sm border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status): ?>
                        <option value="<?php echo $status; ?>" 
                                <?php echo $order['status'] === $status ? 'selected' : ''; ?>>
                            <?php echo ucfirst($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="update_status" 
                        class="bg-blue-500 text-white px-4 py-2 rounded text-sm hover:bg-blue-600">
                    Update Status
                </button>
            </form>
        </div>
    </div>
</div>

<div class="p-6">
    <h4 class="font-semibold mb-4">Order Items</h4>
    <div class="divide-y">
        <?php if (!empty($items)): ?>
            <?php foreach ($items as $item): ?>
            <div class="flex items-center gap-4 py-4">
                <?php if ($item['image_url']): ?>
                    <img src="<?php echo BASE_URL . '/' . $item['image_url']; ?>" 
                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                         class="w-20 h-20 object-cover rounded-lg border border-gray-200">
                <?php else: ?>
                    <div class="w-20 h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-image text-gray-400 text-2xl"></i>
                    </div>
                <?php endif; ?>
                
                <div class="flex-1">
                    <h5 class="font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></h5>
                    <p class="text-sm text-gray-600 mt-1">
                        <?php echo formatPrice($item['price']); ?> × <?php echo $item['quantity']; ?>
                    </p>
                </div>
                
                <div class="text-right">
                    <p class="font-medium text-gray-900">
                        <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-4 text-gray-500">
                No items found in this order.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="bg-gray-50 p-6 border-t">
    <div class="max-w-sm ml-auto space-y-2">
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Subtotal</span>
            <span><?php echo formatPrice($subtotal); ?></span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Shipping</span>
            <span><?php echo formatPrice($order['shipping_cost']); ?></span>
        </div>
        <div class="flex justify-between font-semibold text-lg pt-2 border-t">
            <span>Total</span>
            <span><?php echo formatPrice($order['total_amount']); ?></span>
        </div>
    </div>
</div>

<div class="p-6 bg-gray-50 border-t">
    <h4 class="font-semibold mb-4">Customer & Shipping Details</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Customer Information -->
        <div>
            <h5 class="text-sm font-medium text-gray-700 mb-2">Customer Information</h5>
            <div class="bg-white rounded-lg p-4 space-y-2">
                <p><span class="text-gray-600">Name:</span> <?php echo htmlspecialchars($order['username']); ?></p>
                <p><span class="text-gray-600">Email:</span> <?php echo htmlspecialchars($order['email']); ?></p>
            </div>
        </div>

        <!-- Shipping Information -->
        <div>
            <h5 class="text-sm font-medium text-gray-700 mb-2">Shipping Information</h5>
            <div class="bg-white rounded-lg p-4 space-y-2">
                <p><span class="text-gray-600">Name:</span> <?php echo htmlspecialchars($order['shipping_name']); ?></p>
                <p><span class="text-gray-600">Phone:</span> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                <p><span class="text-gray-600">Address:</span> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                <p><span class="text-gray-600">City:</span> <?php echo htmlspecialchars($order['shipping_city']); ?></p>
                <p><span class="text-gray-600">Postal Code:</span> <?php echo htmlspecialchars($order['shipping_postal_code']); ?></p>
            </div>
        </div>
    </div>
</div>
