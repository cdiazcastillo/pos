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
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #ffffff;
        }
        .wrap {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 14px;
            background: #ffffff;
        }
        .card {
            width: min(460px, 100%);
            background: #ffffff;
            border: none;
            border-radius: 14px;
            padding: 14px;
            text-align: center;
        }
        .logo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }
        .logo-wrap img {
            width: min(320px, 88vw);
            height: auto;
            object-fit: contain;
        }
        .btn {
            width: min(320px, 100%);
            height: 46px;
            border: none;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
        }
        .btn-green {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: min(320px, 100%);
            height: 46px;
            border: none;
            border-radius: 10px;
            background: #16a34a;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            margin-top: 10px;
        }
        .error {
            margin-top: 10px;
            color: #b91c1c;
            font-size: .88rem;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <form class="card" method="POST" autocomplete="off">
            <div class="logo-wrap">
                <img src="img/logo.png" alt="Logo 4 Básico A">
            </div>
            <button type="submit" class="btn">Iniciar Ventas</button>
            <a href="admin_login.php" class="btn-green">Equipo de trabajo</a>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
