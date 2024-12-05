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

<!-- Filters and Sorting -->
<div class="flex flex-col sm:flex-row justify-between items-center mb-6">
    <div class="flex items-center space-x-4 mb-4 sm:mb-0">
        <label for="sort" class="text-gray-700">Sort by:</label>
        <select id="sort" name="sort" onchange="updateFilters()"
                class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
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

<!-- Products Grid -->
<?php if (!empty($products)): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($products as $product): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <a href="product.php?id=<?php echo $product['id']; ?>">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         class="w-full h-48 object-cover">
                    
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </h3>
                        
                        <p class="text-gray-600 text-sm mb-2">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </p>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold text-gray-900">
                                <?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($product['price'], 0, ',', '.'); ?>
                            </span>
                            
                            <?php if (isLoggedIn()): ?>
                                <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Add to Cart
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="text-center py-12">
        <i class="fas fa-box-open text-gray-400 text-5xl mb-4"></i>
        <p class="text-gray-500 text-lg">No products found</p>
        <?php if ($search || $category_id): ?>
            <p class="text-gray-400 mt-2">Try adjusting your search or filter criteria</p>
            <a href="index.php" class="inline-block mt-4 text-blue-500 hover:text-blue-600">
                View all products
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="flex justify-center mt-8">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a href="index.php?page=<?php echo $page - 1; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" 
                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600">
                        <?php echo $i; ?>
                    </span>
                <?php else: ?>
                    <a href="index.php?page=<?php echo $i; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="index.php?page=<?php echo $page + 1; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $sort ? '&sort=' . $sort : ''; ?>" 
                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </nav>
    </div>
<?php endif; ?>

<script>
function updateFilters() {
    const sort = document.getElementById('sort').value;
    const urlParams = new URLSearchParams(window.location.search);
    
    urlParams.set('sort', sort);
    
    // Keep other parameters
    if (!urlParams.has('page')) {
        urlParams.set('page', '1');
    }
    
    window.location.href = 'index.php?' + urlParams.toString();
}

function addToCart(productId) {
    // We'll implement this later with AJAX
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
            // Update cart count
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
