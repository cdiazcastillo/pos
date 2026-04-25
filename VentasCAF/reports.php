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
    <style>
        :root {
            --primary: #7c3aed;
            --bg-app: #f3f0ff;
            --bg-card: #ffffff;
            --bg-surface: #faf8ff;
            --text-dark: #1e1b4b;
            --text-muted: #6b7280;
            --success: #14b8a6;
            --danger: #fb7185;
            --warning: #f97316;
            --shadow-card: 0 0.5rem 1.5rem rgba(30,27,75,0.10);
            --radius-card: 1.4rem;
            --border-subtle: 0.06rem solid rgba(124,58,237,0.12);
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Poppins', 'Segoe UI', sans-serif;
            background: var(--bg-app);
            color: var(--text-dark);
        }

        body {
            overflow-x: hidden;
            display: block !important;
            min-height: 100% !important;
        }

        .reports-page {
            width: min(100%, 80rem);
            margin: 0 auto;
            max-width: 100%;
            padding: 1rem;
            display: grid;
            gap: 1rem;
            align-content: start;
        }

        .reports-header-card,
        .reports-summary-card,
        .report-details {
            background: var(--bg-card);
            border: var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 1rem;
            max-width: 100%;
        }

        .sticky-top {
            position: sticky;
            top: calc(var(--nav-height, 3.8rem) + 0.35rem);
            z-index: 90;
            background: transparent;
            padding-top: 0.15rem;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            min-width: 0;
        }

        .reports-title-wrap {
            display: grid;
            gap: 0.35rem;
            min-width: 0;
            flex: 1 1 auto;
        }

        .reports-title-wrap h1 {
            margin: 0;
            font-size: clamp(1rem, 4vw, 1.4rem);
            color: var(--text-dark);
            font-weight: 800;
            line-height: 1.15;
        }

        .reports-brand {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 800;
        }

        .logo-column {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex: 0 0 auto;
        }

        .logo-column img {
            width: 3.4rem;
            height: 3.4rem;
            object-fit: contain;
            border-radius: 1rem;
            padding: 0.3rem;
            background: #fff;
            box-shadow: 0 0.25rem 0.75rem rgba(30,27,75,0.10);
        }

        .reports-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-top: 0.85rem;
            min-width: 0;
        }

        .btn {
            padding: 0.65rem 1.1rem;
            border: none;
            border-radius: 999rem;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            transition: transform 0.2s ease, filter 0.2s ease;
        }

        .btn:hover,
        .view-report-btn:hover {
            transform: translateY(-1px);
            filter: brightness(0.98);
        }

        .btn-success {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6) !important;
            color: #ffffff !important;
        }

        .btn-secondary,
        .view-report-btn {
            background: rgba(124, 58, 237, 0.12) !important;
            color: #7c3aed !important;
            border: none;
            border-radius: 999rem;
            font-weight: 700;
            min-height: 2.75rem;
            padding: 0.45rem 0.9rem;
        }

        .reports-container {
            margin-top: 0;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            background: var(--bg-card);
            border: var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            min-height: 0;
        }

        #shifts-table {
            width: 100%;
            min-width: 900px;
            border-collapse: separate;
            border-spacing: 0;
        }

        #shifts-table th,
        #shifts-table td {
            padding: 0.85rem 0.9rem;
            text-align: left;
            border-bottom: var(--border-subtle);
            font-size: 0.88rem;
            vertical-align: middle;
            font-variant-numeric: tabular-nums;
        }

        #shifts-table th {
            background: rgba(124,58,237,0.08);
            color: var(--text-dark);
            font-weight: 800;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        #shifts-table tbody tr:nth-child(odd) {
            background: var(--bg-surface);
        }

        #shifts-table tbody tr:nth-child(even) {
            background: #ffffff;
        }

        #shifts-table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-pill-table {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.7rem;
            border-radius: 999rem;
            font-size: 0.74rem;
            font-weight: 800;
        }

        .status-pill-table.open {
            background: rgba(20, 184, 166, 0.13);
            color: #0f766e;
        }

        .status-pill-table.closed {
            background: rgba(124, 58, 237, 0.1);
            color: #5b21b6;
        }

        .report-details { margin-top: 0; }

        .report-details h2 {
            margin-top: 0;
            font-size: clamp(1rem, 2vw, 1.35rem);
            color: var(--text-dark);
        }

        .report-details p {
            margin: 0.45rem 0;
            word-break: break-word;
            color: var(--text-muted);
        }

        .report-details strong {
            color: var(--text-dark);
            font-variant-numeric: tabular-nums;
        }

        .report-details-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 0.85rem;
        }

        .report-details-actions {
            display: flex;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        #shifts-table td[data-label="Acción"] {
            white-space: nowrap;
        }

        @media (max-width: 767px) {
            .reports-page {
                padding: 1rem;
            }

            .header {
                flex-direction: column;
                align-items: stretch;
            }

            .logo-column {
                justify-content: center;
            }

            .reports-actions,
            .report-details-actions {
                width: 100%;
                flex-direction: column;
            }

            .reports-actions .btn,
            .report-details-actions .btn,
            .view-report-btn {
                width: 100%;
            }

            .reports-container {
                overflow-x: auto;
                overflow-y: hidden;
                border-radius: 1rem;
            }

            #shifts-table th {
                font-size: 0.68rem;
            }

            #shifts-table td {
                font-size: 0.82rem;
            }

            .view-report-btn,
            .report-details-actions .btn {
                min-height: 2.75rem;
            }
        }

        @media (max-width: 479px) {
            .reports-header-card,
            .reports-summary-card,
            .report-details {
                padding: 0.9rem;
            }

            .logo-column {
                display: none;
            }

            #shifts-table td[data-label="Acción"] {
                white-space: normal;
            }

            #shifts-table td[data-label="Acción"] .btn,
            #shifts-table td[data-label="Acción"] .view-report-btn {
                width: 100%;
                display: inline-flex;
            }
        }
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
                <button id="export-all-btn" class="btn btn-success">Generar reporte diario (Excel + PDF)</button>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script src="main.js"></script>
    <script src="reports.js"></script>
</body>
</html>