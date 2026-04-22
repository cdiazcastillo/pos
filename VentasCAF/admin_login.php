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
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f3f5fb; position: relative; overflow-x: hidden; }
        body::before { content: ''; position: fixed; inset: -120px; background: url('img/logo.png') center/55vmin no-repeat; opacity: 0.09; pointer-events: none; filter: blur(1px); }
        .wrap { min-height: 100svh; display: grid; place-items: center; padding: clamp(12px, 3vw, 24px); position: relative; z-index: 1; }
        .card { width: min(560px, 96vw); background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: clamp(16px, 3vw, 24px); box-shadow: 0 12px 26px rgba(0,0,0,.1); }
        .logo-wrap { display: flex; justify-content: center; margin-bottom: 16px; }
        .logo-wrap img { width: min(320px, 88vw); height: auto; object-fit: contain; }
        h1 { margin: 0 0 6px; font-size: 1.3rem; color: #1f2937; text-align: center; }
        p { margin: 0 0 12px; color: #6b7280; font-size: .9rem; text-align: center; }
        .field { display: grid; gap: 6px; margin-top: 10px; }
        input { height: 42px; border: 1px solid #d1d5db; border-radius: 10px; padding: 0 10px; font-size: .95rem; }
        .btn { margin-top: 12px; width: 100%; height: 46px; border: none; border-radius: 10px; background: #16a34a; color: #fff; font-weight: 700; cursor: pointer; font-size: 1rem; }
        .error { margin-top: 10px; color: #b91c1c; font-size: .88rem; }
        .links { margin-top: 14px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .link-btn { display: inline-flex; justify-content: center; align-items: center; height: 44px; border-radius: 10px; text-decoration: none; font-size: .9rem; font-weight: 700; }
        .link-btn.vendor { background: #dc2626; color: #fff; }
        .link-btn.pos { background: #2563eb; color: #fff; }

        @media (max-width: 520px) {
            .links { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <form class="card" method="POST" autocomplete="off">
            <div class="logo-wrap">
                <img src="img/logo.png" alt="Logo 4 Básico A">
            </div>
            <h1>Equipo de trabajo</h1>
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
