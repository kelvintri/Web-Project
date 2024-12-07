<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/functions.php';

// Define BASE_URL constant if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://final.test');
}

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$success = '';
$error = '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['order_ids'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $action = sanitizeInput($_POST['bulk_action']);
        $order_ids = array_map('intval', $_POST['order_ids']);
        
        if (!empty($order_ids)) {
            try {
                $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id IN ($placeholders)");
                $params = array_merge([$action], $order_ids);
                if ($stmt->execute($params)) {
                    $success = 'Selected orders have been updated successfully';
                }
            } catch(PDOException $e) {
                $error = 'Error updating orders: ' . $e->getMessage();
            }
        }
    }
}

// Handle single order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
    } else {
        $order_id = (int)$_POST['order_id'];
        $status = sanitizeInput($_POST['status']);
        
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            if ($stmt->execute([$status, $order_id])) {
                $success = 'Order status updated successfully';
            }
        } catch(PDOException $e) {
            $error = 'Error updating order status: ' . $e->getMessage();
        }
    }
}

// Get order statistics
try {
    $stats_query = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
            COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped_orders,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
        FROM orders
    ";
    $stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $stats = [];
    $error = 'Error fetching statistics: ' . $e->getMessage();
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Date', 'Customer', 'Email', 'Items', 'Total', 'Status']);
    
    // Export all orders without pagination
    $stmt = $pdo->query(buildOrderQuery(false));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['username'],
            $row['email'],
            $row['item_count'],
            $row['total_amount'],
            $row['status']
        ]);
    }
    fclose($output);
    exit();
}

// Build the SQL query based on filters
function buildOrderQuery($paginated = true) {
    global $search, $status_filter, $date_from, $date_to, $offset, $per_page;
    
    $query = "
        SELECT o.*, u.username, u.email,
               sa.name as shipping_name, sa.phone as shipping_phone,
               sa.address as shipping_address, sa.city as shipping_city,
               sa.postal_code as shipping_postal_code,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN shipping_addresses sa ON o.shipping_address_id = sa.id
        WHERE 1=1
    ";
    
    if ($search) {
        $query .= " AND (u.username LIKE :search OR u.email LIKE :search OR o.id LIKE :search)";
    }
    
    if ($status_filter) {
        $query .= " AND o.status = :status";
    }
    
    if ($date_from) {
        $query .= " AND DATE(o.created_at) >= :date_from";
    }
    
    if ($date_to) {
        $query .= " AND DATE(o.created_at) <= :date_to";
    }
    
    $query .= " ORDER BY o.created_at DESC";
    
    if ($paginated) {
        $query .= " LIMIT :offset, :per_page";
    }
    
    return $query;
}

// Get total number of orders for pagination
try {
    $count_query = "
        SELECT COUNT(*) as total
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1
    ";
    
    if ($search) {
        $count_query .= " AND (u.username LIKE :search OR u.email LIKE :search OR o.id LIKE :search)";
    }
    
    if ($status_filter) {
        $count_query .= " AND o.status = :status";
    }
    
    if ($date_from) {
        $count_query .= " AND DATE(o.created_at) >= :date_from";
    }
    
    if ($date_to) {
        $count_query .= " AND DATE(o.created_at) <= :date_to";
    }
    
    $count_stmt = $pdo->prepare($count_query);
    
    if ($search) {
        $count_stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    if ($status_filter) {
        $count_stmt->bindValue(':status', $status_filter, PDO::PARAM_STR);
    }
    if ($date_from) {
        $count_stmt->bindValue(':date_from', $date_from, PDO::PARAM_STR);
    }
    if ($date_to) {
        $count_stmt->bindValue(':date_to', $date_to, PDO::PARAM_STR);
    }
    
    $count_stmt->execute();
    $total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_orders / $per_page);
} catch(PDOException $e) {
    $error = 'Error counting orders: ' . $e->getMessage();
    $total_pages = 0;
}

// Get orders with pagination
try {
    $stmt = $pdo->prepare(buildOrderQuery());
    if ($search) {
        $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    if ($status_filter) {
        $stmt->bindValue(':status', $status_filter, PDO::PARAM_STR);
    }
    if ($date_from) {
        $stmt->bindValue(':date_from', $date_from, PDO::PARAM_STR);
    }
    if ($date_to) {
        $stmt->bindValue(':date_to', $date_to, PDO::PARAM_STR);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $orders = [];
    $error = 'Error fetching orders: ' . $e->getMessage();
}

// Get order items separately for each order
foreach ($orders as &$order) {
    try {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name, p.image_url
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $order['order_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $order['order_items'] = [];
        $error = 'Error fetching order items: ' . $e->getMessage();
    }
}
unset($order);

$page_title = "Manage Orders";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script defer>
    const BASE_URL = '<?php echo BASE_URL; ?>';

    function formatPrice(amount) {
        return 'Rp ' + Number(amount).toLocaleString('id-ID');
    }

    function showOrderDetails(order, orderItems) {
        const modal = document.getElementById('orderModal');
        const modalContent = document.getElementById('modalContent');
        
        const statusClasses = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'processing': 'bg-blue-100 text-blue-800',
            'shipped': 'bg-indigo-100 text-indigo-800',
            'delivered': 'bg-green-100 text-green-800',
            'cancelled': 'bg-red-100 text-red-800'
        };

        const content = `
            <div class="p-6">
                <div class="mb-6">
                    <h4 class="text-lg font-semibold mb-2">Customer Information</h4>
                    <p><strong>Name:</strong> ${order.username}</p>
                    <p><strong>Email:</strong> ${order.email}</p>
                </div>

                <div class="mb-6">
                    <h4 class="text-lg font-semibold mb-2">Shipping Information</h4>
                    <p><strong>Name:</strong> ${order.shipping_name}</p>
                    <p><strong>Phone:</strong> ${order.shipping_phone}</p>
                    <p><strong>Address:</strong> ${order.shipping_address}</p>
                    <p><strong>City:</strong> ${order.shipping_city}</p>
                    <p><strong>Postal Code:</strong> ${order.shipping_postal_code}</p>
                </div>

                <div class="mb-6">
                    <h4 class="text-lg font-semibold mb-2">Order Items</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left">Product</th>
                                    <th class="px-4 py-2 text-left">Price</th>
                                    <th class="px-4 py-2 text-left">Quantity</th>
                                    <th class="px-4 py-2 text-left">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${orderItems.map(item => `
                                    <tr>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center">
                                                <img src="${BASE_URL}/${item.image_url}" alt="${item.name}" class="w-12 h-12 object-cover rounded mr-2">
                                                <span>${item.name}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">${formatPrice(item.price)}</td>
                                        <td class="px-4 py-2">${item.quantity}</td>
                                        <td class="px-4 py-2">${formatPrice(item.price * item.quantity)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <div class="text-right">
                        <p class="text-lg"><strong>Total Amount:</strong> ${formatPrice(order.total_amount)}</p>
                    </div>
                </div>

                <div class="mt-6 border-t pt-4">
                    <h4 class="text-lg font-semibold mb-2">Update Status</h4>
                    <form method="POST" class="flex items-center gap-4" onsubmit="closeModal()">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="order_id" value="${order.id}">
                        <select name="status" class="rounded border-gray-300 shadow-sm">
                            ${['pending', 'processing', 'shipped', 'delivered', 'cancelled'].map(status => 
                                `<option value="${status}" ${order.status === status ? 'selected' : ''}>${
                                    status.charAt(0).toUpperCase() + status.slice(1)
                                }</option>`
                            ).join('')}
                        </select>
                        <button type="submit" name="update_status" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            Update Status
                        </button>
                    </form>
                </div>
            </div>
        `;
        
        modalContent.innerHTML = content;
        modal.classList.remove('hidden');
    }

    function closeModal() {
        const modal = document.getElementById('orderModal');
        modal.classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize date pickers
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d",
            allowInput: true
        });

        // Initialize select all functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.order-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Modal click event handling
        const modal = document.getElementById('orderModal');
        const modalContent = modal.querySelector('.bg-white');
        
        modal.addEventListener('click', function(event) {
            // Close only if clicking the backdrop (modal background)
            if (event.target === modal) {
                closeModal();
            }
        });

        // Prevent clicks inside modal content from closing the modal
        modalContent.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    });

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        selectAll.checked = !selectAll.checked;
        document.querySelectorAll('.order-checkbox').forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
    }
    </script>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h1 class="text-3xl font-bold">Manage Orders</h1>
            <div class="flex gap-2">
                <button onclick="toggleSelectAll()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    <i class="fas fa-check-square mr-2"></i>Select All
                </button>
                <a href="?export=csv<?php echo $search ? '&search=' . urlencode($search) : ''; echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>" 
                   class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    <i class="fas fa-download mr-2"></i>Export to CSV
                </a>
            </div>
        </div>

        <!-- Order Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="rounded-full bg-blue-100 p-3">
                        <i class="fas fa-shopping-cart text-blue-500"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-gray-500 text-sm">Total Orders</h4>
                        <p class="text-2xl font-bold"><?php echo number_format($stats['total_orders']); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="rounded-full bg-green-100 p-3">
                        <i class="fas fa-dollar-sign text-green-500"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-gray-500 text-sm">Total Revenue</h4>
                        <p class="text-2xl font-bold"><?php echo formatPrice($stats['total_revenue']); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="rounded-full bg-yellow-100 p-3">
                        <i class="fas fa-clock text-yellow-500"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-gray-500 text-sm">Pending Orders</h4>
                        <p class="text-2xl font-bold"><?php echo number_format($stats['pending_orders']); ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="rounded-full bg-indigo-100 p-3">
                        <i class="fas fa-truck text-indigo-500"></i>
                    </div>
                    <div class="ml-4">
                        <h4 class="text-gray-500 text-sm">Shipped Orders</h4>
                        <p class="text-2xl font-bold"><?php echo number_format($stats['shipped_orders']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Form -->
        <form method="GET" class="bg-white p-4 rounded-lg shadow-md mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Order ID, Customer, Email" 
                           class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full rounded border-gray-300 shadow-sm">
                        <option value="">All Statuses</option>
                        <?php foreach(['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                <?php echo ucfirst($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="text" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                           class="w-full rounded border-gray-300 shadow-sm datepicker">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="text" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                           class="w-full rounded border-gray-300 shadow-sm datepicker">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </form>

        <!-- Bulk Actions Form -->
        <form method="POST" id="ordersForm" class="bg-white rounded-lg shadow-md overflow-hidden">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="p-4 border-b flex items-center gap-4">
                <select name="bulk_action" class="rounded border-gray-300 shadow-sm">
                    <option value="">Bulk Actions</option>
                    <?php foreach(['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status): ?>
                        <option value="<?php echo $status; ?>"><?php echo ucfirst($status); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Apply
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="w-8 px-6 py-3">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-300">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($orders as $order): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <input type="checkbox" name="order_ids[]" value="<?php echo $order['id']; ?>" 
                                       class="order-checkbox rounded border-gray-300">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">#<?php echo $order['id']; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['username']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $order['item_count']; ?> items</td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo formatPrice($order['total_amount']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
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
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button type="button" onclick='event.preventDefault(); event.stopPropagation(); showOrderDetails(<?php echo json_encode($order); ?>, <?php echo json_encode($order["order_items"]); ?>)'
                                        class="text-blue-600 hover:text-blue-800">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>" 
                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>" 
                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        Next
                    </a>
                <?php endif; ?>
            </nav>
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
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date pickers
    flatpickr(".datepicker", {
        dateFormat: "Y-m-d",
        allowInput: true
    });

    // Initialize select all functionality
    document.getElementById('selectAll').addEventListener('change', function() {
        document.querySelectorAll('.order-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

    // Modal click event handling
    const modal = document.getElementById('orderModal');
    const modalContent = modal.querySelector('.bg-white');
    
    modal.addEventListener('click', function(event) {
        // Close only if clicking the backdrop (modal background)
        if (event.target === modal) {
            closeModal();
        }
    });

    // Prevent clicks inside modal content from closing the modal
    modalContent.addEventListener('click', function(event) {
        event.stopPropagation();
    });
});

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    selectAll.checked = !selectAll.checked;
    document.querySelectorAll('.order-checkbox').forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}
</script>
