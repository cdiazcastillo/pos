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
    <title>Reportes de Turno</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="reports.css">
    <style>
        .btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-size: 1rem; font-weight: 600; color: white !important; display: inline-block; text-align: center; }
        .btn-secondary { background-color: var(--secondary-color) !important; }
        .btn-success { background-color: var(--success-color) !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="img/logo.png" alt="Logo" style="max-width: 100px;">
            <h1>Reportes de Turno</h1>
            <div>
                <button id="export-all-btn" class="btn btn-success">Exportar Todo a Excel</button>
                <a href="admin.php" class="btn btn-secondary">Volver al menu</a>
            </div>
        </div>
        <div class="reports-container">
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
        <div id="report-details" class="report-details" style="display: none;">
            <!-- Los detalles del reporte se poblarán aquí -->
        </div>
    </div>
    <script src="xlsx.full.min.js"></script>
    <script src="main.js"></script>
    <script src="reports.js"></script>
</body>
</html>