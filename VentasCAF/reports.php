<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['admin'], 'admin_login.php', 'index.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Turno</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="reports.css">
    <style>
        .reports-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 12px;
            display: grid;
            gap: 12px;
        }

        .reports-header-card,
        .reports-summary-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(30, 27, 75, 0.08);
            padding: 12px;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 999rem;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            transition: transform 0.2s;
        }

        .btn:hover { transform: translateY(-1px); }
        .btn-success { background-color: #7c3aed !important; }
    </style>
</head>
<body>
    <?php $activePage = 'admin'; include 'top-nav.php'; ?>
    <div class="reports-page">
        <div class="reports-header-card sticky-top">
            <div class="header">
                <div class="reports-title-wrap">
                    <h1>Reportes de Turno</h1>
                    <p class="reports-brand">Resumen y exportación de turnos</p>
                </div>
                <div class="logo-column">
                    <img src="img/logo.png" alt="Logo">
                </div>
            </div>
            <div class="reports-actions">
                <button id="export-all-btn" class="btn btn-success">Exportar a Excel</button>
            </div>
        </div>
        <div class="reports-container" role="region" aria-label="Listado de turnos">
            <table id="shifts-table">
                <thead>
                    <tr>
                        <th>ID Turno</th>
                        <th>Usuario</th>
                        <th>Hora de Inicio</th>
                        <th>Hora de Fin</th>
                        <th>Venta Efectivo</th>
                        <th>Venta Transferencia</th>
                        <th>Efectivo Final</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Los datos de los turnos se poblarán aquí -->
                </tbody>
            </table>
        </div>
        <div id="reports-summary" class="report-details reports-summary-card" style="display:block; margin-top:0;">
            <strong>Total turnos cargados:</strong> <span id="total-shifts-count">0</span>
        </div>
        <div id="report-details" class="report-details" style="display: none;">
            <!-- Los detalles del reporte se poblarán aquí -->
        </div>
    </div>
    <script src="xlsx.full.min.js"></script>
    <script src="main.js"></script>
    <script src="reports.js"></script>
</body>
</html>