<?php
// Simple script to initialize the database for the 4 Básico A POS system.
// WARNING: This script will drop existing tables if they exist.

require_once __DIR__ . '/config/bootstrap.php'; // Incluir bootstrap para cargar variables de entorno

$servername = $_ENV['DB_HOST'];
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$dbname = $_ENV['DB_NAME'];

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // 1. Conectar a MySQL server (usando credenciales del .env)
        $pdo = new PDO("mysql:host=$servername", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 2. Crear la base de datos si no existe (esto generará un error si el usuario no tiene permisos para CREAR BDs, pero es normal en cPanel)
        // La BD ya debe estar creada en cPanel, este paso es más bien una verificación o un intento que podría fallar
        // $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"); // Descomentar solo si el usuario de BD tiene permiso para crear BDs
        $pdo->exec("USE `$dbname`;"); // Usar la BD existente
        $message .= "Base de datos '$dbname' seleccionada o creada (si se tenía permiso).<br>";


        // 3. Eliminar tablas existentes para asegurar una instalación limpia
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;"); // Desactivar FKs
        $pdo->exec("DROP TABLE IF EXISTS `sale_items`;");
        $pdo->exec("DROP TABLE IF EXISTS `expenses`;");
        $pdo->exec("DROP TABLE IF EXISTS `sales`;");
        $pdo->exec("DROP TABLE IF EXISTS `products`;");
        $pdo->exec("DROP TABLE IF EXISTS `shifts`;");
        $pdo->exec("DROP TABLE IF EXISTS `users`;");
        $pdo->exec("DROP TABLE IF EXISTS `role_permissions`;");
        $pdo->exec("DROP TABLE IF EXISTS `permissions`;");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;"); // Reactivar FKs
        $message .= "Tablas existentes eliminadas (si existían).<br>";


        // 4. Crear tablas
        // Users Table
        $pdo->exec("
            CREATE TABLE `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `role` ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $message .= "`users` table created.<br>";

        // Products Table
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
        $message .= "`products` table created.<br>";

        // Shifts Table
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
        $message .= "`shifts` table created.<br>";
        
        // Sales Table
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
        $message .= "`sales` table created.<br>";

        // Sale Items Table
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
        $message .= "`sale_items` table created.<br>";

        // Expenses Table
        $pdo->exec("
            CREATE TABLE `expenses` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `shift_id` INT NOT NULL,
                `sale_id` INT NULL, -- Link to a sale for returns/voids
                `description` VARCHAR(255) NOT NULL,
                `amount` INT NOT NULL,
                `payment_method` ENUM('cash', 'transfer') NOT NULL DEFAULT 'cash',
                `expense_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`),
                FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE SET NULL
            );
        ");
        $message .= "`expenses` table created.<br>";

        // Permissions Table
        $pdo->exec("
            CREATE TABLE `permissions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(100) NOT NULL UNIQUE,
                `name` VARCHAR(100) NOT NULL,
                `description` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $message .= "`permissions` table created.<br>";

        // Role Permissions Table
        $pdo->exec("
            CREATE TABLE `role_permissions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `role` ENUM('admin', 'cashier') NOT NULL,
                `permission_id` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `role_perm_unique` (`role`, `permission_id`)
            );
        ");
        $message .= "`role_permissions` table created.<br>";

        // 4.5. Insertar permisos por defecto
        $permissions = [
            ['pos', 'Punto de Venta (POS)', 'Acceso a la pantalla de ventas'],
            ['returns', 'Devoluciones', 'Procesar devoluciones de productos'],
            ['sales_history', 'Historial de Ventas', 'Ver historial de ventas'],
            ['dashboard', 'Dashboard', 'Ver dashboard administrativo'],
            ['reports', 'Reportes', 'Generar y ver reportes'],
            ['totals', 'Totales', 'Ver totales y resúmenes'],
            ['products', 'Productos', 'Gestionar inventario de productos'],
            ['monitor', 'Monitor', 'Monitoreo en tiempo real'],
            ['users', 'Usuarios', 'Gestionar usuarios del sistema'],
            ['permissions', 'Permisos', 'Configurar permisos por rol'],
        ];
        $stmt = $pdo->prepare("INSERT INTO `permissions` (`key`, `name`, `description`) VALUES (?, ?, ?)");
        foreach ($permissions as $perm) {
            $stmt->execute($perm);
        }
        $message .= "Permisos por defecto insertados.<br>";

        // 4.6. Asignar permisos a roles
        // Admin: todos los permisos
        $stmt = $pdo->prepare("
            INSERT INTO `role_permissions` (role, permission_id) 
            SELECT 'admin', id FROM `permissions`
        ");
        $stmt->execute();
        
        // Cashier: POS + historial + dashboard + productos (sin totales ni módulos sensibles)
        $stmt = $pdo->prepare("
            INSERT INTO `role_permissions` (role, permission_id)
            SELECT 'cashier', id FROM `permissions` WHERE `key` IN ('pos', 'returns', 'sales_history', 'dashboard', 'products')
        ");
        $stmt->execute();
        $message .= "Permisos por defecto asignados a roles.<br>";

        // 5. Insertar usuario administrador por defecto
        $admin_user = 'admin';
        $admin_pass = '0000'; // Clave del equipo de trabajo (admin)
        $password_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `users` (username, password_hash, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$admin_user, $password_hash]);
        $message .= "Usuario administrador por defecto creado (usuario: 'admin', clave: '0000').<br>";

        // 5b. Insertar usuario vendedor por defecto
        $cashier_user = 'ventas';
        $cashier_pass = 'ventas123';
        $cashier_hash = password_hash($cashier_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `users` (username, password_hash, role) VALUES (?, ?, 'cashier')");
        $stmt->execute([$cashier_user, $cashier_hash]);
        $message .= "Usuario vendedor por defecto creado (usuario: 'ventas', clave: 'ventas123').<br>";

        // 5c. Compatibilidad con despliegues anteriores
        $legacy_cashier_user = 'vendedor';
        $legacy_cashier_pass = 'vendedor123';
        $legacy_cashier_hash = password_hash($legacy_cashier_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `users` (username, password_hash, role) VALUES (?, ?, 'cashier')");
        $stmt->execute([$legacy_cashier_user, $legacy_cashier_hash]);
        $message .= "Usuario de compatibilidad creado (usuario: 'vendedor', clave: 'vendedor123').<br>";

        // 6. Insertar productos de ejemplo
        $products = [
            ['Café Americano', 3, 50, 10],
            ['Latte', 4, 30, 10],
            ['Croissant', 2, 15, 5],
            ['Jugo de Naranja', 3, 20, 5],
            ['Sandwich de Pavo', 6, 5, 2],
            ['Té Verde', 2, 40, 10],
            ['Muffin de Arándanos', 3, 12, 5],
            ['Agua Mineral', 2, 0, 10], // Out of stock
        ];
        $stmt = $pdo->prepare("INSERT INTO `products` (name, price, stock_level, min_stock_warning) VALUES (?, ?, ?, ?)");
        foreach ($products as $product) {
            $stmt->execute($product);
        }
        $message .= "Productos de ejemplo insertados.<br>";
        
        $message .= "<hr><strong>¡Instalación completada con éxito!</strong> Ahora puedes usar la aplicación. Por favor, elimina este archivo por razones de seguridad.";


    } catch (PDOException $e) {
        $message = "La instalación falló: " . $e->getMessage();

    } catch (Exception $e) {
        $message = "Ocurrió un error inesperado durante la instalación: " . $e->getMessage();

    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>4 Básico A - Instalador de Base de Datos</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 2rem auto; padding: 0 1rem; background-color: #f8f9fa; }
        .container { background-color: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { color: #007bff; }
        button { background-color: #007bff; color: #fff; border: none; padding: 12px 20px; font-size: 1rem; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; }
        button:hover { background-color: #0056b3; }
        .message { margin-top: 1.5rem; padding: 1rem; border-radius: 5px; border: 1px solid transparent; }
        .message.success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
        .message.error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>4 Básico A Instalador de Base de Datos</h1>
        <p>Este script configurará la base de datos y las tablas necesarias para el sistema POS.</p>
        <p><strong>Advertencia:</strong> Esto eliminará las tablas existentes con los mismos nombres en la base de datos `<?php echo $dbname; ?>`.</p>
        
        <form method="POST">
            <button type="submit" name="install">Instalar Base de Datos</button>
        </form>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo (strpos($message, 'falló') !== false) ? 'error' : 'success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>