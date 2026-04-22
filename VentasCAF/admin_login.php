<?php
require_once 'includes/auth.php';

$currentUser = auth_current_user();
if ($currentUser) {
    if (($currentUser['role'] ?? '') === 'admin') {
        auth_redirect('admin.php');
    }
    auth_redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = 'admin';
    $password = (string)($_POST['password'] ?? '');

    if ($password === '') {
        $error = 'Debes ingresar la clave.';
    } elseif (!preg_match('/^\d{4}$/', $password)) {
        $error = 'La clave debe tener 4 números.';
    } else {
        $db = Database::getInstance();
        $user = $db->query('SELECT id, username, password_hash, role FROM users WHERE username = ?', [$username]);

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            $error = 'Credenciales inválidas.';
        } elseif (($user['role'] ?? '') !== 'admin') {
            $error = 'Este acceso es solo para administrador.';
        } else {
            auth_login_user($user);
            auth_redirect('admin.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Admin - 4 Básico A</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f3f5fb; }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 14px; }
        .card { width: min(420px, 100%); background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 18px; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        h1 { margin: 0 0 6px; font-size: 1.2rem; color: #1f2937; }
        p { margin: 0 0 12px; color: #6b7280; font-size: .9rem; }
        .field { display: grid; gap: 6px; margin-top: 10px; }
        input { height: 42px; border: 1px solid #d1d5db; border-radius: 10px; padding: 0 10px; font-size: .95rem; }
        .btn { margin-top: 12px; width: 100%; height: 42px; border: none; border-radius: 10px; background: #111827; color: #fff; font-weight: 700; cursor: pointer; }
        .error { margin-top: 10px; color: #b91c1c; font-size: .88rem; }
        .links { margin-top: 12px; text-align: center; font-size: .88rem; }
        .links a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrap">
        <form class="card" method="POST" autocomplete="off">
            <h1>Ingreso Administrador</h1>
            <p>Equipo de trabajo (clave numérica).</p>
            <div class="field">
                <label for="password">Clave</label>
                <input id="password" name="password" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" minlength="4" required>
            </div>

            <button type="submit" class="btn">Ingresar a Admin</button>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="links">
                ¿Eres vendedor? <a href="vendedor_login.php">Ingresar al POS</a>
            </div>
        </form>
    </div>
</body>
</html>
