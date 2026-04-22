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
    <title>Equipo de trabajo - 4 Básico A</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f3f5fb; position: relative; overflow: hidden; }
        body::before { content: ''; position: fixed; inset: -120px; background: url('img/logo.png') center/55vmin no-repeat; opacity: 0.09; pointer-events: none; filter: blur(1px); }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 16px; position: relative; z-index: 1; }
        .card { width: min(440px, 100%); background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 18px; box-shadow: 0 12px 26px rgba(0,0,0,.1); }
        .logo-wrap { display: flex; justify-content: center; margin-bottom: 8px; }
        .logo-wrap img { width: 94px; height: 94px; object-fit: contain; border-radius: 14px; background:#fff; border:1px solid #dbe4ff; padding: 5px; }
        h1 { margin: 0 0 6px; font-size: 1.3rem; color: #1f2937; text-align: center; }
        p { margin: 0 0 12px; color: #6b7280; font-size: .9rem; text-align: center; }
        .field { display: grid; gap: 6px; margin-top: 10px; }
        input { height: 42px; border: 1px solid #d1d5db; border-radius: 10px; padding: 0 10px; font-size: .95rem; }
        .btn { margin-top: 12px; width: 100%; height: 44px; border: none; border-radius: 10px; background: #111827; color: #fff; font-weight: 700; cursor: pointer; }
        .error { margin-top: 10px; color: #b91c1c; font-size: .88rem; }
        .links { margin-top: 14px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .link-btn { display: inline-flex; justify-content: center; align-items: center; height: 40px; border-radius: 10px; text-decoration: none; font-size: .88rem; font-weight: 700; }
        .link-btn.vendor { background: #e2e8f0; color: #1e293b; }
        .link-btn.pos { background: #2563eb; color: #fff; }
    </style>
</head>
<body>
    <div class="wrap">
        <form class="card" method="POST" autocomplete="off">
            <div class="logo-wrap">
                <img src="img/logo.png" alt="Logo 4 Básico A">
            </div>
            <h1>Equipo de trabajo</h1>
            <p>Acceso protegido con clave numérica.</p>
            <div class="field">
                <label for="password">Clave</label>
                <input id="password" name="password" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" minlength="4" required>
            </div>

            <button type="submit" class="btn">Ingresar</button>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="links">
                <a href="vendedor_login.php" class="link-btn vendor">Regresar a vendedor</a>
                <a href="index.php" class="link-btn pos">Ir al POS</a>
            </div>
        </form>
    </div>
</body>
</html>
