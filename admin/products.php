<?php
require_once '../includes/functions.php';
require_once '../config/config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $product_id = (int)$_POST['product_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            if ($stmt->execute([$product_id])) {
                $success = 'Product deleted successfully';
            }
        } catch(PDOException $e) {
            $error = 'Error deleting product';
        }
    }
}

// Handle product creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $category_id = (int)$_POST['category_id'] ?: null;

        // Handle image upload
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $error = 'Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.';
            } else {
                $filename = uniqid() . '.' . $file_extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                    $image_url = 'uploads/' . $filename;
                } else {
                    $error = 'Error uploading file';
                }
            }
        }

        if (!$error) {
            try {
                if ($product_id) { // Update
                    $sql = "UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category_id = ?";
                    $params = [$name, $description, $price, $stock, $category_id];
                    
                    if ($image_url) {
                        $sql .= ", image_url = ?";
                        $params[] = $image_url;
                    }
                    
                    $sql .= " WHERE id = ?";
                    $params[] = $product_id;
                    
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute($params)) {
                        $success = 'Product updated successfully';
                    }
                } else { // Create
                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$name, $description, $price, $stock, $category_id, $image_url])) {
                        $success = 'Product added successfully';
                    }
                }
            } catch(PDOException $e) {
                $error = 'Error saving product';
            }
        }
    }
}

// Get product by ID for editing
if (isset($_GET['edit'])) {
    $product_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        echo json_encode($product);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Error fetching product']);
        exit;
    }
}

// Add this near the top of the file, after getting $products and $categories
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : '';

// Modify the getAllProducts function call to include search and filter
$products = getAllProducts($search, $category_filter);
$categories = getAllCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">Manage Products</h1>
            <button onclick="openModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Add New Product
            </button>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <form action="products.php" method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Products</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Search by name or description"
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                </div>
                <div class="md:w-48">
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Filter by Category</label>
                    <select id="category" name="category"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Search
                    </button>
                    <?php if ($search || $category_filter): ?>
                        <a href="products.php" class="ml-2 text-gray-600 hover:text-gray-800 flex items-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (empty($products)): ?>
            <div class="bg-gray-50 rounded-lg p-8 text-center">
                <p class="text-gray-600">No products found<?php echo $search ? ' for "' . htmlspecialchars($search) . '"' : ''; ?></p>
            </div>
        <?php else: ?>
            <!-- Existing table code here -->
            <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($product['image_url']): ?>
                                    <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="h-10 w-10 object-cover rounded">
                                <?php else: ?>
                                    <div class="h-10 w-10 bg-gray-200 rounded flex items-center justify-center">
                                        <span class="text-gray-500 text-xs">No image</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($product['name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo CURRENCY_SYMBOL; ?> <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $product['stock']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="editProduct(<?php echo $product['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                <form action="products.php" method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" name="delete_product" 
                                            class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Are you sure you want to delete this product?')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="productModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full my-8">
                <div class="flex justify-between items-center p-4 border-b sticky top-0 bg-white">
                    <h3 id="modalTitle" class="text-lg font-semibold">Add New Product</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="max-h-[calc(100vh-8rem)] overflow-y-auto">
                    <form id="productForm" action="products.php" method="POST" enctype="multipart/form-data" class="p-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" id="product_id" name="product_id" value="">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-4">
                                <label for="name" class="block text-gray-700 font-bold mb-2">Product Name</label>
                                <input type="text" id="name" name="name" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                            </div>

                            <div class="mb-4">
                                <label for="category_id" class="block text-gray-700 font-bold mb-2">Category</label>
                                <select id="category_id" name="category_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="price" class="block text-gray-700 font-bold mb-2">Price</label>
                                <input type="number" id="price" name="price" required min="0" step="0.01"
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                            </div>

                            <div class="mb-4">
                                <label for="stock" class="block text-gray-700 font-bold mb-2">Stock</label>
                                <input type="number" id="stock" name="stock" required min="0"
                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                            <textarea id="description" name="description" rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="image" class="block text-gray-700 font-bold mb-2">Product Image</label>
                            <input type="file" id="image" name="image" accept="image/*"
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                            <div id="currentImage" class="mt-2 hidden">
                                <img src="" alt="Current product image" class="h-32 object-cover rounded">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="button" onclick="closeModal()" 
                                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded mr-2 hover:bg-gray-400">
                                Cancel
                            </button>
                            <button type="submit" name="save_product"
                                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Save Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openModal() {
        document.getElementById('modalTitle').textContent = 'Add New Product';
        document.getElementById('productForm').reset();
        document.getElementById('product_id').value = '';
        document.getElementById('currentImage').classList.add('hidden');
        document.getElementById('productModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('productModal').classList.add('hidden');
    }

    function editProduct(productId) {
        fetch(`products.php?edit=${productId}`)
            .then(response => response.json())
            .then(product => {
                document.getElementById('modalTitle').textContent = 'Edit Product';
                document.getElementById('product_id').value = product.id;
                document.getElementById('name').value = product.name;
                document.getElementById('description').value = product.description;
                document.getElementById('price').value = product.price;
                document.getElementById('stock').value = product.stock;
                document.getElementById('category_id').value = product.category_id || '';

                // Handle image preview
                const currentImage = document.getElementById('currentImage');
                if (product.image_url) {
                    currentImage.querySelector('img').src = '../' + product.image_url;
                    currentImage.classList.remove('hidden');
                } else {
                    currentImage.classList.add('hidden');
                }

                document.getElementById('productModal').classList.remove('hidden');
            })
            .catch(error => console.error('Error:', error));
    }

    // Close modal when clicking outside
    document.getElementById('productModal').addEventListener('click', function(event) {
        if (event.target === this) {
            closeModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.querySelector('form[action="products.php"]');
        const searchInput = document.getElementById('search');
        const categorySelect = document.getElementById('category');
        let searchTimeout;

        // Handle search input with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchForm.submit();
            }, 500);
        });

        // Handle category change
        categorySelect.addEventListener('change', function() {
            searchForm.submit();
        });
    });
    </script>
</body>
</html>
