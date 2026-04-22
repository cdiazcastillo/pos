<?php
// Script para actualizar DB existente con tablas de permisos

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/db.php';

$message = '';
$messageType = '';

try {
    $pdo = new PDO(
        "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASS']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear tabla de permisos si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(100) NOT NULL UNIQUE,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    $message .= "✓ Tabla 'permissions' verificada/creada.<br>";

    // Crear tabla de role_permissions si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `role_permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `role` ENUM('admin', 'cashier') NOT NULL,
            `permission_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
            UNIQUE KEY `role_perm_unique` (`role`, `permission_id`)
        );
    ");
    $message .= "✓ Tabla 'role_permissions' verificada/creada.<br>";

    // Asegurar columna payment_method en expenses
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'expenses' AND COLUMN_NAME = 'payment_method'"
    );
    $stmt->execute([$_ENV['DB_NAME']]);
    $hasPaymentMethod = intval(($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0)) > 0;

    if (!$hasPaymentMethod) {
        $pdo->exec("ALTER TABLE `expenses` ADD COLUMN `payment_method` ENUM('cash', 'transfer') NOT NULL DEFAULT 'cash' AFTER `amount`");
        $message .= "✓ Columna 'payment_method' agregada a expenses.<br>";
    } else {
        $message .= "ℹ️ Columna 'payment_method' ya existe en expenses.<br>";
    }

    // Insertar permisos base (si no existen)
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
    $stmt = $pdo->prepare(
        "INSERT INTO `permissions` (`key`, `name`, `description`) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
         `name` = VALUES(`name`),
         `description` = VALUES(`description`)"
    );
    foreach ($permissions as $perm) {
        $stmt->execute($perm);
    }
    $message .= "✓ Permisos base sincronizados.<br>";

    // Asegurar permisos admin (todos)
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO `role_permissions` (role, permission_id)
        SELECT 'admin', id FROM `permissions`
    ");
    $stmt->execute();

    // Asegurar permisos por defecto para vendedor/cajero
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO `role_permissions` (role, permission_id)
        SELECT 'cashier', id FROM `permissions`
        WHERE `key` IN ('pos', 'returns', 'sales_history', 'dashboard', 'products')
    ");
    $stmt->execute();

    $message .= "✓ Permisos por defecto de roles aplicados.<br>";

    $message .= "<hr><strong>✓ Actualización completada.</strong> Ya puedes usar la gestión de permisos.";
    $messageType = 'success';

} catch (PDOException $e) {
    $message = "Error al actualizar base de datos: " . $e->getMessage();
    $messageType = 'error';
} catch (Exception $e) {
    $message = "Error inesperado: " . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar Base de Datos - POS</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { color: #333; margin-bottom: 20px; }
        .message {
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid;
            line-height: 1.6;
        }
        .message.success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .back-link:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Actualizar Base de Datos</h1>
        <div class="message <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <a href="admin.php" class="back-link">Ir al Admin</a>
    </div>
</body>
</html>
