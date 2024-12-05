<?php
require_once '../includes/functions.php';
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Invalid request';
        header('Location: manage_address.php');
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $address_id = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
        $name = sanitizeInput($_POST['name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $postal_code = sanitizeInput($_POST['postal_code'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        // Validate inputs
        if (empty($name) || empty($phone) || empty($address) || empty($city) || empty($postal_code)) {
            $_SESSION['error'] = 'All fields are required';
        } else {
            $data = [
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'city' => $city,
                'postal_code' => $postal_code,
                'is_default' => $is_default
            ];

            if ($action === 'add') {
                if (addAddress($_SESSION['user_id'], $data)) {
                    $_SESSION['success'] = 'Address added successfully';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $_SESSION['error'] = 'Error adding address. Please try again.';
                }
            } else {
                if (updateAddress($address_id, $_SESSION['user_id'], $data)) {
                    $_SESSION['success'] = 'Address updated successfully';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $_SESSION['error'] = 'Error updating address. Please try again.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $address_id = (int)$_POST['address_id'];
        if (deleteAddress($address_id, $_SESSION['user_id'])) {
            $_SESSION['success'] = 'Address deleted successfully';
        } else {
            $_SESSION['error'] = 'Error deleting address. Please try again.';
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get user's addresses
$addresses = getUserAddresses($_SESSION['user_id']);

$page_title = 'Manage Addresses';
require_once '../includes/layout/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Manage Shipping Addresses</h1>
            <button onclick="showAddAddressForm()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Add New Address
            </button>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Address List -->
        <div class="bg-white rounded-lg shadow-md divide-y">
            <?php if (empty($addresses)): ?>
                <div class="p-6 text-center text-gray-500">
                    You haven't added any shipping addresses yet.
                </div>
            <?php else: ?>
                <?php foreach ($addresses as $address): ?>
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($address['name']); ?>
                                    <?php if ($address['is_default']): ?>
                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            Default
                                        </span>
                                    <?php endif; ?>
                                </h3>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($address['phone']); ?></p>
                                <p class="text-gray-600 mt-1">
                                    <?php echo htmlspecialchars($address['address']); ?><br>
                                    <?php echo htmlspecialchars($address['city']); ?> <?php echo htmlspecialchars($address['postal_code']); ?>
                                </p>
                            </div>
                            <div class="flex space-x-3">
                                <button onclick="editAddress(<?php echo htmlspecialchars(json_encode($address)); ?>)" 
                                        class="text-blue-600 hover:text-blue-800">
                                    Edit
                                </button>
                                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="inline" 
                                      onsubmit="return confirm('Are you sure you want to delete this address?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Address Form Modal -->
<div id="addressModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <form id="addressForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="p-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="address_id" id="address_id" value="">

                <h2 id="modalTitle" class="text-xl font-semibold mb-4">Add New Address</h2>

                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" id="name" name="name" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="tel" id="phone" name="phone" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea id="address" name="address" rows="2" required
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                        <input type="text" id="city" name="city" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" id="is_default" name="is_default"
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_default" class="ml-2 block text-sm text-gray-900">
                            Set as default shipping address
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="hideAddressModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Save Address
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAddAddressForm() {
    document.getElementById('modalTitle').textContent = 'Add New Address';
    document.getElementById('addressForm').reset();
    document.getElementById('addressForm').action.value = 'add';
    document.getElementById('address_id').value = '';
    document.getElementById('addressModal').classList.remove('hidden');
}

function hideAddressModal() {
    document.getElementById('addressModal').classList.add('hidden');
}

function editAddress(address) {
    document.getElementById('modalTitle').textContent = 'Edit Address';
    document.getElementById('addressForm').elements['action'].value = 'edit';
    document.getElementById('address_id').value = address.id;
    document.getElementById('name').value = address.name;
    document.getElementById('phone').value = address.phone;
    document.getElementById('address').value = address.address;
    document.getElementById('city').value = address.city;
    document.getElementById('postal_code').value = address.postal_code;
    document.getElementById('is_default').checked = address.is_default == 1;
    document.getElementById('addressModal').classList.remove('hidden');
}

// Close modal when clicking outside
document.getElementById('addressModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideAddressModal();
    }
});
</script>

<?php require_once '../includes/layout/footer.php'; ?>
