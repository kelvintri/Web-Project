<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch(PDOException $e) {
    $error = 'Error fetching user data';
}

// Get user's addresses
$addresses = getUserAddresses($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - E-Commerce Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold mb-8">My Profile</h1>

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

            <!-- Profile Navigation -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <nav class="flex border-b">
                    <button onclick="showTab('profile')" 
                            class="tab-btn px-6 py-3 text-gray-600 hover:text-gray-800 focus:outline-none active">
                        Account Details
                    </button>
                    <button onclick="showTab('addresses')" 
                            class="tab-btn px-6 py-3 text-gray-600 hover:text-gray-800 focus:outline-none">
                        Shipping Addresses
                    </button>
                    <button onclick="showTab('orders')" 
                            class="tab-btn px-6 py-3 text-gray-600 hover:text-gray-800 focus:outline-none">
                        Order History
                    </button>
                </nav>

                <!-- Profile Tab -->
                <div id="profile-tab" class="tab-content p-6">
                    <div class="max-w-xl">
                        <div class="mb-4">
                            <label class="block text-gray-700 font-bold mb-2">Username</label>
                            <p class="text-gray-800"><?php echo htmlspecialchars($user['username']); ?></p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 font-bold mb-2">Email</label>
                            <p class="text-gray-800"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 font-bold mb-2">Member Since</label>
                            <p class="text-gray-800"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Addresses Tab -->
                <div id="addresses-tab" class="tab-content p-6 hidden">
                    <button onclick="toggleAddressForm()" 
                            class="mb-6 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 focus:outline-none">
                        Add New Address
                    </button>

                    <!-- Add Address Form (Hidden by default) -->
                    <div id="address-form" class="hidden mb-8">
                        <form action="manage_address.php" method="POST" class="max-w-xl bg-gray-50 p-6 rounded-lg">
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

                            <div class="mt-4 flex justify-end space-x-2">
                                <button type="button" onclick="toggleAddressForm()"
                                        class="px-4 py-2 text-gray-600 hover:text-gray-800">
                                    Cancel
                                </button>
                                <button type="submit" name="add_address"
                                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                    Save Address
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Saved Addresses -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($addresses as $address): ?>
                        <div class="bg-white border rounded-lg p-4 relative">
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
                                    <form action="manage_address.php" method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                        <button type="submit" name="set_default" 
                                                class="text-blue-600 hover:text-blue-800">
                                            Set as Default
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form action="manage_address.php" method="POST" class="inline">
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

                <!-- Orders Tab -->
                <div id="orders-tab" class="tab-content p-6 hidden">
                    <!-- Order history will be implemented later -->
                    <p class="text-gray-500 text-center">Order history will be displayed here.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Add active class to clicked button
            event.currentTarget.classList.add('active');
        }

        function toggleAddressForm() {
            const form = document.getElementById('address-form');
            form.classList.toggle('hidden');
        }
    </script>

    <style>
        .tab-btn.active {
            color: #1a56db;
            border-bottom: 2px solid #1a56db;
        }
    </style>
</body>
</html>
