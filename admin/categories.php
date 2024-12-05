<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

// Handle category deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $category_id = (int)$_POST['category_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            if ($stmt->execute([$category_id])) {
                $success = 'Category deleted successfully';
            }
        } catch(PDOException $e) {
            $error = 'Error deleting category. Make sure it has no associated products.';
        }
    }
}

// Handle category creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);

        try {
            if ($category_id) { // Update
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                if ($stmt->execute([$name, $description, $category_id])) {
                    $success = 'Category updated successfully';
                }
            } else { // Create
                $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                if ($stmt->execute([$name, $description])) {
                    $success = 'Category added successfully';
                }
            }
        } catch(PDOException $e) {
            $error = 'Error saving category';
        }
    }
}

// Get category by ID for editing
if (isset($_GET['edit'])) {
    $category_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();
        echo json_encode($category);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['error' => 'Error fetching category']);
        exit;
    }
}

// Get all categories
$categories = getAllCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">Manage Categories</h1>
            <button onclick="openModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Add New Category
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

        <!-- Categories List -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($category['name']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($category['description']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button onclick="editCategory(<?php echo $category['id']; ?>)" 
                                    class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                            <form action="categories.php" method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                <button type="submit" name="delete_category"
                                        class="text-red-600 hover:text-red-900"
                                        onclick="return confirm('Are you sure you want to delete this category?')">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div id="categoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 id="modalTitle" class="text-lg font-semibold">Add New Category</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form id="categoryForm" action="categories.php" method="POST" class="p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" id="category_id" name="category_id" value="">
                    
                    <div class="mb-4">
                        <label for="name" class="block text-gray-700 font-bold mb-2">Category Name</label>
                        <input type="text" id="name" name="name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                        <textarea id="description" name="description"
                                  class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"></textarea>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="button" onclick="closeModal()" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded mr-2 hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" name="save_category"
                                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Save Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function openModal() {
        document.getElementById('modalTitle').textContent = 'Add New Category';
        document.getElementById('categoryForm').reset();
        document.getElementById('category_id').value = '';
        document.getElementById('categoryModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('categoryModal').classList.add('hidden');
    }

    function editCategory(categoryId) {
        fetch(`categories.php?edit=${categoryId}`)
            .then(response => response.json())
            .then(category => {
                document.getElementById('modalTitle').textContent = 'Edit Category';
                document.getElementById('category_id').value = category.id;
                document.getElementById('name').value = category.name;
                document.getElementById('description').value = category.description;
                document.getElementById('categoryModal').classList.remove('hidden');
            })
            .catch(error => console.error('Error:', error));
    }

    // Close modal when clicking outside
    document.getElementById('categoryModal').addEventListener('click', function(event) {
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
    </script>
</body>
</html>
