-- Drop existing tables if they exist
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS shipping_addresses;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create shipping_addresses table
CREATE TABLE shipping_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    is_default TINYINT(1) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create cart table
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    shipping_address_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shipping_address_id) REFERENCES shipping_addresses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create order_items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert admin user (password: admin123)
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@example.com', '$2y$10$8WkSBtX3ajVtJvP6hU1w4.6YzGUZDh3t3c4KV9fGu4wNX.WLmrJVK', 'admin');

-- Insert categories
INSERT INTO categories (name, description) VALUES
('Gaming Mice', 'High-performance gaming mice for precision and control'),
('Gaming Keyboards', 'Mechanical and membrane keyboards for gaming'),
('Gaming Headsets', 'High-quality audio headsets for immersive gaming'),
('Mousepads', 'Gaming mousepads for optimal mouse tracking'),
('Gaming Monitors', 'High refresh rate monitors for competitive gaming'),
('Microphones', 'Professional microphones for streaming and content creation'),
('Webcams', 'HD webcams for streaming and video content');

-- Insert gaming gear products
INSERT INTO products (category_id, name, description, price, stock, image_url) VALUES
-- Gaming Mice
(1, 'Logitech G Pro X Superlight', 'Ultra-lightweight wireless gaming mouse with HERO 25K sensor, less than 63 grams', 1799000, 50, 'products/gpro-superlight.jpg'),
(1, 'Razer DeathAdder V3 Pro', 'Ergonomic wireless gaming mouse with Focus Pro 30K optical sensor', 2299000, 40, 'products/deathadder-v3-pro.jpg'),
(1, 'Zowie EC2-C', 'Professional esports gaming mouse with 3360 sensor and ergonomic design', 1099000, 35, 'products/zowie-ec2c.jpg'),

-- Gaming Keyboards
(2, 'Ducky One 3 SF', 'Premium mechanical keyboard with hot-swappable switches and PBT keycaps', 1899000, 25, 'products/ducky-one3.jpg'),
(2, 'Logitech G Pro X', 'TKL mechanical gaming keyboard with hot-swappable switches', 1999000, 30, 'products/gpro-keyboard.jpg'),
(2, 'Keychron Q1', 'Custom mechanical keyboard with QMK/VIA support and aluminum case', 2499000, 20, 'products/keychron-q1.jpg'),

-- Gaming Headsets
(3, 'HyperX Cloud Alpha', 'Premium gaming headset with dual chamber drivers', 1299000, 45, 'products/cloud-alpha.jpg'),
(3, 'Logitech G Pro X', 'Professional gaming headset with Blue VO!CE technology', 1899000, 35, 'products/gpro-headset.jpg'),
(3, 'SteelSeries Arctis Nova Pro', 'High-end wireless gaming headset with active noise cancellation', 4499000, 15, 'products/arctis-nova-pro.jpg'),

-- Mousepads
(4, 'Artisan Hien FX XSOFT', 'Premium Japanese mousepad with unique surface texture', 899000, 20, 'products/artisan-hien.jpg'),
(4, 'Logitech G640', 'Large cloth gaming mousepad with consistent surface texture', 499000, 60, 'products/g640.jpg'),
(4, 'Zowie GSR-SE Rouge', 'Professional esports mousepad with controlled surface', 449000, 40, 'products/gsr-se.jpg'),

-- Gaming Monitors
(5, 'ASUS ROG Swift PG279QM', '27" 1440p 240Hz IPS Gaming Monitor with G-SYNC', 12999000, 10, 'products/pg279qm.jpg'),
(5, 'BenQ ZOWIE XL2546K', '24.5" 240Hz DyAc+ Gaming Monitor for Esports', 8999000, 15, 'products/xl2546k.jpg'),
(5, 'LG 27GP950-B', '27" 4K 160Hz Nano IPS Gaming Monitor', 13999000, 8, 'products/27gp950.jpg'),

-- Microphones
(6, 'Shure SM7B', 'Professional dynamic microphone for streaming and content creation', 4999000, 10, 'products/sm7b.jpg'),
(6, 'Elgato Wave:3', 'Premium USB condenser microphone with digital mixing', 2499000, 25, 'products/wave3.jpg'),
(6, 'HyperX QuadCast S', 'RGB USB condenser microphone with tap-to-mute', 2299000, 20, 'products/quadcast-s.jpg'),

-- Webcams
(7, 'Logitech BRIO', '4K Ultra HD webcam with HDR and Windows Hello', 3299000, 15, 'products/brio.jpg'),
(7, 'Elgato Facecam', 'Professional 1080p 60fps webcam for content creators', 2999000, 20, 'products/facecam.jpg'),
(7, 'Razer Kiyo Pro', '1080p 60fps webcam with adaptive light sensor', 2799000, 18, 'products/kiyo-pro.jpg');
