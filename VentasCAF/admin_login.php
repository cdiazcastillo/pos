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
        :root {
            --primary: #7c3aed;
            --bg-app: #f3f0ff;
            --bg-card: #ffffff;
            --text-dark: #1e1b4b;
            --text-muted: #4b5563;
            --danger: #dc2626;
            --success: #16a34a;
            --blue: #2563eb;
            --shadow-soft: 0 8px 24px rgba(30, 27, 75, 0.1);
        }

        * { box-sizing: border-box; }

        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Inter", "Poppins", "Segoe UI", Roboto, Arial, sans-serif; background: var(--bg-app); overflow-x: hidden; color: var(--text-dark); }

        .app { min-height: 100svh; display: flex; flex-direction: column; position: relative; z-index: 1; }
        .top-nav {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #6d28d9;
            padding: 10px 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .nav-btn {
            border: none;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 0.82rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
        }
        .nav-btn.menu { background: var(--success); }
        .nav-btn.pos { background: var(--danger); }
        .nav-btn.logout { background: var(--blue); }

        .wrap { flex: 1; display: grid; place-items: center; padding: clamp(12px, 3vw, 24px); }
        .card { width: min(560px, 96vw); background: var(--bg-card); border: 1px solid #ece8ff; border-radius: 24px; padding: clamp(16px, 3vw, 24px); box-shadow: var(--shadow-soft); }
        .logo-wrap { display: flex; justify-content: center; margin-bottom: 16px; }
        .logo-badge { inline-size: 4rem; block-size: 4rem; border-radius: 50%; background: rgba(124, 58, 237, 0.12); color: var(--primary); display: grid; place-items: center; font-size: 1.2rem; font-weight: 800; }
        h1 { margin: 0 0 6px; font-size: 1.3rem; color: var(--text-dark); text-align: center; }
        p { margin: 0 0 12px; color: var(--text-muted); font-size: .9rem; text-align: center; }
        .field { display: grid; gap: 6px; margin-top: 10px; }
        input { height: 44px; border: 1px solid #d1d5db; border-radius: 12px; padding: 0 12px; font-size: .95rem; }
        .btn { margin-top: 12px; width: 100%; height: 48px; border: none; border-radius: 14px; background: linear-gradient(135deg, var(--primary), #8b5cf6); color: #fff; font-weight: 700; cursor: pointer; font-size: 1rem; }
        .error { margin-top: 10px; color: #b91c1c; font-size: .88rem; }
        .links { margin-top: 14px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .link-btn { display: inline-flex; justify-content: center; align-items: center; height: 44px; border-radius: 12px; text-decoration: none; font-size: .9rem; font-weight: 700; }
        .link-btn.vendor { background: #dc2626; color: #fff; }
        .link-btn.pos { background: #16a34a; color: #fff; }

        @media (max-width: 520px) {
            .links { grid-template-columns: 1fr; }
            .top-nav { justify-content: stretch; }
            .nav-btn { flex: 1; }
        }
    </style>
</head>
<body>
    <div class="app">
        <div class="top-nav">
            <a href="index.php" class="nav-btn pos">Volver al POS</a>
            <a href="logout.php" class="nav-btn logout">Cerrar sesión</a>
        </div>

    <main class="wrap">
        <form class="card" method="POST" autocomplete="off">
            <div class="logo-wrap">
                <div class="logo-badge" aria-hidden="true">AD</div>
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
    </main>
    </div>
</body>
</html>
