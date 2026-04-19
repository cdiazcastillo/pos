<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    die('Acceso denegado. Por favor, inicie sesión.');
}

$db = Database::getInstance();
$active_shift = $db->query("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'", [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú de Administración - VentasCAF</title>
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            font-family: var(--font-family);
            background-color: var(--light-gray);
            color: var(--dark-gray);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .admin-container {
            background-color: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
            width: 90%;
            max-width: 800px;
        }
        .admin-header {
            margin-bottom: 30px;
        }
        .admin-header img {
            max-width: 120px;
            margin-bottom: 15px;
        }
        .admin-header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 2rem;
        }
        .admin-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .menu-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-color);
            color: white;
            padding: 25px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .menu-button:hover {
            background-color: #0056b3;
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }
        .menu-button.secondary {
            background-color: var(--secondary-color);
        }
        .menu-button.secondary:hover {
            background-color: #5a6268;
        }
        .menu-button.disabled {
            background-color: #a0a0a0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .shift-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <img src="img/logo.png" alt="Logo">
            <h1>Menú de Administración</h1>
        </div>
        <div class="admin-menu">
            <a href="products.php" class="menu-button">Gestionar Productos</a>
            <a href="sales_history.php" class="menu-button">Historial de Ventas</a>
            <a href="dashboard.php" class="menu-button">Panel Turno en Curso</a>
            <a href="reports.php" class="menu-button">Reportes de Turno</a>
            <a href="totals.php" class="menu-button">Ventas Totales</a>
        </div>
        <div class="shift-actions">
            <button id="start-shift-btn" class="menu-button" <?php if ($active_shift) echo 'disabled'; ?>>Iniciar Turno</button>
            <button id="end-shift-btn" class="menu-button" <?php if (!$active_shift) echo 'disabled'; ?>>Terminar Turno</button>
        </div>
        <hr>
        <a href="index.php" class="menu-button secondary">Volver al POS</a>
    </div>
    <script src="main.js"></script>
    <script>
        document.getElementById('start-shift-btn').addEventListener('click', () => {
            if (<?php echo $active_shift ? 'true' : 'false'; ?>) {
                showToast('Ya existe un Turno en curso', true);
                return;
            }
            const initialCash = prompt('Ingrese el efectivo inicial:');
            if (initialCash !== null) {
                const formData = new FormData();
                formData.append('initial_cash', initialCash);

                fetch('start_shift_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Turno iniciado con éxito.');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        });

        document.getElementById('end-shift-btn').addEventListener('click', () => {
            if (!<?php echo $active_shift ? 'true' : 'false'; ?>) return;
            const finalCash = prompt('Ingrese el efectivo final:');
            if (finalCash !== null) {
                const formData = new FormData();
                formData.append('final_cash', finalCash);

                fetch('end_shift_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Turno terminado con éxito.');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        });
    </script>
</body>
</html>