<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas Totales - VentasCAF</title>
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            font-family: var(--font-family);
            background-color: var(--light-gray);
            color: var(--dark-gray);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background-color: transparent;
            box-shadow: none;
            padding: 0;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 20px;
        }
        h1 {
            margin: 0;
            color: var(--dark-gray);
        }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 600;
            color: white !important;
            display: inline-block;
            text-align: center;
        }
        .btn-secondary {
            background-color: var(--secondary-color) !important;
        }
        .kpi-card {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 120px;
        }
        .kpi-title {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        .kpi-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark-gray);
        }
        .kpi-value.positive {
            color: var(--success-color);
        }
        .kpi-value.negative {
            color: var(--danger-color);
        }
        #dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="img/logo.png" alt="Logo" style="max-width: 100px;">
            <h1>Ventas Totales</h1>
            <a href="admin.php" class="btn btn-secondary">Volver al menu</a>
        </div>

        <div id="dashboard-grid">
            <!-- KPIs will be loaded here by JavaScript -->
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const grid = document.getElementById('dashboard-grid');

            function formatCurrency(value) {
                return '$' + new Intl.NumberFormat('es-CL').format(value);
            }

            fetch('get_totals_api.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const kpis = data.data;
                        grid.innerHTML = `
                            <div class="kpi-card">
                                <div class="kpi-title">Venta Actual</div>
                                <div class="kpi-value positive">${formatCurrency(kpis.total_sales_current_net)}</div>
                            </div>
                            <div class="kpi-card">
                                <div class="kpi-title">Venta por Efectivo</div>
                                <div class="kpi-value">${formatCurrency(kpis.net_cash_sales)}</div>
                            </div>
                            <div class="kpi-card">
                                <div class="kpi-title">Venta por Transferencia</div>
                                <div class="kpi-value">${formatCurrency(kpis.net_transfer_sales)}</div>
                            </div>
                            <div class="kpi-card">
                                <div class="kpi-title">Anulación o Cancelada</div>
                                <div class="kpi-value negative">${formatCurrency(kpis.total_returns_amount)}</div>
                            </div>
                            <div class="kpi-card">
                                <div class="kpi-title">Efectivo Esperado en Caja</div>
                                <div class="kpi-value">${formatCurrency(kpis.expected_cash_in_drawer)}</div>
                            </div>
                        `;
                    } else {
                        grid.innerHTML = `<p>Error al cargar los datos: ${data.message}</p>`;
                    }
                })
                .catch(error => {
                    grid.innerHTML = `<p>Error de conexión al cargar los datos.</p>`;
                    console.error('Error fetching totals:', error);
                });
        });
    </script>
</body>
</html>