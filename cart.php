<?php
require_once 'includes/functions.php';
require_once 'config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$page_title = 'Shopping Cart';
$active_page = 'cart';

// Handle quantity updates and item removal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid request';
    } else {
        if (isset($_POST['update_cart'])) {
            foreach ($_POST['quantity'] as $product_id => $quantity) {
                if ($quantity > 0) {
                    updateCartItemQuantity($_SESSION['user_id'], $product_id, $quantity);
                } else {
                    removeFromCart($_SESSION['user_id'], $product_id);
                }
            }
            $_SESSION['success'] = 'Cart updated successfully';
        } elseif (isset($_POST['remove_item'])) {
            $product_id = (int)$_POST['product_id'];
            removeFromCart($_SESSION['user_id'], $product_id);
            $_SESSION['success'] = 'Item removed from cart';
        }
    }
    header('Location: cart.php');
    exit();
}

// Get cart items with product details
$cart_items = getCartItems($_SESSION['user_id']);

require_once 'includes/layout/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Shopping Cart</h1>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php 
            echo htmlspecialchars($_SESSION['success']);
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php 
            echo htmlspecialchars($_SESSION['error']);
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($cart_items)): ?>
        <form action="cart.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subtotal</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $total = 0;
                        foreach ($cart_items as $item): 
                            $subtotal = $item['price'] * $item['quantity'];
                            $total += $subtotal;
                        ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 class="h-16 w-16 object-cover rounded">
                                        <?php endif; ?>
                                        <div class="ml-4">
                                            <a href="product.php?id=<?php echo $item['product_id']; ?>" 
                                               class="text-gray-900 font-medium hover:text-blue-600">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($item['price'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <input type="number" name="quantity[<?php echo $item['product_id']; ?>]" 
                                           value="<?php echo $item['quantity']; ?>" min="0" max="99"
                                           class="w-20 px-2 py-1 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                                </td>
                                <td class="px-6 py-4 font-medium">
                                    <?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($subtotal, 0, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <form action="cart.php" method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <button type="submit" name="remove_item" 
                                                class="text-red-600 hover:text-red-900"
                                                onclick="return confirm('Are you sure you want to remove this item?')">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-right font-medium">Total:</td>
                            <td class="px-6 py-4 font-bold text-lg">
                                <?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($total, 0, ',', '.'); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <button type="submit" name="update_cart" 
                        class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                    Update Cart
                </button>
                <a href="checkout.php" 
                   class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Proceed to Checkout
                </a>
            </div>
        </form>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow-md p-6 text-center">
            <div class="text-gray-500 mb-4">Your cart is empty</div>
            <a href="index.php" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                Continue Shopping
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/layout/footer.php'; ?>
