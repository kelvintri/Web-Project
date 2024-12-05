<?php
require_once 'includes/functions.php';
require_once 'config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Checkout';
$active_page = 'checkout';

// Get cart items
$cart_items = getCartItems($_SESSION['user_id']);
if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

// Get user's shipping addresses
$addresses = getUserAddresses($_SESSION['user_id']);
$default_address = getDefaultAddress($_SESSION['user_id']);

// Calculate totals
$subtotal = 0;
$shipping_cost = 20000; // Fixed shipping cost for now
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$total = $subtotal + $shipping_cost;

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid request';
    } else {
        $address_id = (int)($_POST['shipping_address'] ?? 0);
        $shipping_address = getAddressById($address_id, $_SESSION['user_id']);

        if (!$shipping_address) {
            $_SESSION['error'] = 'Please select a valid shipping address';
        } else {
            // Create order
            try {
                $pdo->beginTransaction();

                // Check stock availability again
                $stock_error = false;
                foreach ($cart_items as $item) {
                    $stock_check = $pdo->prepare("SELECT stock FROM products WHERE id = ? AND stock >= ?");
                    $stock_check->execute([$item['product_id'], $item['quantity']]);
                    if (!$stock_check->fetch()) {
                        $stock_error = true;
                        $_SESSION['error'] = "Sorry, {$item['name']} is no longer available in the requested quantity.";
                        break;
                    }
                }

                if (!$stock_error) {
                    // Insert order
                    $stmt = $pdo->prepare("
                        INSERT INTO orders (user_id, shipping_address_id, total_amount, shipping_cost, status)
                        VALUES (?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([$_SESSION['user_id'], $address_id, $total, $shipping_cost]);
                    $order_id = $pdo->lastInsertId();

                    // Insert order items and update stock
                    foreach ($cart_items as $item) {
                        // Insert order item
                        $stmt = $pdo->prepare("
                            INSERT INTO order_items (order_id, product_id, quantity, price)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $order_id,
                            $item['product_id'],
                            $item['quantity'],
                            $item['price']
                        ]);

                        // Update product stock
                        $stmt = $pdo->prepare("
                            UPDATE products 
                            SET stock = stock - ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$item['quantity'], $item['product_id']]);
                    }

                    // Clear cart
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);

                    $pdo->commit();
                    
                    // Redirect to order confirmation
                    header("Location: order_confirmation.php?id=" . $order_id);
                    exit();
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Error in checkout: " . $e->getMessage());
                $_SESSION['error'] = 'Error processing your order. Please try again.';
            }
        }
    }
}

require_once 'includes/layout/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Checkout</h1>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php 
            echo htmlspecialchars($_SESSION['error']);
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Order Summary -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Order Summary</h2>
            
            <?php foreach ($cart_items as $item): ?>
                <div class="flex items-center justify-between py-4 border-b">
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

            <div class="mt-6 space-y-2">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal</span>
                    <span><?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Shipping</span>
                    <span><?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($shipping_cost, 0, ',', '.'); ?></span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t pt-2">
                    <span>Total</span>
                    <span><?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($total, 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- Shipping Address Selection -->
        <div>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Shipping Address</h2>

                <?php if (empty($addresses)): ?>
                    <p class="text-gray-600 mb-4">You don't have any shipping addresses.</p>
                    <a href="customer/manage_address.php" class="text-blue-600 hover:text-blue-800">
                        Add a shipping address
                    </a>
                <?php else: ?>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <?php foreach ($addresses as $address): ?>
                            <div class="mb-4">
                                <label class="block relative">
                                    <input type="radio" name="shipping_address" 
                                           value="<?php echo $address['id']; ?>"
                                           <?php echo ($default_address && $default_address['id'] == $address['id']) ? 'checked' : ''; ?>
                                           class="hidden peer" required>
                                    <div class="border rounded-lg p-4 cursor-pointer peer-checked:border-blue-500 peer-checked:ring-2 peer-checked:ring-blue-500">
                                        <div class="font-medium"><?php echo htmlspecialchars($address['name']); ?></div>
                                        <div class="text-gray-600 mt-1">
                                            <?php echo htmlspecialchars($address['phone']); ?>
                                        </div>
                                        <div class="text-gray-600 mt-1">
                                            <?php echo htmlspecialchars($address['address']); ?><br>
                                            <?php echo htmlspecialchars($address['city']); ?> <?php echo htmlspecialchars($address['postal_code']); ?>
                                        </div>
                                        <?php if ($address['is_default']): ?>
                                            <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mt-2">
                                                Default Address
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>

                        <div class="mt-6">
                            <button type="submit" 
                                    class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Place Order
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="text-center">
                <a href="cart.php" class="text-blue-600 hover:text-blue-800">
                    ← Return to Cart
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/layout/footer.php'; ?>
