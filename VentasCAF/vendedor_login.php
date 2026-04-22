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
            background: #ffffff;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        .app {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            background: #ffffff;
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
            padding: 22px;
            box-shadow: var(--shadow-soft);
            text-align: center;
            display: grid;
            justify-items: center;
            gap: 10px;
        }

        .logo-wrap {
            display: flex;
            justify-content: center;
            width: 100%;
            margin: 0 auto 8px auto;
        }

        .logo-image {
            width: min(560px, 94vw);
            height: min(560px, 94vw);
            object-fit: contain;
            border-radius: 1.1rem;
            background: #fff;
            border: 1px solid #dbe4ff;
            padding: 12px;
            box-shadow: 0 0.7rem 1.8rem rgba(30, 27, 75, 0.18);
            display: block;
            margin: 0 auto;
        }

        .btn {
            width: min(520px, 100%);
            min-height: 50px;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1.03rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s ease, filter 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            filter: brightness(0.97);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: #fff;
        }

        .btn-green {
            background: var(--success);
            color: #fff;
            margin-top: 0;
            width: min(520px, 100%);
            min-height: 50px;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1.03rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s ease, filter 0.15s ease;
        }

        .btn-green:hover {
            transform: translateY(-1px);
            filter: brightness(0.97);
        }

        .error {
            margin-top: 12px;
            color: #b91c1c;
            font-size: .9rem;
            font-weight: 600;
        }

        @media (max-width: 520px) {
            .card { border-radius: 20px; padding: 16px; gap: 8px; }
            .btn,
            .btn-green { min-height: 48px; font-size: 1rem; }
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
            <button type="submit" class="btn btn-primary">Ventas</button>
            <a href="admin_login.php" class="btn-green">Equipo De Trabajo</a>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </form>
        </main>
    </div>
</body>
</html>
