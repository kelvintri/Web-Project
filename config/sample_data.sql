-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('Electronics', 'Electronic devices and gadgets'),
('Clothing', 'Fashion and apparel'),
('Books', 'Books and literature');

-- Insert sample products
INSERT INTO products (category_id, name, description, price, stock, image_url) VALUES
(1, 'Smartphone X', 'Latest smartphone with advanced features', 5000000, 10, 'uploads/products/smartphone.jpg'),
(1, 'Laptop Pro', 'High-performance laptop for professionals', 12000000, 5, 'uploads/products/laptop.jpg'),
(2, 'Cotton T-Shirt', 'Comfortable cotton t-shirt', 150000, 50, 'uploads/products/tshirt.jpg'),
(3, 'Programming Guide', 'Complete guide to programming', 250000, 20, 'uploads/products/book.jpg');
