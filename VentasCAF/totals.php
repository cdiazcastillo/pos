<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['admin'], 'admin_login.php', 'index.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas Totales - 4 Básico A</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary-color: #7c3aed;
            --secondary-color: #6b7280;
            --success-color: #14b8a6;
            --warning-color: #f97316;
            --danger-color: #fb7185;
            --info-color: #3b82f6;
            --light-gray: #f3f0ff;
            --dark-gray: #1e1b4b;
            --font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            --card-shadow: 0 0.5rem 1.5rem rgba(30, 27, 75, 0.1);
        }
        body {
            font-family: var(--font-family);
            background-color: var(--light-gray);
            color: var(--dark-gray);
            margin: 0;
            padding: 0.75rem;
            overflow-x: hidden;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background-color: transparent;
            box-shadow: none;
            padding: 0;
        }
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--light-gray);
            padding: 6px 0 10px;
            margin-bottom: 8px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .title-wrap { display: flex; align-items: center; gap: 10px; }
        .logo-column img {
            width: 66px;
            height: 66px;
            object-fit: contain;
            border-radius: 1.25rem;
            border: 1px solid #dbe4ff;
            background: #fff;
            padding: 4px;
        }
        h1 {
            margin: 0;
            color: var(--dark-gray);
            font-size: 1.15rem;
        }
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 999rem;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 600;
            color: white !important;
            display: inline-block;
            text-align: center;
            white-space: nowrap;
        }
        .btn-secondary {
            background-color: var(--secondary-color) !important;
        }
        .kpi-card {
            background-color: #fff;
            padding: 1.25rem;
            border-radius: 1.4rem;
            box-shadow: var(--card-shadow);
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
    <?php $activePage = 'totals'; include 'top-nav.php'; ?>
    <div class="container">
        <div class="sticky-top">
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