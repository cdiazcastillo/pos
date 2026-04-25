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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Acceso Vendedor - 4 Básico A</title>
    <style>
        :root {
            --primary: #7c3aed;
            --bg-app: #f3f0ff;
            --bg-card: #ffffff;
            --text-dark: #1e1b4b;
            --success: #16a34a;
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
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        .wrap {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
        }

        .card {
            width: min(400px, 100%);
            background: var(--bg-card);
            border-radius: 1.4rem;
            padding: 1.5rem;
            box-shadow: 0 0.5rem 1.5rem rgba(30, 27, 75, 0.10);
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            align-items: stretch;
        }

        .logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 0.2rem;
        }

        .logo-image {
            width: min(180px, 50vw);
            max-width: min(180px, 50vw);
            max-height: 30dvh;
            height: auto;
            object-fit: contain;
            padding: 8px;
            box-shadow: 0 0.25rem 0.75rem rgba(30, 27, 75, 0.10);
            display: block;
        }

        .btn {
            width: 100%;
            min-height: 2.75rem;
            border: none;
            border-radius: 999rem;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1rem;
            transition: transform 0.15s ease, filter 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            filter: brightness(0.97);
        }

        .btn--primary {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6);
            color: #fff;
        }

        .btn--secondary {
            background: rgba(124, 58, 237, 0.12);
            color: #7c3aed;
        }

        .error {
            margin-top: 0.35rem;
            color: #b91c1c;
            font-size: .9rem;
            font-weight: 600;
            text-align: center;
        }

        @media (max-width: 520px) {
            .card { padding: 1.35rem 1rem; }
        }
    </style>
</head>
<body>
    <div class="app">
        <main class="wrap">
        <form class="card" method="POST" autocomplete="off" novalidate>
            <div class="logo-wrap">
                <img src="img/logo.png" alt="Logo" class="logo-image">
            </div>
            <button type="submit" class="btn btn--primary">Ventas</button>
            <a href="admin_login.php" class="btn btn--secondary">Equipo De Trabajo</a>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </form>
        </main>
    </div>
</body>
</html>
