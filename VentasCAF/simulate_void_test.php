<?php
// simulate_void_test.php - Simulates a scenario to test void_sale_api.php

// Ensure error reporting is on for CLI execution
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Assume we are running from 4 Básico A/
chdir(dirname(__FILE__));

// --- Step 0: Include necessary files ---
require_once 'config/db.php';

$db = Database::getInstance();
$user_id = 1; // Assuming admin user exists and is ID 1

try {
    // --- Step 1: Clean Database (Simulate install.php effect) ---
    // Note: For a true test, user should run install.php from browser first
    // For this simulation, we'll just try to create tables and user/products if they don't exist
    // This is a simplified version of install.php's table creation
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `role` ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `products` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `price` INT NOT NULL,
                `stock_level` INT NOT NULL DEFAULT 0,
                `min_stock_warning` INT NOT NULL DEFAULT 10,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `shifts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `start_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `end_time` TIMESTAMP NULL,
                `initial_cash` INT NOT NULL,
                `final_cash` INT NULL,
                `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
            );
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `sales` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `shift_id` INT NOT NULL,
                `total_amount` INT NOT NULL,
                `status` ENUM('completed', 'voided') NOT NULL DEFAULT 'completed',
                `payment_method` ENUM('cash', 'transfer') NOT NULL DEFAULT 'cash',
                `sale_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`)
            );
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `sale_items` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `sale_id` INT NOT NULL,
                `product_id` INT NOT NULL,
                `quantity` INT NOT NULL,
                `quantity_returned` INT NOT NULL DEFAULT 0,
                `price_per_unit` INT NOT NULL,
                FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`),
                FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
            );
        ");
        $db->exec("
            CREATE TABLE `expenses` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `shift_id` INT NOT NULL,
                `sale_id` INT NULL,
                `description` VARCHAR(255) NOT NULL,
                `amount` INT NOT NULL,
                `expense_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`),
                FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL
            );
        ");

        $admin_user = 'admin';
        $admin_pass = 'admin123';
        $password_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT IGNORE INTO `users` (id, username, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$user_id, $admin_user, $password_hash]);

        $products = [
            ['Café Americano', 3, 50, 10], ['Latte', 4, 30, 10], ['Croissant', 2, 15, 5],
            ['Jugo de Naranja', 3, 20, 5], ['Sandwich de Pavo', 6, 5, 2], ['Té Verde', 2, 40, 10],
            ['Muffin de Arándanos', 3, 12, 5], ['Agua Mineral', 2, 0, 10],
        ];
        $stmt = $db->prepare("INSERT IGNORE INTO `products` (name, price, stock_level, min_stock_warning) VALUES (?, ?, ?, ?)");
        foreach ($products as $product) {
            $stmt->execute($product);
        }

    } catch (PDOException $e) {
        throw $e;
    }


    // --- Step 2: Start a Shift ---
    // Ensure no open shift exists, then create one
    $db->execute("UPDATE shifts SET status = 'closed' WHERE user_id = ?", [$user_id]); // Close any existing
    $initial_cash = 1000;
    $db->execute("INSERT INTO shifts (user_id, initial_cash, status) VALUES (?, ?, 'open')", [$user_id, $initial_cash]);
    $shift_id = $db->lastInsertId();


    // --- Step 3: Create a Sale ---
    $product_id_1 = 1; // Café Americano, price 3
    $product_id_2 = 2; // Latte, price 4
    $product_id_3 = 3; // Croissant, price 2

    $sale_total = 3 + 4 + 2; // Café, Latte, Croissant
    $sale_method = 'cash';

    $db->execute("INSERT INTO sales (shift_id, total_amount, payment_method, status) VALUES (?, ?, ?, 'completed')", [$shift_id, $sale_total, $sale_method]);
    $sale_id = $db->lastInsertId();

    $db->execute("INSERT INTO sale_items (sale_id, product_id, quantity, price_per_unit) VALUES (?, ?, ?, ?)", [$sale_id, $product_id_1, 1, 3]);
    $db->execute("INSERT INTO sale_items (sale_id, product_id, quantity, price_per_unit) VALUES (?, ?, ?, ?)", [$sale_id, $product_id_2, 1, 4]);
    $db->execute("INSERT INTO sale_items (sale_id, product_id, quantity, price_per_unit) VALUES (?, ?, ?, ?)", [$sale_id, $product_id_3, 1, 2]);

    // Reduce stock
    $db->execute("UPDATE products SET stock_level = stock_level - 1 WHERE id = ?", [$product_id_1]);
    $db->execute("UPDATE products SET stock_level = stock_level - 1 WHERE id = ?", [$product_id_2]);
    $db->execute("UPDATE products SET stock_level = stock_level - 1 WHERE id = ?", [$product_id_3]);


    // --- Step 4: Simulate Voiding the Sale ---
    // We are directly calling void_sale_api.php's logic
    // For simulation, we need to manually set $_POST['sale_id'] and $_SESSION['user_id']
    $_POST['sale_id'] = $sale_id;
    $_SESSION['user_id'] = $user_id;

    // Capture output of void_sale_api.php (for testing purposes)
    ob_start();
    include 'void_sale_api.php';
    $void_api_output = ob_get_clean();
    
    $void_api_response = json_decode($void_api_output, true);
    if ($void_api_response['success']) {
        echo "Void test successful for Sale ID {$sale_id}.\n";
    } else {
        echo "Void test FAILED for Sale ID {$sale_id}.\n";
    }


} catch (Exception $e) {
    echo "Simulation failed: " . $e->getMessage() . "\n";
}

// Clean up temporary install script
unlink(__FILE__);
