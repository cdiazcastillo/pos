<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

function auth_login_user(array $user): void {
    $_SESSION['user_id'] = intval($user['id']);
    $_SESSION['user_role'] = (string)($user['role'] ?? 'cashier');
    $_SESSION['username'] = (string)($user['username'] ?? '');
    unset($_SESSION['is_super_admin']);
}

function auth_logout_user(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

function auth_current_user(): ?array {
    $userId = intval($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    try {
        $db = Database::getInstance();
        $user = $db->query('SELECT id, username, role FROM users WHERE id = ?', [$userId]);

        if (!$user) {
            return null;
        }

        $_SESSION['user_role'] = (string)($user['role'] ?? 'cashier');
        $_SESSION['username'] = (string)($user['username'] ?? '');

        return $user;
    } catch (Throwable $error) {
        return null;
    }
}

function auth_redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function auth_require_login(string $loginPath = 'vendedor_login.php'): array {
    $user = auth_current_user();
    if (!$user) {
        auth_logout_user();
        auth_redirect($loginPath);
    }
    return $user;
}

function auth_require_role(array $roles, string $loginPath = 'vendedor_login.php', string $forbiddenPath = 'index.php'): array {
    $user = auth_require_login($loginPath);
    $role = (string)($user['role'] ?? '');

    if (!in_array($role, $roles, true)) {
        auth_redirect($forbiddenPath);
    }

    return $user;
}

function auth_require_api_role(array $roles): array {
    header('Content-Type: application/json');

    $user = auth_current_user();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Autenticación requerida.']);
        exit;
    }

    $role = (string)($user['role'] ?? '');
    if (!in_array($role, $roles, true)) {
        echo json_encode(['success' => false, 'message' => 'No tienes permisos para esta acción.']);
        exit;
    }

    return $user;
}

function auth_is_super_admin(): bool {
    $role = (string)($_SESSION['user_role'] ?? '');
    return ($role === 'admin') && !empty($_SESSION['is_super_admin']);
}

function auth_has_permission(string $permissionKey): bool {
    $user = auth_current_user();
    if (!$user) {
        return false;
    }

    try {
        $db = Database::getInstance();
        $result = $db->query(
            "SELECT COUNT(*) as count FROM role_permissions 
             JOIN permissions ON role_permissions.permission_id = permissions.id 
             WHERE role_permissions.role = ? AND permissions.key = ?",
            [(string)($user['role'] ?? ''), $permissionKey]
        );
        return isset($result['count']) && $result['count'] > 0;
    } catch (Throwable $error) {
        return false;
    }
}

function auth_require_permission(string $permissionKey, string $loginPath = 'vendedor_login.php', string $forbiddenPath = 'index.php'): array {
    $user = auth_require_login($loginPath);
    
    if (!auth_has_permission($permissionKey)) {
        auth_redirect($forbiddenPath);
    }

    return $user;
}

function auth_get_all_permissions(string $role = ''): array {
    try {
        $db = Database::getInstance();
        
        if (empty($role)) {
            $user = auth_current_user();
            $role = (string)($user['role'] ?? '');
        }
        
        $permissions = $db->query(
            "SELECT p.* FROM permissions p
             LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role = ?
             ORDER BY p.name",
            [$role],
            true
        );
        
        return is_array($permissions) ? $permissions : [];
    } catch (Throwable $error) {
        return [];
    }
}

function auth_get_role_permissions(string $role): array {
    try {
        $db = Database::getInstance();
        $permissions = $db->query(
            "SELECT p.* FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role = ?
             ORDER BY p.name",
            [$role],
            true
        );
        
        return is_array($permissions) ? $permissions : [];
    } catch (Throwable $error) {
        return [];
    }
}

function auth_set_role_permissions(string $role, array $permissionIds): void {
    try {
        $db = Database::getInstance();
        
        // Eliminar permisos existentes
        $db->execute("DELETE FROM role_permissions WHERE role = ?", [$role]);
        
        // Insertar nuevos permisos
        $stmt = $db->connection()->prepare(
            "INSERT INTO role_permissions (role, permission_id) VALUES (?, ?)"
        );
        
        foreach ($permissionIds as $permId) {
            $stmt->execute([$role, intval($permId)]);
        }
    } catch (Throwable $error) {
        throw new Exception("Error al actualizar permisos: " . $error->getMessage());
    }
}

