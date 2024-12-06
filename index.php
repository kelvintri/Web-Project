<?php
$page_title = 'Home';
$active_page = 'home';
require_once 'includes/layout/header.php';

// Get filters from URL
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = ITEMS_PER_PAGE;

// Build the query
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE 1=1";
$params = [];

if ($category_id) {
    $query .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if ($search) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
}

// Add sorting
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'name':
        $query .= " ORDER BY p.name ASC";
        break;
    default: // newest
        $query .= " ORDER BY p.created_at DESC";
}

// Get products first (without pagination)
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    $total_products = count($products);
    $total_pages = ceil($total_products / $per_page);

    // Apply pagination to products array
    $offset = ($page - 1) * $per_page;
    $products = array_slice($products, $offset, $per_page);

} catch(PDOException $e) {
    error_log("Error in products query: " . $e->getMessage());
    $products = [];
    $total_products = 0;
    $total_pages = 0;
}
?>

<!-- Category Pills -->
<div class="container mx-auto px-4 mb-8">
    <div class="flex flex-wrap gap-2">
        <a href="index.php" 
           class="px-4 py-2 rounded-full <?php echo !$category_id ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
            All Products
        </a>
        <?php foreach (getAllCategories() as $category): ?>
            <a href="index.php?category=<?php echo $category['id']; ?>" 
               class="px-4 py-2 rounded-full <?php echo $category_id === $category['id'] ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
                <?php echo htmlspecialchars($category['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Filters and Sort -->
<div class="container mx-auto px-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-4">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <label for="sort" class="text-gray-700">Sort by:</label>
                <select id="sort" name="sort" onchange="updateFilters()"
                        class="border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name</option>
                </select>
            </div>
            
            <?php if ($total_products > 0): ?>
                <p class="text-gray-600">
                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_products); ?> 
                    of <?php echo $total_products; ?> products
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Products Grid -->
<?php if (!empty($products)): ?>
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($products as $product): ?>
                <div class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group relative flex flex-col h-full">
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="block flex-1">
                        <!-- Image Container -->
                        <div class="relative pt-[100%] overflow-hidden bg-gray-100">
                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="absolute inset-0 w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500">
                            
                            <!-- Badges -->
                            <div class="absolute top-3 left-3 right-3 flex justify-between items-start">
                                <?php if ($product['stock'] < 5): ?>
                                    <span class="bg-red-500 text-white px-3 py-1 rounded-full text-xs font-medium">
                                        Only <?php echo $product['stock']; ?> left
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (strtotime($product['created_at']) > strtotime('-7 days')): ?>
                                    <span class="bg-green-500 text-white px-3 py-1 rounded-full text-xs font-medium">
                                        New
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Product Info -->
                        <div class="p-4 flex-1 flex flex-col">
                            <!-- Category -->
                            <div class="mb-2">
                                <span class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </div>
                            
                            <!-- Product Name -->
                            <h3 class="text-lg font-semibold text-gray-800 group-hover:text-blue-600 transition-colors">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                        </div>
                    </a>
                    
                    <!-- Price and Action (Outside of the anchor tag) -->
                    <div class="p-4 border-t bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-2xl font-bold text-gray-900">
                                    <?php echo formatPrice($product['price']); ?>
                                </span>
                                <?php if ($product['stock'] > 0): ?>
                                    <p class="text-sm text-gray-500 mt-1">
                                        In Stock
                                    </p>
                                <?php else: ?>
                                    <p class="text-sm text-red-500 mt-1">
                                        Out of Stock
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isLoggedIn() && $product['stock'] > 0): ?>
                                <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg 
                                               flex items-center gap-2 transition-colors duration-300
                                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span>Add</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <div class="container mx-auto px-4">
        <div class="bg-white rounded-lg shadow-sm p-8 text-center">
            <i class="fas fa-box-open text-gray-400 text-5xl mb-4"></i>
            <p class="text-gray-500 text-lg mb-4">No products found</p>
            <?php if ($search || $category_id): ?>
                <p class="text-gray-400 mb-4">Try adjusting your search or filter criteria</p>
                <a href="index.php" class="inline-block bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                    View all products
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="container mx-auto px-4 mt-8">
        <nav class="flex justify-center" aria-label="Pagination">
            <ul class="flex items-center space-x-1">
                <?php if ($page > 1): ?>
                    <li>
                        <a href="index.php?page=<?php echo $page - 1; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" 
                           class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li>
                        <?php if ($i === $page): ?>
                            <span class="px-4 py-2 rounded-lg bg-blue-500 text-white">
                                <?php echo $i; ?>
                            </span>
                        <?php else: ?>
                            <a href="index.php?page=<?php echo $i; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" 
                               class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li>
                        <a href="index.php?page=<?php echo $page + 1; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" 
                           class="px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
<?php endif; ?>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-4 right-4 transform translate-y-full opacity-0 transition-all duration-300">
    <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
        <p class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span id="toastMessage">Item added to cart</span>
        </p>
    </div>
</div>

<script>
function showToast(message) {
    const toast = document.getElementById('toast');
    document.getElementById('toastMessage').textContent = message;
    toast.classList.remove('translate-y-full', 'opacity-0');
    
    setTimeout(() => {
        toast.classList.add('translate-y-full', 'opacity-0');
    }, 3000);
}

function updateFilters() {
    const sort = document.getElementById('sort').value;
    const urlParams = new URLSearchParams(window.location.search);
    
    urlParams.set('sort', sort);
    if (!urlParams.has('page')) {
        urlParams.set('page', '1');
    }
    
    window.location.href = 'index.php?' + urlParams.toString();
}

function addToCart(productId) {
    fetch('api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?php echo generateCSRFToken(); ?>'
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Item added to cart');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.error || 'Error adding to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error adding to cart');
    });
}
</script>

<?php require_once 'includes/layout/footer.php'; ?>
