<?php
// temp_install.php - Simulates installation process for testing

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ventas_db";

try {
    $pdo = new PDO("mysql:host=$servername", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    $pdo->exec("USE `$dbname`;");

    $pdo->exec("DROP TABLE IF EXISTS `sale_items`;");
    $pdo->exec("DROP TABLE IF EXISTS `expenses`;");
    $pdo->exec("DROP TABLE IF EXISTS `sales`;");
    $pdo->exec("DROP TABLE IF EXISTS `products`;");
    $pdo->exec("DROP TABLE IF EXISTS `shifts`;");
    $pdo->exec("DROP TABLE IF EXISTS `users`;");

    $pdo->exec("
        CREATE TABLE `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    $pdo->exec("
        CREATE TABLE `products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `price` INT NOT NULL,
            `stock_level` INT NOT NULL DEFAULT 0,
            `min_stock_warning` INT NOT NULL DEFAULT 10,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    $pdo->exec("
        CREATE TABLE `shifts` (
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
    $pdo->exec("
        CREATE TABLE `sales` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `shift_id` INT NOT NULL,
            `total_amount` INT NOT NULL,
            `status` ENUM('completed', 'voided') NOT NULL DEFAULT 'completed',
            `payment_method` ENUM('cash', 'transfer') NOT NULL DEFAULT 'cash',
            `sale_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`)
        );
    ");
    $pdo->exec("
        CREATE TABLE `sale_items` (
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
    $pdo->exec("
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
    $stmt = $pdo->prepare("INSERT INTO `users` (username, password_hash, role) VALUES (?, ?, 'admin')");
    $stmt->execute([$admin_user, $password_hash]);

    $products = [
        ['Americano', 3, 50, 10],
        ['Latte', 4, 30, 10],
        ['Croissant', 2, 15, 5],
        ['Jugo de Naranja', 3, 20, 5],
        ['Sandwich de Pavo', 6, 5, 2],
        ['Té Verde', 2, 40, 10],
        ['Muffin de Arándanos', 3, 12, 5],
        ['Agua Mineral', 2, 0, 10],
    ];
    $stmt = $pdo->prepare("INSERT INTO `products` (name, price, stock_level, min_stock_warning) VALUES (?, ?, ?, ?)");
    foreach ($products as $product) {
        $stmt->execute($product);
    }
    
    // Create logs directory for PHP CLI execution simulation
    $log_dir_path = dirname(__FILE__) . '/logs';
    if (!is_dir($log_dir_path)) {
        mkdir($log_dir_path, 0777, true);
    }

    echo "Database installed successfully for testing.\n";

} catch (PDOException $e) {
    echo "Database installation failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "An unexpected error occurred during installation: " . $e->getMessage() . "\n";
}

