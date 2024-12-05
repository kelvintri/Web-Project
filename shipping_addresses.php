<?php
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $name = sanitizeInput($_POST['name']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);
        $city = sanitizeInput($_POST['city']);
        $postal_code = sanitizeInput($_POST['postal_code']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        try {
            // If setting as default, unset other default addresses
            if ($is_default) {
                $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = 0 WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            }

            // Insert new address
            $stmt = $pdo->prepare("
                INSERT INTO shipping_addresses (user_id, name, phone, address, city, postal_code, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$_SESSION['user_id'], $name, $phone, $address, $city, $postal_code, $is_default])) {
                $success = 'Shipping address added successfully';
            }
        } catch(PDOException $e) {
            $error = 'Error saving shipping address';
        }
    }
}

// Handle delete address
if (isset($_POST['delete_address'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $address_id = (int)$_POST['address_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM shipping_addresses WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$address_id, $_SESSION['user_id']])) {
                $success = 'Address deleted successfully';
            }
        } catch(PDOException $e) {
            $error = 'Error deleting address';
        }
    }
}

// Handle set default address
if (isset($_POST['set_default'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $address_id = (int)$_POST['address_id'];
        try {
            // First, unset all default addresses
            $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Set the selected address as default
            $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = 1 WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$address_id, $_SESSION['user_id']])) {
                $success = 'Default address updated successfully';
            }
        } catch(PDOException $e) {
            $error = 'Error updating default address';
        }
    }
}

// Get user's shipping addresses
try {
    $stmt = $pdo->prepare("SELECT * FROM shipping_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $addresses = $stmt->fetchAll();
} catch(PDOException $e) {
    $addresses = [];
    $error = 'Error fetching addresses';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Shipping Addresses - E-Commerce Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">My Shipping Addresses</h1>

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

        <!-- Add New Address Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Add New Address</h2>
            <form action="shipping_addresses.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-gray-700 font-bold mb-2">Full Name</label>
                        <input type="text" id="name" name="name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label for="phone" class="block text-gray-700 font-bold mb-2">Phone Number</label>
                        <input type="tel" id="phone" name="phone" required
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="md:col-span-2">
                        <label for="address" class="block text-gray-700 font-bold mb-2">Address</label>
                        <textarea id="address" name="address" required rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"></textarea>
                    </div>

                    <div>
                        <label for="city" class="block text-gray-700 font-bold mb-2">City</label>
                        <input type="text" id="city" name="city" required
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label for="postal_code" class="block text-gray-700 font-bold mb-2">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" required
                               class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="md:col-span-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_default" value="1" class="mr-2">
                            <span class="text-gray-700">Set as default shipping address</span>
                        </label>
                    </div>
                </div>

                <button type="submit" 
                        class="mt-4 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 focus:outline-none">
                    Add Address
                </button>
            </form>
        </div>

        <!-- Saved Addresses -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($addresses as $address): ?>
            <div class="bg-white rounded-lg shadow-md p-6 relative">
                <?php if ($address['is_default']): ?>
                    <span class="absolute top-2 right-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Default</span>
                <?php endif; ?>

                <h3 class="font-semibold mb-2"><?php echo htmlspecialchars($address['name']); ?></h3>
                <p class="text-gray-600 mb-1"><?php echo htmlspecialchars($address['phone']); ?></p>
                <p class="text-gray-600 mb-1"><?php echo htmlspecialchars($address['address']); ?></p>
                <p class="text-gray-600 mb-4">
                    <?php echo htmlspecialchars($address['city']); ?>, 
                    <?php echo htmlspecialchars($address['postal_code']); ?>
                </p>

                <div class="flex space-x-2">
                    <?php if (!$address['is_default']): ?>
                        <form action="shipping_addresses.php" method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                            <button type="submit" name="set_default" 
                                    class="text-blue-600 hover:text-blue-800">
                                Set as Default
                            </button>
                        </form>
                    <?php endif; ?>

                    <form action="shipping_addresses.php" method="POST" class="inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                        <button type="submit" name="delete_address" 
                                class="text-red-600 hover:text-red-800"
                                onclick="return confirm('Are you sure you want to delete this address?')">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($addresses)): ?>
            <p class="text-gray-500 text-center">No shipping addresses saved yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
