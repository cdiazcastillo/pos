<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

// Solo admin puede acceder
auth_require_role(['admin']);

$db = Database::getInstance();
$roles = ['admin', 'cashier'];
$message = '';
$messageType = '';

// Procesar cambios de permisos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    $role = $_POST['role'] ?? '';
    $permissionIds = isset($_POST['permissions']) && is_array($_POST['permissions']) ? 
        array_map('intval', $_POST['permissions']) : [];
    
    if (in_array($role, $roles, true)) {
        try {
            auth_set_role_permissions($role, $permissionIds);
            $message = "Permisos actualizados correctamente para el rol '$role'.";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Error al actualizar permisos: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Obtener todos los permisos
$allPermissions = $db->query(
    "SELECT * FROM permissions ORDER BY name",
    [],
    true
) ?: [];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos - POS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .permissions-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .role-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .role-section h3 {
            margin-top: 0;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .permission-item {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            transition: all 0.2s;
        }

        .permission-item:hover {
            background: #e7f3ff;
            border-color: #007bff;
        }

        .permission-item input[type="checkbox"] {
            margin-right: 10px;
            margin-top: 2px;
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #007bff;
        }

        .permission-label {
            flex: 1;
            cursor: pointer;
        }

        .permission-label strong {
            display: block;
            color: #333;
            margin-bottom: 4px;
        }

        .permission-label small {
            color: #666;
            font-size: 0.85rem;
        }

        .button-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-primary {
            background-color: #007bff;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        .message {
            padding: 12px 16px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
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

        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 90;
            background: #fff;
            border-bottom: 1px solid #ddd;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sticky-top h2 {
            margin: 0;
            font-size: 1.3rem;
            color: #333;
        }

        .back-link {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .back-link:hover {
            background: #e9ecef;
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="sticky-top">
        <h2>Gestión de Permisos por Rol</h2>
        <a href="admin.php" class="back-link">← Volver</a>
    </div>

    <div class="permissions-container">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php foreach ($roles as $role): ?>
            <?php
            $rolePermissions = $db->query(
                "SELECT permission_id FROM role_permissions WHERE role = ?",
                [$role],
                true
            ) ?: [];
            $rolePermissionIds = array_map(function($p) { return $p['permission_id']; }, $rolePermissions);
            $roleLabel = $role === 'admin' ? 'Administrador' : 'Vendedor/Cajero';
            ?>
            <div class="role-section">
                <h3><?php echo htmlspecialchars($roleLabel); ?> (<?php echo htmlspecialchars($role); ?>)</h3>
                
                <form method="POST">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                    <input type="hidden" name="update_permissions" value="1">
                    
                    <div class="permissions-grid">
                        <?php foreach ($allPermissions as $permission): ?>
                            <div class="permission-item">
                                <input 
                                    type="checkbox" 
                                    name="permissions[]" 
                                    value="<?php echo intval($permission['id']); ?>"
                                    id="perm_<?php echo intval($permission['id']); ?>"
                                    <?php echo in_array($permission['id'], $rolePermissionIds, true) ? 'checked' : ''; ?>
                                >
                                <label for="perm_<?php echo intval($permission['id']); ?>" class="permission-label">
                                    <strong><?php echo htmlspecialchars($permission['name']); ?></strong>
                                    <small><?php echo htmlspecialchars($permission['description'] ?? ''); ?></small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn-primary">Guardar Permisos</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
