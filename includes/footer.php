    </main>
    <footer class="bg-gray-800 text-white py-8 mt-8">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">Gaming Gear Store</h3>
                    <p class="text-gray-300">Your one-stop shop for premium gaming gear in Indonesia.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? '../index.php' : 'index.php'; ?>" class="text-gray-300 hover:text-white">Home</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? 'order_history.php' : 'customer/order_history.php'; ?>" class="text-gray-300 hover:text-white">Order History</a></li>
                        <li><a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? 'manage_address.php' : 'customer/manage_address.php'; ?>" class="text-gray-300 hover:text-white">Manage Addresses</a></li>
                        <?php else: ?>
                        <li><a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? '../login.php' : 'login.php'; ?>" class="text-gray-300 hover:text-white">Login</a></li>
                        <li><a href="<?php echo str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']) === '/final/customer' ? '../register.php' : 'register.php'; ?>" class="text-gray-300 hover:text-white">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact Us</h3>
                    <ul class="space-y-2 text-gray-300">
                        <li><i class="fas fa-envelope mr-2"></i> support@gaminggear.com</li>
                        <li><i class="fas fa-phone mr-2"></i> +62 123 456 7890</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i> Jakarta, Indonesia</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-300">
                <p>&copy; <?php echo date('Y'); ?> Gaming Gear Store. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
