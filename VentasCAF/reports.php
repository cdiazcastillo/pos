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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="reports.css">
    <style>
        .btn { padding: 10px 16px; border: none; border-radius: 10px; cursor: pointer; text-decoration: none; font-size: 0.92rem; font-weight: 700; color: white !important; display: inline-block; text-align: center; white-space: nowrap; transition: all 0.2s; }
        .btn:hover { transform: translateY(-1px); opacity: 0.9; }
        .btn-secondary { background-color: #6b7280 !important; }
        .btn-success { background-color: #1f9d61 !important; }
        .btn-primary { background-color: #3457dc !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sticky-top">
            <div class="header">
                <div class="reports-title-wrap">
                    <h1>Reportes de Turno</h1>
                    <p class="reports-brand">4 Básico A · Resumen y exportación de turnos</p>
                </div>
                <div class="logo-column">
                    <img src="img/logo.png" alt="Logo">
                </div>
            </div>
            <div class="top-menu-row reports-actions">
                <button id="export-all-btn" class="btn btn-success">Exportar a Excel</button>
                <a href="admin.php" class="btn btn-primary">Menú</a>
                <a href="index.php" class="btn btn-secondary">Volver al POS</a>
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
        <div id="reports-summary" class="report-details" style="display:block; margin-top:12px;">
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