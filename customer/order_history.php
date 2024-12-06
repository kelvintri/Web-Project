<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$orders = getUserOrders($user_id);

// Get any status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Filter orders if status is set
if ($status_filter) {
    $orders = array_filter($orders, function($order) use ($status_filter) {
        return $order['status'] === $status_filter;
    });
}

$page_title = "Order History";
require_once '../includes/layout/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Order History</h1>
        <a href="../index.php" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left"></i>
            Continue Shopping
        </a>
    </div>

    <!-- Status Filter -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <h2 class="text-lg font-semibold mb-3">Filter Orders</h2>
        <div class="flex flex-wrap gap-2">
            <a href="order_history.php" 
               class="px-4 py-2 rounded-full text-sm font-medium transition-colors
                      <?php echo $status_filter === '' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                All Orders
            </a>
            <?php
            $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
            foreach ($statuses as $status):
                $isActive = $status_filter === $status;
                $statusColors = [
                    'pending' => 'yellow',
                    'processing' => 'blue',
                    'shipped' => 'indigo',
                    'delivered' => 'green',
                    'cancelled' => 'red'
                ];
                $color = $statusColors[$status];
            ?>
                <a href="?status=<?php echo $status; ?>" 
                   class="px-4 py-2 rounded-full text-sm font-medium transition-colors
                          <?php echo $isActive 
                                ? "bg-{$color}-500 text-white" 
                                : "bg-gray-100 text-gray-700 hover:bg-gray-200"; ?>">
                    <?php echo ucfirst($status); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($orders)): ?>
    <div class="bg-white rounded-lg shadow-sm p-8 text-center">
        <div class="mb-4">
            <i class="fas fa-shopping-bag text-gray-400 text-5xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-800 mb-2">No Orders Found</h3>
        <p class="text-gray-600 mb-4">
            <?php echo $status_filter 
                ? "You don't have any " . $status_filter . " orders." 
                : "You haven't placed any orders yet."; ?>
        </p>
        <a href="../index.php" 
           class="inline-flex items-center gap-2 bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
            <i class="fas fa-shopping-cart"></i>
            Start Shopping
        </a>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="divide-y divide-gray-200">
            <?php foreach ($orders as $order): 
                $order_items = getOrderItems($order['id']);
                // Get the first item since we're only showing one
                $first_item = $order_items[0] ?? null;
            ?>
            <div class="p-4">
                <!-- Order Header -->
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-shopping-bag text-gray-400"></i>
                        <span class="text-sm text-gray-600">
                            <?php echo date('d M Y', strtotime($order['created_at'])); ?>
                        </span>
                        <span class="px-3 py-1 rounded-full text-xs font-medium
                            <?php echo match($order['status']) {
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'processing' => 'bg-blue-100 text-blue-800',
                                'shipped' => 'bg-indigo-100 text-indigo-800',
                                'delivered' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800'
                            }; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                    <div class="text-sm text-gray-600">
                        INV/<?php echo date('Ymd', strtotime($order['created_at'])); ?>/<?php echo $order['id']; ?>
                    </div>
                </div>

                <?php if ($first_item): ?>
                <!-- Product Info -->
                <div class="flex items-start gap-4">
                    <img src="<?php echo BASE_URL . '/' . $first_item['image_url']; ?>" 
                         alt="<?php echo htmlspecialchars($first_item['name']); ?>" 
                         class="w-16 h-16 object-cover rounded">
                    
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900">
                            <?php echo htmlspecialchars($first_item['name']); ?>
                        </h4>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php echo $first_item['quantity']; ?> item × <?php echo formatPrice($first_item['price']); ?>
                        </p>
                        <?php if (count($order_items) > 1): ?>
                            <p class="text-sm text-gray-500 mt-1">
                                +<?php echo count($order_items) - 1; ?> other products
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="text-right">
                        <p class="text-sm text-gray-600">Total</p>
                        <p class="font-medium text-gray-900">
                            <?php echo formatPrice($order['total_amount']); ?>
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end items-center gap-2 mt-4">
                    <button onclick='showOrderDetails(<?php echo json_encode($order); ?>, <?php echo json_encode($order_items); ?>)'
                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View Details
                    </button>
                    <?php if ($order['status'] === 'delivered'): ?>
                        <button class="bg-blue-500 text-white px-4 py-2 rounded text-sm hover:bg-blue-600">
                            Buy Again
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" style="backdrop-filter: blur(4px);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto relative">
            <div class="flex justify-between items-center p-6 border-b sticky top-0 bg-white z-10">
                <h3 class="text-lg font-semibold">Order Details</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalContent" class="relative">
                <!-- Content will be inserted here -->
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';

function showOrderDetails(order, orderItems) {
    console.log('Order:', order);
    console.log('Items:', orderItems);
    
    const modal = document.getElementById('orderModal');
    const modalContent = document.getElementById('modalContent');
    
    if (!modal || !modalContent) {
        console.error('Modal elements not found');
        return;
    }
    
    const statusClasses = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'processing': 'bg-blue-100 text-blue-800',
        'shipped': 'bg-indigo-100 text-indigo-800',
        'delivered': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800'
    };

    const content = `
        <div class="p-6 border-b">
            <div class="flex flex-wrap gap-6 justify-between items-start">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-lg font-semibold">Order #${order.id}</h3>
                        <span class="px-3 py-1 rounded-full text-sm font-medium ${statusClasses[order.status]}">
                            ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                        </span>
                    </div>
                    <p class="text-gray-600">
                        <i class="far fa-calendar-alt mr-2"></i>
                        ${new Date(order.created_at).toLocaleString()}
                    </p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <h4 class="font-semibold mb-4">Order Items</h4>
            <div class="divide-y">
                ${orderItems.map(item => `
                    <div class="flex items-center gap-4 py-4">
                        <img src="${BASE_URL}/${item.image_url}" 
                             alt="${item.name}" 
                             class="w-20 h-20 object-cover rounded-lg">
                        <div class="flex-1">
                            <h5 class="font-medium">${item.name}</h5>
                            <p class="text-gray-600 mt-1">
                                ${formatPrice(item.price)} × ${item.quantity}
                            </p>
                        </div>
                        <div class="text-right font-semibold">
                            ${formatPrice(item.price * item.quantity)}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>

        <div class="bg-gray-50 p-6 border-t">
            <div class="max-w-sm ml-auto space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal</span>
                    <span>${formatPrice(order.total_amount - order.shipping_cost)}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Shipping</span>
                    <span>${formatPrice(order.shipping_cost)}</span>
                </div>
                <div class="flex justify-between font-semibold text-lg pt-2 border-t">
                    <span>Total</span>
                    <span>${formatPrice(order.total_amount)}</span>
                </div>
            </div>
        </div>

        <div class="p-6 bg-gray-50 border-t">
            <h4 class="font-semibold mb-4">Shipping Details</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-600 mb-1">Recipient Name</p>
                    <p class="font-medium">${order.shipping_name}</p>
                </div>
                <div>
                    <p class="text-gray-600 mb-1">Phone Number</p>
                    <p class="font-medium">${order.phone}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-gray-600 mb-1">Delivery Address</p>
                    <p class="font-medium">
                        ${order.address}<br>
                        ${order.city}, ${order.postal_code}
                    </p>
                </div>
            </div>
        </div>
    `;

    modalContent.innerHTML = content;
    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById('orderModal').classList.add('hidden');
}

function formatPrice(amount) {
    return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Close modal when clicking outside
document.getElementById('orderModal').addEventListener('click', function(event) {
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

<?php include '../includes/layout/footer.php'; ?>
