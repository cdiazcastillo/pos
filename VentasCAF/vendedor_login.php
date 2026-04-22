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
    $db = Database::getInstance();
    $user = $db->query('SELECT id, username, password_hash, role FROM users WHERE username = ?', ['ventas']);

    if (!$user) {
        $user = $db->query('SELECT id, username, password_hash, role FROM users WHERE username = ?', ['vendedor']);
    }

    if (!$user) {
        $defaultHash = password_hash('ventas123', PASSWORD_DEFAULT);
        $db->execute("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'cashier')", ['ventas', $defaultHash]);
        $user = $db->query('SELECT id, username, password_hash, role FROM users WHERE username = ?', ['ventas']);
    }

    if (!$user) {
        $error = 'No fue posible crear el usuario vendedor automáticamente. Revisa la base de datos.';
    } elseif (($user['role'] ?? '') !== 'cashier') {
        $error = 'El usuario vendedor no tiene rol permitido.';
    } else {
        auth_login_user($user);
        auth_redirect('index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Vendedor - 4 Básico A</title>
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

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Inter", "Poppins", "Segoe UI", Roboto, Arial, sans-serif;
            background: var(--bg-app);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        .app {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
        }

        .top-nav {
            position: sticky;
            top: 0;
            z-index: 10;
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

        .wrap {
            flex: 1;
            display: grid;
            place-items: center;
            padding: 16px;
        }

        .card {
            width: min(460px, 100%);
            background: var(--bg-card);
            border-radius: 24px;
            padding: 20px;
            box-shadow: var(--shadow-soft);
            text-align: center;
        }

        .label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 800;
            color: #4b5563;
            margin-bottom: 8px;
        }

        .logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 10px;
        }

        .logo-badge {
            inline-size: 4rem;
            block-size: 4rem;
            border-radius: 50%;
            background: rgba(124, 58, 237, 0.12);
            color: var(--primary);
            display: grid;
            place-items: center;
            font-size: 1.2rem;
            font-weight: 800;
        }

        .big-number {
            font-size: clamp(1.8rem, 4vw, 2.4rem);
            font-weight: 800;
            line-height: 1.1;
            margin: 4px 0 16px;
            color: var(--text-dark);
        }

        .btn {
            width: 100%;
            min-height: 48px;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: #fff;
        }

        .btn-green {
            background: var(--success);
            color: #fff;
            margin-top: 10px;
            width: 100%;
            min-height: 2.75rem;
            border: none;
            border-radius: 0.875rem;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .error {
            margin-top: 12px;
            color: #b91c1c;
            font-size: .9rem;
            font-weight: 600;
        }

        @media (max-width: 520px) {
            .card { border-radius: 20px; padding: 16px; }
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
        <form class="card" method="POST" autocomplete="off" novalidate>
            <div class="label">Acceso rápido</div>
            <div class="logo-wrap">
                <div class="logo-badge" aria-hidden="true">VD</div>
            </div>
            <div class="big-number">Vendedor</div>
            <button type="submit" class="btn btn-primary">Iniciar Ventas</button>
            <a href="admin_login.php" class="btn-green">Equipo de trabajo</a>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </form>
        </main>
    </div>
</body>
</html>
