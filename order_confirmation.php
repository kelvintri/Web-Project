<?php
require_once 'includes/functions.php';
require_once 'config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Order Confirmation';

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get order details
$order = getOrderById($order_id);

// Verify order belongs to current user
if (!$order || $order['user_id'] != $_SESSION['user_id']) {
    header('Location: index.php');
    exit();
}

// Get order items
$order_items = getOrderItems($order_id);

require_once 'includes/layout/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Order Confirmed!</h1>
                <p class="text-gray-600">
                    Thank you for your order. Your order number is #<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?>
                </p>
            </div>

            <div class="border-t border-b py-4 mb-4">
                <h2 class="text-lg font-semibold mb-4">Order Details</h2>
                
                <?php foreach ($order_items as $item): ?>
                    <div class="flex items-center justify-between py-2">
                        <div class="flex items-center">
                            <?php if ($item['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="h-16 w-16 object-cover rounded">
                            <?php endif; ?>
                            <div class="ml-4">
                                <h3 class="text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p class="text-gray-600">
                                    <?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($item['price'], 0, ',', '.'); ?> × <?php echo $item['quantity']; ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-gray-800 font-medium">
                            <?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="mt-4 pt-4 border-t">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span>
                            <?php echo CURRENCY_SYMBOL; ?> 
                            <?php echo number_format($order['total_amount'] - $order['shipping_cost'], 0, ',', '.'); ?>
                        </span>
                    </div>
                    <div class="flex justify-between text-gray-600 mt-2">
                        <span>Shipping</span>
                        <span>
                            <?php echo CURRENCY_SYMBOL; ?> 
                            <?php echo number_format($order['shipping_cost'], 0, ',', '.'); ?>
                        </span>
                    </div>
                    <div class="flex justify-between text-lg font-bold mt-2 pt-2 border-t">
                        <span>Total</span>
                        <span>
                            <?php echo CURRENCY_SYMBOL; ?> 
                            <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-4">Shipping Address</h2>
                <div class="text-gray-600">
                    <p class="font-medium"><?php echo htmlspecialchars($order['shipping_name']); ?></p>
                    <p><?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                    <p><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                    <p><?php echo htmlspecialchars($order['shipping_city']); ?> <?php echo htmlspecialchars($order['shipping_postal_code']); ?></p>
                </div>
            </div>

            <div class="text-center space-x-4">
                <a href="customer/orders.php" class="text-blue-600 hover:text-blue-800">
                    View Order History
                </a>
                <span class="text-gray-300">|</span>
                <a href="index.php" class="text-blue-600 hover:text-blue-800">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/layout/footer.php'; ?>
