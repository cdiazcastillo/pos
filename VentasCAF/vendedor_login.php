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

        .wrap {
            flex: 1;
            display: grid;
            place-items: center;
            padding: 16px;
        }

        .card {
            width: min(760px, 100%);
            background: var(--bg-card);
            border-radius: 24px;
            padding: 20px;
            box-shadow: var(--shadow-soft);
            text-align: center;
        }

        .logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }

        .logo-image {
            width: min(560px, 94vw);
            height: min(560px, 94vw);
            object-fit: contain;
            border-radius: 1.1rem;
            background: #fff;
            border: 1px solid #dbe4ff;
            padding: 10px;
            box-shadow: 0 0.7rem 1.8rem rgba(30, 27, 75, 0.18);
            display: block;
            margin: 0 auto 16px auto;
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
        }
    </style>
</head>
<body>
    <div class="app" style="background:#fff;min-height:100vh;">
        <main class="wrap">
        <form class="card" method="POST" autocomplete="off" novalidate>
            <div class="logo-wrap">
                <img src="img/logo.png" alt="Logo" class="logo-image">
            </div>
            <button type="submit" class="btn btn-primary">Iniciar Ventas</button>
            <a href="admin_login.php" class="btn-green">Administración</a>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </form>
        </main>
    </div>
</body>
</html>
