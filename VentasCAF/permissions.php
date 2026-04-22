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
    
    // Admin siempre tiene todos los permisos, no se puede cambiar
    if ($role === 'admin') {
        $message = "Los permisos del administrador no pueden modificarse.";
        $messageType = 'warning';
    } elseif (in_array($role, $roles, true)) {
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

// Determinar rol seleccionado
$selectedRole = isset($_GET['role']) && in_array($_GET['role'], $roles, true) ? $_GET['role'] : 'cashier';

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
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-color: #3457dc;
            --success-color: #1f9d61;
            --warning-color: #f59e0b;
            --danger-color: #dc3545;
            --muted: #6b7280;
            --light-bg: #f3f5fb;
        }

        body {
            background-color: var(--light-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .permissions-container {
            max-width: 1080px;
            margin: 0 auto;
            padding: clamp(12px, 3vw, 18px);
        }

        .permissions-shell {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(30, 27, 75, 0.08);
            padding: clamp(12px, 2vw, 20px);
        }

        .role-selector-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            background: #f8faff;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        .role-selector-row label {
            font-weight: 700;
            color: var(--muted);
            white-space: nowrap;
        }

        .role-selector-row select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            cursor: pointer;
            min-width: 200px;
        }

        .role-section {
            background: #fcfcff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: clamp(14px, 2vw, 22px);
            box-shadow: inset 0 0 0 1px rgba(124, 58, 237, 0.03);
        }

        .role-section h3 {
            margin-top: 0;
            color: #1f2937;
            font-size: clamp(1rem, 2vw, 1.2rem);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .role-status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #e0e7ff;
            color: #3730a3;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px;
            margin: 18px 0 22px;
        }

        .permission-item {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            background: #f8faff;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }

        .permission-item:not(:has(input:disabled)):hover {
            background: #eef3ff;
            border-color: var(--primary-color);
        }

        .permission-item:has(input:disabled) {
            opacity: 0.6;
            pointer-events: none;
        }

        .permission-item input[type="checkbox"] {
            margin-right: 10px;
            margin-top: 2px;
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .permission-label {
            flex: 1;
            cursor: pointer;
        }

        .permission-label strong {
            display: block;
            color: #1f2937;
            margin-bottom: 4px;
            font-size: 0.92rem;
        }

        .permission-label small {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .button-group {
            margin-top: 20px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        button {
            padding: clamp(10px, 1vw, 12px) clamp(16px, 2vw, 20px);
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background-color 0.2s;
            font-weight: 700;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #253ea8;
        }

        .btn-secondary {
            background-color: var(--muted);
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
        }

        .message {
            padding: 12px 16px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
            font-size: 0.92rem;
        }

        .message.success {
            background-color: #d4edda;
            border-color: var(--success-color);
            color: #155724;
        }

        .message.error {
            background-color: #f8d7da;
            border-color: var(--danger-color);
            color: #721c24;
        }

        .message.warning {
            background-color: #fff3cd;
            border-color: var(--warning-color);
            color: #856404;
        }

        .sticky-top {
            position: sticky;
            top: calc(var(--nav-height, 3.8rem) + 0.35rem);
            z-index: 90;
            background: #f3f5fb;
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .sticky-top h2 {
            margin: 0;
            font-size: clamp(1rem, 2vw, 1.4rem);
            color: #1f2937;
        }

        .back-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.86rem;
            padding: 8px 12px;
            background: #fff;
            border-radius: 999rem;
            transition: all 0.2s;
            font-weight: 600;
            border: 1px solid #d1d5db;
        }

        .back-link:hover {
            background: #e9ecef;
            color: #253ea8;
        }

        @media (max-width: 768px) {
            .permissions-grid {
                grid-template-columns: 1fr;
            }

            .role-selector-row {
                flex-direction: column;
                align-items: stretch;
            }

            .role-selector-row select {
                min-width: unset;
                width: 100%;
            }

            .button-group {
                flex-direction: column;
            }

            .button-group button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php $activePage = 'admin'; include 'top-nav.php'; ?>
    <div class="sticky-top">
        <h2>Gestión de Permisos</h2>
        <a href="admin.php" class="back-link">← Volver al Panel</a>
    </div>

    <div class="permissions-container">
        <div class="permissions-shell">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="role-selector-row">
            <label for="role-select">Selecciona un rol para gestionar permisos:</label>
            <select id="role-select" onchange="window.location.href='?role=' + this.value">
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $r === $selectedRole ? 'selected' : ''; ?>>
                        <?php echo $r === 'admin' ? 'Administrador' : 'Vendedor (Cajero)'; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php
        $role = $selectedRole;
        $rolePermissions = $db->query(
            "SELECT permission_id FROM role_permissions WHERE role = ?",
            [$role],
            true
        ) ?: [];
        $rolePermissionIds = array_map(function($p) { return $p['permission_id']; }, $rolePermissions);
        $roleLabel = $role === 'admin' ? 'Administrador' : 'Vendedor (Cajero)';
        $isAdminRole = $role === 'admin';
        ?>
        <div class="role-section">
            <h3>
                <?php echo htmlspecialchars($roleLabel); ?>
                <?php if ($isAdminRole): ?>
                    <span class="role-status-badge">Permisos fijos</span>
                <?php endif; ?>
            </h3>
            
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
                                <?php echo $isAdminRole ? 'disabled' : ''; ?>
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
                    <button type="submit" class="btn-primary" <?php echo $isAdminRole ? 'disabled' : ''; ?>>Guardar Permisos</button>
                </div>
            </form>
        </div>
        </div>
    </div>
</body>
</html>
