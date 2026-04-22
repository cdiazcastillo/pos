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
    <title>Administración - 4 Básico A</title>
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
            overflow-x: hidden;
            color: var(--text-dark);
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
            border-radius: 1.2rem;
            background: #fff;
            border: 1px solid #dbe4ff;
            padding: 10px;
            box-shadow: 0 0.7rem 1.8rem rgba(30, 27, 75, 0.18);
            display: block;
            margin: 0 auto 16px auto;
        }
        .field {
            display: grid;
            gap: 6px;
            margin-top: 10px;
            text-align: left;
        }

        input {
            height: 44px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 0 12px;
            font-size: .95rem;
        }

        .btn {
            margin-top: 12px;
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            font-size: 1rem;
        }

        .error {
            margin-top: 10px;
            color: #b91c1c;
            font-size: .88rem;
            font-weight: 600;
        }

        .links {
            margin-top: 14px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .link-btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            height: 44px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
        }

        .link-btn.vendor {
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
        }

        .link-btn.pos {
            background: var(--success);
        }

        @media (max-width: 520px) {
            .links { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app">
    <main class="wrap">
        <form class="card" method="POST" autocomplete="off">
            <div class="logo-wrap">
                <img src="img/logo.png" alt="Logo" class="logo-image">
            </div>
            <div class="field">
                <label for="password">Clave</label>
                <input id="password" name="password" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" minlength="4" required>
            </div>

            <button type="submit" class="btn">Ingresar</button>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="links">
                <a href="vendedor_login.php" class="link-btn vendor">Vendedor</a>
                <a href="index.php" class="link-btn pos">Punto de Venta</a>
            </div>
        </form>
    </main>
    </div>
</body>
</html>
