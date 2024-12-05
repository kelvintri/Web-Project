<?php
require_once 'includes/functions.php';
require_once 'config/config.php';

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = getProductById($product_id);

// If product not found, redirect to homepage
if (!$product) {
    header('Location: index.php');
    exit();
}

$page_title = $product['name'];
require_once 'includes/layout/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <nav class="text-gray-600 mb-8" aria-label="Breadcrumb">
        <ol class="list-none p-0 inline-flex">
            <li class="flex items-center">
                <a href="index.php" class="hover:text-blue-600">Home</a>
                <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                    <path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/>
                </svg>
            </li>
            <?php if ($product['category_name']): ?>
            <li class="flex items-center">
                <a href="index.php?category=<?php echo $product['category_id']; ?>" class="hover:text-blue-600">
                    <?php echo htmlspecialchars($product['category_name']); ?>
                </a>
                <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                    <path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/>
                </svg>
            </li>
            <?php endif; ?>
            <li>
                <span class="text-gray-800" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></span>
            </li>
        </ol>
    </nav>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Product Image -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <?php if ($product['image_url']): ?>
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     class="w-full h-96 object-cover">
            <?php else: ?>
                <div class="w-full h-96 bg-gray-200 flex items-center justify-center">
                    <span class="text-gray-500">No image available</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Product Details -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">
                <?php echo htmlspecialchars($product['name']); ?>
            </h1>

            <?php if ($product['category_name']): ?>
                <p class="text-gray-600 mb-4">
                    Category: 
                    <a href="index.php?category=<?php echo $product['category_id']; ?>" 
                       class="text-blue-600 hover:text-blue-800">
                        <?php echo htmlspecialchars($product['category_name']); ?>
                    </a>
                </p>
            <?php endif; ?>

            <div class="text-2xl font-bold text-gray-900 mb-6">
                <?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($product['price'], 0, ',', '.'); ?>
            </div>

            <div class="prose max-w-none mb-6">
                <p class="text-gray-600">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </p>
            </div>

            <div class="mb-6">
                <p class="text-gray-600">
                    Stock: 
                    <span class="font-semibold <?php echo $product['stock'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $product['stock'] > 0 ? $product['stock'] . ' units' : 'Out of stock'; ?>
                    </span>
                </p>
            </div>

            <?php if (isLoggedIn()): ?>
                <?php if ($product['stock'] > 0): ?>
                    <div class="flex items-center gap-4">
                        <div class="w-24">
                            <label for="quantity" class="sr-only">Quantity</label>
                            <input type="number" id="quantity" min="1" max="<?php echo $product['stock']; ?>" value="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                        </div>
                        <button onclick="addToCart(<?php echo $product['id']; ?>)"
                                class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Add to Cart
                        </button>
                    </div>
                <?php else: ?>
                    <button disabled
                            class="w-full bg-gray-300 text-gray-500 px-6 py-3 rounded-lg cursor-not-allowed">
                        Out of Stock
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <a href="login.php" 
                   class="block text-center bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
                    Login to Purchase
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function addToCart(productId) {
    const quantity = parseInt(document.getElementById('quantity').value);
    if (isNaN(quantity) || quantity < 1) {
        alert('Please enter a valid quantity');
        return;
    }

    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?php echo generateCSRFToken(); ?>'
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the page to update cart count
            location.reload();
        } else {
            alert(data.error || 'Error adding to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding to cart');
    });
}
</script>

<?php require_once 'includes/layout/footer.php'; ?>
