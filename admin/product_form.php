<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';
$product = [
    'id' => '',
    'name' => '',
    'description' => '',
    'price' => '',
    'stock' => '',
    'category_id' => '',
    'image_url' => ''
];

// Get categories for dropdown
$categories = getAllCategories();

// Edit mode
if (isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    $product = getProductById($product_id);
    if (!$product) {
        header('Location: products.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $price = (float)$_POST['price'];
        $stock = (int)$_POST['stock'];
        $category_id = (int)$_POST['category_id'];
        
        // Handle image upload
        $image_url = $product['image_url']; // Keep existing image by default
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
                if ($product['id']) { // Update existing product
                    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, image_url = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $price, $stock, $category_id ?: null, $image_url, $product['id']]);
                    $success = 'Product updated successfully';
                } else { // Insert new product
                    $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $description, $price, $stock, $category_id ?: null, $image_url]);
                    $success = 'Product added successfully';
                }
                
                if ($success) {
                    header('Location: products.php');
                    exit();
                }
            } catch(PDOException $e) {
                $error = 'Error saving product';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['id'] ? 'Edit' : 'Add'; ?> Product - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold mb-8"><?php echo $product['id'] ? 'Edit' : 'Add'; ?> Product</h1>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="product_form.php<?php echo $product['id'] ? '?id='.$product['id'] : ''; ?>" 
                  method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-bold mb-2">Product Name</label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo htmlspecialchars($product['name']); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                    <textarea id="description" name="description" rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>

                <div class="mb-4">
                    <label for="price" class="block text-gray-700 font-bold mb-2">Price</label>
                    <input type="number" id="price" name="price" step="0.01" required
                           value="<?php echo $product['price']; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                </div>

                <div class="mb-4">
                    <label for="stock" class="block text-gray-700 font-bold mb-2">Stock</label>
                    <input type="number" id="stock" name="stock" required
                           value="<?php echo $product['stock']; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                </div>

                <div class="mb-4">
                    <label for="category_id" class="block text-gray-700 font-bold mb-2">Category</label>
                    <select id="category_id" name="category_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"
                                    <?php echo $category['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-6">
                    <label for="image" class="block text-gray-700 font-bold mb-2">Product Image</label>
                    <?php if ($product['image_url']): ?>
                        <div class="mb-2">
                            <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                 alt="Current product image" class="h-32 object-cover rounded">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/*"
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit" 
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 focus:outline-none">
                        <?php echo $product['id'] ? 'Update' : 'Add'; ?> Product
                    </button>
                    <a href="products.php" class="text-gray-600 hover:text-gray-800">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
