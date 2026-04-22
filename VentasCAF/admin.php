<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['cashier', 'admin'], 'admin_login.php', 'index.php');
$isAdmin = (($currentUser['role'] ?? '') === 'admin');

$db = Database::getInstance();
$selectedShiftId = intval($_SESSION['selected_shift_id'] ?? 0);
$active_shift = null;

if ($selectedShiftId > 0) {
    $active_shift = $db->query(
        "SELECT s.id, s.user_id, s.start_time, u.username
         FROM shifts s
         JOIN users u ON u.id = s.user_id
         WHERE s.id = ? AND s.status = 'open'",
        [$selectedShiftId]
    );

    if (!$active_shift) {
        unset($_SESSION['selected_shift_id']);
    }
}

if (!$active_shift) {
    $active_shift = $db->query(
        "SELECT s.id, s.user_id, s.start_time, u.username
         FROM shifts s
         JOIN users u ON u.id = s.user_id
         WHERE s.user_id = ? AND s.status = 'open'",
        [$_SESSION['user_id']]
    );
    if ($active_shift) {
        $_SESSION['selected_shift_id'] = intval($active_shift['id']);
    }
}

$has_active_shift = $active_shift && isset($active_shift['id']);
$open_shifts_for_admin = $isAdmin
    ? ($db->query(
        "SELECT s.id, s.start_time, u.username
         FROM shifts s
         JOIN users u ON u.id = s.user_id
         WHERE s.status = 'open'
         ORDER BY s.start_time ASC",
        [],
        true
    ) ?: [])
    : [];

$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = rtrim($basePath, '/');
$baseHref = ($basePath === '' || $basePath === '.') ? '/' : $basePath . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú de Administración - 4 Básico A</title>
    <base href="<?php echo htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        :root {
            --primary-color: #3457dc;
            --primary-dark: #253ea8;
            --secondary-color: #6c757d;
            --success-color: #1f9d61;
            --danger-color: #dc3545;
            --light-gray: #f4f6fb;
            --card-bg: #ffffff;
            --dark-gray: #1f2937;
            --muted: #6b7280;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--light-gray);
            color: var(--dark-gray);
            margin: 0;
            min-height: 100vh;
            padding: 20px;
        }

        .admin-container {
            background-color: var(--card-bg);
            margin: 0 auto;
            max-width: 980px;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.15);
        }

        .admin-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px;
            background: linear-gradient(135deg, var(--primary-color), #4f46e5);
            color: white;
        }

        .logo-spotlight {
            width: 118px;
            height: 118px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 30% 30%, #ffffff, #eef2ff 68%, #dbe4ff 100%);
            border: 2px solid rgba(255, 255, 255, 0.78);
            box-shadow:
                0 16px 30px rgba(37, 62, 168, 0.35),
                0 0 0 6px rgba(255, 255, 255, 0.18);
            flex-shrink: 0;
        }

        .admin-header img {
            width: 84px;
            height: 84px;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(37, 62, 168, 0.2));
        }

        .header-text {
            flex: 1;
            text-align: left;
        }

        .admin-header h1 {
            margin: 0;
            font-size: 1.7rem;
            line-height: 1.2;
        }

        .admin-header p {
            margin: 8px 0 0;
            opacity: 0.95;
            font-size: 0.95rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 8px 12px;
            font-weight: 700;
            font-size: 0.84rem;
            background-color: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #9ca3af;
        }

        .status-pill.open .status-dot {
            background-color: #34d399;
        }

        .status-pill.closed .status-dot {
            background-color: #fbbf24;
        }

        .admin-main {
            padding: 24px;
            display: grid;
            gap: 24px;
        }

        .section-title {
            margin: 0 0 12px;
            font-size: 1.1rem;
        }

        .admin-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
        }

        .menu-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            gap: 8px;
            background-color: #f8faff;
            color: var(--dark-gray);
            border: 1px solid #e5eaf8;
            border-radius: 12px;
            padding: 16px;
            text-decoration: none;
            transition: all 0.2s ease;
            min-height: 118px;
        }

        .menu-card:hover {
            border-color: #cfd9ff;
            background-color: #eef3ff;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.12);
        }

        .card-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background-color: #dbe6ff;
            color: #253ea8;
        }

        .card-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            stroke-width: 1.5;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .card-title {
            font-weight: 700;
        }

        .card-subtitle {
            font-size: 0.9rem;
            color: var(--muted);
        }

        .shift-panel {
            border: 1px solid #e6e9f5;
            border-radius: 14px;
            padding: 18px;
            background-color: #fcfcff;
        }

        .panel-hidden {
            display: none;
        }

        .panel-visible {
            display: block;
        }

        .panel-note {
            margin: 0 0 8px;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .insight-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .insight-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            padding: 12px;
        }

        .insight-label {
            margin: 0;
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .insight-value {
            margin: 8px 0 0;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--dark-gray);
        }

        .insight-value.danger {
            color: var(--danger-color);
        }

        .insight-value.success {
            color: var(--success-color);
        }

        .insight-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 14px;
            margin-top: 14px;
        }

        .insight-block {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
        }

        .insight-block h3 {
            margin: 0 0 10px;
            font-size: 0.95rem;
        }

        .expense-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 8px;
            margin-bottom: 10px;
        }

        .expense-form input {
            height: 40px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 0 10px;
            font-size: 0.92rem;
        }

        .expense-form button {
            height: 40px;
            padding: 0 12px;
            border: none;
            border-radius: 10px;
            background: var(--primary-color);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .expense-table-wrap {
            overflow: auto;
            max-height: 280px;
            border: 1px solid #eef2f7;
            border-radius: 10px;
        }

        .expense-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        .expense-table th,
        .expense-table td {
            padding: 8px;
            border-bottom: 1px solid #eef2f7;
            text-align: left;
        }

        .chart-list {
            display: grid;
            gap: 8px;
        }

        .chart-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: center;
        }

        .chart-track {
            background: #eef2ff;
            border-radius: 8px;
            height: 22px;
            overflow: hidden;
            position: relative;
        }

        .chart-bar {
            height: 100%;
            display: flex;
            align-items: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.78rem;
            padding-left: 8px;
            white-space: nowrap;
            min-width: 34px;
        }

        .chart-bar.top {
            background: linear-gradient(90deg, #16a34a, #22c55e);
        }

        .chart-bar.low {
            background: linear-gradient(90deg, #f59e0b, #f97316);
        }

        .chart-qty {
            font-size: 0.82rem;
            font-weight: 700;
            color: #374151;
        }

        .danger-panel {
            border: 1px solid #fecaca;
            border-radius: 14px;
            padding: 18px;
            background-color: #fff6f6;
        }

        .danger-panel p {
            margin: 8px 0 0;
            color: #7f1d1d;
            font-size: 0.92rem;
        }

        .option-row {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.92rem;
            color: #7f1d1d;
            font-weight: 600;
        }

        .close-shift-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 16px;
            margin: 16px 0;
            padding: 0;
        }

        .summary-section {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            padding: 14px;
        }

        .summary-section h3 {
            margin: 0 0 12px;
            font-size: 1rem;
            color: #1f2937;
            font-weight: 700;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.9rem;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.summary-total {
            background-color: #f0f9ff;
            padding: 10px 8px;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            font-weight: 700;
            margin-top: 8px;
        }

        .summary-label {
            color: #6b7280;
            font-weight: 600;
        }

        .summary-value {
            font-weight: 700;
            color: #1f2937;
            font-size: 0.95rem;
        }

        .summary-value.success {
            color: var(--success-color);
        }

        .summary-value.danger {
            color: var(--danger-color);
        }

        .option-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--danger-color);
        }

        .shift-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
            margin-top: 12px;
        }

        .field-group {
            display: grid;
            gap: 6px;
        }

        .field-group label {
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: 600;
        }

        .money-input {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 0 12px;
            font-size: 1rem;
        }

        .money-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 87, 220, 0.18);
        }

        .btn-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .active-shifts-panel {
            margin-top: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
        }

        .active-shifts-head h3 {
            margin: 0;
            font-size: 0.96rem;
        }

        .active-shifts-head p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 0.86rem;
        }

        .active-shifts-table-wrap {
            margin-top: 10px;
            border: 1px solid #eef2f7;
            border-radius: 10px;
            overflow: auto;
        }

        .active-shifts-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
        }

        .active-shifts-table th,
        .active-shifts-table td {
            padding: 9px 8px;
            border-bottom: 1px solid #eef2f7;
            text-align: left;
            vertical-align: middle;
        }

        .active-shifts-table tbody tr:last-child td {
            border-bottom: none;
        }

        .shift-chip {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            background: #e5e7eb;
            color: #374151;
        }

        .shift-chip.selected {
            background: #dcfce7;
            color: #166534;
        }

        .shift-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .row-action-btn {
            padding: 8px 10px;
            font-size: 0.82rem;
        }

        .menu-button {
            border: none;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #fff;
            background-color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .menu-button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .menu-button.success {
            background-color: var(--success-color);
        }

        .menu-button.success:hover {
            background-color: #168553;
        }

        .menu-button.danger {
            background-color: var(--danger-color);
        }

        .menu-button.danger:hover {
            background-color: #b82331;
        }

        .menu-button:disabled,
        .menu-button.disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .footer-actions {
            display: flex;
            justify-content: flex-end;
            border-top: 1px solid #edf0f8;
            padding-top: 12px;
        }

        .menu-button.secondary {
            background-color: var(--secondary-color);
        }

        .menu-button.secondary:hover {
            background-color: #5a6268;
        }

        .menu-button.menu-green {
            background-color: var(--success-color);
        }

        .menu-button.menu-green:hover {
            background-color: #168553;
        }

        .menu-button.pos-red {
            background-color: var(--danger-color);
        }

        .menu-button.pos-red:hover {
            background-color: #b82331;
        }

        .menu-button.end-blue {
            background-color: #2563eb;
        }

        .menu-button.end-blue:hover {
            background-color: #1d4ed8;
        }

        #toast {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 999;
            min-width: 240px;
            max-width: 420px;
            padding: 12px 16px;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.25s ease;
            pointer-events: none;
            background-color: #111827;
        }

        #toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        #toast.error {
            background-color: var(--danger-color);
        }

        #toast.success {
            background-color: var(--success-color);
        }

        #action-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 1200;
        }

        #action-modal.show {
            display: flex;
        }

        .modal-card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 20px 35px rgba(15, 23, 42, 0.24);
            overflow: hidden;
        }

        .modal-head {
            padding: 14px 16px;
            border-bottom: 1px solid #eef2fb;
            background: #f8faff;
        }

        .modal-head h3 {
            margin: 0;
            font-size: 1.05rem;
        }

        .modal-body {
            padding: 14px 16px;
            color: #374151;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .modal-input-wrap {
            margin-top: 10px;
            display: none;
        }

        .modal-input-wrap.show {
            display: block;
        }

        .modal-input-wrap label {
            font-size: 0.86rem;
            font-weight: 700;
            color: #4b5563;
            display: block;
            margin-bottom: 6px;
        }

        #modal-input {
            width: 100%;
            height: 40px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 0 10px;
            font-size: 0.95rem;
        }

        #modal-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 87, 220, 0.16);
        }

        .modal-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            padding: 12px 16px 14px;
            border-top: 1px solid #eef2fb;
        }

        .modal-btn {
            border: none;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
        }

        .modal-btn.cancel {
            background: #e5e7eb;
            color: #374151;
        }

        .modal-btn.confirm {
            background: var(--primary-color);
            color: #fff;
        }

        .modal-btn.confirm.danger {
            background: var(--danger-color);
        }

        @media (max-width: 680px) {
            body {
                padding: 10px;
            }

            .admin-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .logo-spotlight {
                width: 98px;
                height: 98px;
            }

            .admin-header img {
                width: 70px;
                height: 70px;
            }

            .status-pill {
                margin-top: 6px;
            }

            .footer-actions {
                justify-content: stretch;
            }

            .footer-actions .menu-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div class="logo-spotlight">
                <img src="img/logo.png" alt="Logo">
            </div>
            <div class="header-text">
                <h1>Panel de Administración</h1>
            </div>
            <div class="status-pill <?php echo $has_active_shift ? 'open' : 'closed'; ?>">
                <span class="status-dot"></span>
                <?php echo $has_active_shift ? ('Turno #' . intval($active_shift['id'])) : 'Turno cerrado'; ?>
            </div>
        </div>

        <div class="admin-main">
            <section>
                <h2 class="section-title">Accesos rápidos</h2>
                <div class="admin-menu">
                    <a href="products.php" class="menu-card">
                        <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.5 12 3l9 4.5-9 4.5-9-4.5Z"/><path d="M3 7.5V16.5L12 21l9-4.5V7.5"/></svg></span>
                        <span class="card-title">Gestionar productos</span>
                        <span class="card-subtitle">Precios, stock y estado de venta.</span>
                    </a>
                    <a href="sales_history.php" class="menu-card">
                        <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a2 2 0 0 1 2 2v16l-2-1.5L15 21l-2-1.5L11 21l-2-1.5L7 21l-2-1.5V5a2 2 0 0 1 2-2Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
                        <span class="card-title">Historial de ventas</span>
                        <span class="card-subtitle">Consulta ventas, anulaciones y detalles.</span>
                    </a>
                    <a href="dashboard.php" class="menu-card">
                        <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20V8"/></svg></span>
                        <span class="card-title">Panel turno en curso</span>
                        <span class="card-subtitle">Visualiza ventas del turno activo.</span>
                    </a>
                    <?php if ($isAdmin): ?>
                        <a href="reports.php" class="menu-card">
                            <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17 9 11l4 4 8-8"/><path d="M14 7h7v7"/></svg></span>
                            <span class="card-title">Reportes de turno</span>
                            <span class="card-subtitle">Exporta y revisa resultados diarios.</span>
                        </a>
                        <a href="totals.php" class="menu-card">
                            <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18"/><path d="M17 7.5c0-1.9-2.2-3.5-5-3.5s-5 1.6-5 3.5 2.2 3.5 5 3.5 5 1.6 5 3.5-2.2 3.5-5 3.5-5-1.6-5-3.5"/></svg></span>
                            <span class="card-title">Ventas totales</span>
                            <span class="card-subtitle">Resumen acumulado del negocio.</span>
                        </a>
                        <a href="monitor.php" class="menu-card">
                            <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 16 8-8"/><path d="m11 7 6 6"/><path d="M3 12h5M16 12h5M12 3v5M12 16v5"/></svg></span>
                            <span class="card-title">Control operativo</span>
                            <span class="card-subtitle">Resumen clave y monitoreo de correos.</span>
                        </a>
                    <?php endif; ?>
                    <a href="expenses.php" class="menu-card">
                        <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/><path d="M7 14h4"/></svg></span>
                        <span class="card-title">OTROS GASTOS</span>
                        <span class="card-subtitle">Abrir página nueva para registrar, editar y eliminar gastos.</span>
                    </a>
                    <?php if ($isAdmin): ?>
                        <a href="#" id="open-security-manager-btn" class="menu-card">
                            <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20V8"/></svg></span>
                            <span class="card-title">Cierre de Turnos</span>
                            <span class="card-subtitle">Cuadre de caja y cierre operativo con resumen financiero.</span>
                        </a>
                        <a href="#" id="open-realtime-insights-btn" class="menu-card">
                            <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/></svg></span>
                            <span class="card-title">Insights en tiempo real</span>
                            <span class="card-subtitle">Ingresos netos, otros gastos y productos más/menos vendidos.</span>
                        </a>
                        <a href="permissions.php" class="menu-card">
                            <span class="card-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="3"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a3 3 0 0 1 0 5.8"/></svg></span>
                            <span class="card-title">Permisos</span>
                            <span class="card-subtitle">Configurar qué puede ver cada rol del sistema.</span>
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <section id="shift-manager-panel" class="shift-panel panel-hidden">
                <h2 class="section-title">Gestión de turno</h2>
                <p class="panel-note">Usa este panel para abrir o cerrar turno de forma rápida.</p>
                <div class="shift-grid">
                    <div class="field-group">
                        <label for="initial-cash">Efectivo inicial para abrir turno</label>
                        <input id="initial-cash" class="money-input" type="text" inputmode="numeric" pattern="[0-9]*" min="0" step="1" placeholder="Ej: 50000">
                    </div>
                    <div class="field-group">
                        <label for="final-cash">Efectivo final para cerrar turno</label>
                        <input id="final-cash" class="money-input" type="text" inputmode="numeric" pattern="[0-9]*" min="0" step="1" placeholder="Ej: 145000">
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="field-group">
                        <label for="admin-shift-target">Turno para trabajar</label>
                        <select id="admin-shift-target" class="money-input" style="height:42px;">
                            <option value="new">Crear mi propio turno</option>
                            <?php foreach ($open_shifts_for_admin as $openShift): ?>
                                <option value="<?php echo intval($openShift['id']); ?>" <?php echo ($has_active_shift && intval($active_shift['id'] ?? 0) === intval($openShift['id'])) ? 'selected' : ''; ?>>
                                    Turno #<?php echo intval($openShift['id']); ?> · <?php echo htmlspecialchars($openShift['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="btn-row">
                    <button id="start-shift-btn" class="menu-button success" <?php if ($has_active_shift) echo 'disabled'; ?>>Iniciar turno</button>
                    <button id="end-shift-btn" class="menu-button end-blue" <?php if (!$has_active_shift) echo 'disabled'; ?>>Terminar turno</button>
                    <a href="index.php" class="menu-button secondary">Regresar al inicio</a>
                </div>

                <?php if ($isAdmin): ?>
                <div class="active-shifts-panel">
                    <div class="active-shifts-head">
                        <h3>Turnos activos del equipo</h3>
                        <p>Usa estas acciones rápidas para trabajar o cerrar un turno específico.</p>
                    </div>
                    <?php if (empty($open_shifts_for_admin)): ?>
                        <p class="panel-note" style="margin-top:10px;">No hay turnos activos en este momento.</p>
                    <?php else: ?>
                        <div class="active-shifts-table-wrap">
                            <table class="active-shifts-table">
                                <thead>
                                    <tr>
                                        <th>Turno</th>
                                        <th>Usuario</th>
                                        <th>Inicio</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($open_shifts_for_admin as $openShift): ?>
                                        <?php
                                            $rowShiftId = intval($openShift['id'] ?? 0);
                                            $isRowSelected = (intval($active_shift['id'] ?? 0) === $rowShiftId);
                                            $rowStartRaw = (string)($openShift['start_time'] ?? '');
                                            $rowStartTs = strtotime($rowStartRaw);
                                            $rowStartLabel = $rowStartTs ? date('d-m-Y H:i', $rowStartTs) : $rowStartRaw;
                                        ?>
                                        <tr>
                                            <td>#<?php echo $rowShiftId; ?></td>
                                            <td><?php echo htmlspecialchars((string)($openShift['username'] ?? 'usuario')); ?></td>
                                            <td><?php echo htmlspecialchars($rowStartLabel); ?></td>
                                            <td>
                                                <span class="shift-chip <?php echo $isRowSelected ? 'selected' : ''; ?>">
                                                    <?php echo $isRowSelected ? 'En uso' : 'Disponible'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="shift-actions">
                                                    <button
                                                        type="button"
                                                        class="menu-button secondary row-action-btn quick-use-shift-btn"
                                                        data-shift-id="<?php echo $rowShiftId; ?>"
                                                        <?php echo $isRowSelected ? 'disabled' : ''; ?>
                                                    >
                                                        <?php echo $isRowSelected ? 'En uso' : 'Usar este turno'; ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="menu-button end-blue row-action-btn quick-close-shift-btn"
                                                        data-shift-id="<?php echo $rowShiftId; ?>"
                                                    >
                                                        Cerrar turno
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="insight-block" style="margin-top:14px;">
                    <h3>Otros gastos rápidos</h3>
                    <p class="panel-note">Registra un gasto indicando si salió de efectivo o transferencia.</p>
                    <div class="expense-form">
                        <input id="shift-expense-note-input" type="text" maxlength="255" placeholder="Ej: Servilletas">
                        <input id="shift-expense-amount-input" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Monto">
                        <select id="shift-expense-method-input" class="money-input" style="height:40px;">
                            <option value="cash">Efectivo</option>
                            <option value="transfer">Transferencia</option>
                        </select>
                        <button id="shift-save-expense-btn" type="button">Guardar gasto</button>
                    </div>
                </div>
            </section>

            <?php if ($isAdmin): ?>
            <section id="security-manager-panel" class="danger-panel panel-hidden">
                <h2 class="section-title">Cierre de Turnos - Cuadre de Caja</h2>
                <p>Revisa el resumen financiero del turno actual y cuadra el efectivo. Todos los valores deben coincidir para cerrar correctamente.</p>
                
                <div class="close-shift-summary">
                    <div class="summary-section">
                        <h3>Resumen Financiero</h3>
                        <div class="summary-row">
                            <span class="summary-label">Efectivo inicial del turno:</span>
                            <span id="close-shift-initial-cash" class="summary-value">$0</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Ventas en efectivo:</span>
                            <span id="close-shift-cash-sales" class="summary-value success">$0</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Ventas por transferencia:</span>
                            <span id="close-shift-transfer-sales" class="summary-value success">$0</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Devoluciones/Anulaciones:</span>
                            <span id="close-shift-returns" class="summary-value danger">($0)</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Otros gastos:</span>
                            <span id="close-shift-expenses" class="summary-value danger">($0)</span>
                        </div>
                        <div class="summary-row summary-total">
                            <span class="summary-label">Efectivo esperado en caja:</span>
                            <span id="close-shift-expected-cash" class="summary-value">$0</span>
                        </div>
                    </div>

                    <div class="summary-section">
                        <h3>Cuadre Final</h3>
                        <div class="field-group">
                            <label for="close-shift-actual-cash">Efectivo real contado en caja</label>
                            <input id="close-shift-actual-cash" class="money-input" type="text" inputmode="numeric" pattern="[0-9]*" min="0" step="1" placeholder="Ingresa cantidad total contada">
                        </div>
                        <div id="close-shift-difference" class="summary-row">
                            <span class="summary-label">Diferencia:</span>
                            <span id="close-shift-diff-value" class="summary-value">$0</span>
                        </div>
                    </div>
                </div>

                <div class="shift-grid">
                    <div class="field-group">
                        <label for="reset-initial-cash">Efectivo inicial del nuevo turno (opcional)</label>
                        <input id="reset-initial-cash" class="money-input" type="text" inputmode="numeric" pattern="[0-9]*" min="0" step="1" placeholder="Si lo ingresas, abre un turno nuevo automáticamente">
                    </div>
                    <div class="field-group">
                        <label for="reset-security-key">Clave de seguridad</label>
                        <input id="reset-security-key" class="money-input" type="password" inputmode="numeric" placeholder="Ingresa clave (ej: 250012)">
                    </div>
                </div>
                <label class="option-row" for="clear-products-checkbox">
                    <input id="clear-products-checkbox" type="checkbox" checked>
                    Vaciar también la lista completa de productos
                </label>
                <div class="btn-row">
                    <button id="reset-operations-btn" class="menu-button danger">Cerrar turnos y reiniciar ventas</button>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <section id="realtime-insights-panel" class="shift-panel panel-hidden">
                <h2 class="section-title">Resumen de Turno Actual</h2>
                <p class="panel-note">Ingresos, gastos y productos más/menos vendidos. Los gastos en efectivo se descuentan del ingreso neto.</p>

                <div class="insight-grid">
                    <article class="insight-card">
                        <p class="insight-label">Ventas brutas completadas</p>
                        <p id="kpi-gross-sales" class="insight-value">$0</p>
                    </article>
                    <article class="insight-card">
                        <p class="insight-label">Devoluciones / anulaciones</p>
                        <p id="kpi-returns" class="insight-value danger">$0</p>
                    </article>
                    <article class="insight-card">
                        <p class="insight-label">Otros gastos</p>
                        <p id="kpi-other-expenses" class="insight-value danger">$0</p>
                    </article>
                    <article class="insight-card">
                        <p class="insight-label">Ingreso neto final (descontado)</p>
                        <p id="kpi-net-income" class="insight-value success">$0</p>
                    </article>
                </div>

                <div class="insight-sections">
                    <div class="insight-block">
                        <h3>Otros gastos (nota + monto)</h3>
                        <div class="expense-form">
                            <input id="expense-note-input" type="text" maxlength="255" placeholder="Ej: Compra de bolsas, cambio, transporte...">
                            <input id="expense-amount-input" type="text" inputmode="numeric" pattern="[0-9]*" min="1" step="1" placeholder="Monto">
                            <button id="save-expense-btn" type="button">Guardar</button>
                        </div>
                        <div class="expense-table-wrap">
                            <table class="expense-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Nota</th>
                                        <th>Monto</th>
                                    </tr>
                                </thead>
                                <tbody id="other-expenses-body">
                                    <tr><td colspan="4">Cargando gastos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="insight-block">
                        <h3>Productos más vendidos</h3>
                        <div id="top-products-chart" class="chart-list"></div>
                    </div>

                    <div class="insight-block">
                        <h3>Productos menos vendidos</h3>
                        <div id="least-products-chart" class="chart-list"></div>
                    </div>
                </div>

                <p class="panel-note" id="realtime-updated-at">Actualizando datos...</p>
            </section>
            <?php endif; ?>

            <div class="footer-actions">
                <button id="end-shift-footer-btn" class="menu-button end-blue">Terminar turno</button>
                <a href="index.php" class="menu-button pos-red">Volver al POS</a>
                <a href="logout.php" class="menu-button danger" style="margin-left:8px;">Cerrar sesión</a>
            </div>
        </div>
    </div>

    <div id="toast" role="status" aria-live="polite"></div>

    <div id="action-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-card">
            <div class="modal-head">
                <h3 id="modal-title">Confirmar acción</h3>
            </div>
            <div class="modal-body">
                <div id="modal-message"></div>
                <div id="modal-input-wrap" class="modal-input-wrap">
                    <label id="modal-input-label" for="modal-input">Confirmación</label>
                    <input id="modal-input" type="text" autocomplete="off">
                </div>
            </div>
            <div class="modal-actions">
                <button id="modal-cancel-btn" type="button" class="modal-btn cancel">Cancelar</button>
                <button id="modal-confirm-btn" type="button" class="modal-btn confirm">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
        const hasActiveShift = <?php echo $has_active_shift ? 'true' : 'false'; ?>;
        const isAdminUser = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        const selectedShiftId = <?php echo intval($active_shift['id'] ?? 0); ?>;
        const startShiftBtn = document.getElementById('start-shift-btn');
        const endShiftBtn = document.getElementById('end-shift-btn');
        const initialCashInput = document.getElementById('initial-cash');
        const finalCashInput = document.getElementById('final-cash');
        const resetInitialCashInput = document.getElementById('reset-initial-cash');
        const resetSecurityKeyInput = document.getElementById('reset-security-key');
        const clearProductsCheckbox = document.getElementById('clear-products-checkbox');
        const resetOperationsBtn = document.getElementById('reset-operations-btn');
        const openShiftManagerBtn = document.getElementById('open-shift-manager-btn');
        const openSecurityManagerBtn = document.getElementById('open-security-manager-btn');
        const openRealtimeInsightsBtn = document.getElementById('open-realtime-insights-btn');
        const shiftManagerPanel = document.getElementById('shift-manager-panel');
        const securityManagerPanel = document.getElementById('security-manager-panel');
        const realtimeInsightsPanel = document.getElementById('realtime-insights-panel');
        const kpiGrossSales = document.getElementById('kpi-gross-sales');
        const kpiReturns = document.getElementById('kpi-returns');
        const kpiOtherExpenses = document.getElementById('kpi-other-expenses');
        const kpiNetIncome = document.getElementById('kpi-net-income');
        const otherExpensesBody = document.getElementById('other-expenses-body');
        const topProductsChart = document.getElementById('top-products-chart');
        const leastProductsChart = document.getElementById('least-products-chart');
        const realtimeUpdatedAt = document.getElementById('realtime-updated-at');
        const expenseNoteInput = document.getElementById('expense-note-input');
        const expenseAmountInput = document.getElementById('expense-amount-input');
        const saveExpenseBtn = document.getElementById('save-expense-btn');
        const shiftExpenseNoteInput = document.getElementById('shift-expense-note-input');
        const shiftExpenseAmountInput = document.getElementById('shift-expense-amount-input');
        const shiftExpenseMethodInput = document.getElementById('shift-expense-method-input');
        const shiftSaveExpenseBtn = document.getElementById('shift-save-expense-btn');
        const toast = document.getElementById('toast');
        const actionModal = document.getElementById('action-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalMessage = document.getElementById('modal-message');
        const modalInputWrap = document.getElementById('modal-input-wrap');
        const modalInputLabel = document.getElementById('modal-input-label');
        const modalInput = document.getElementById('modal-input');
        const modalCancelBtn = document.getElementById('modal-cancel-btn');
        const modalConfirmBtn = document.getElementById('modal-confirm-btn');
        const adminShiftTarget = document.getElementById('admin-shift-target');
        const quickUseShiftButtons = document.querySelectorAll('.quick-use-shift-btn');
        const quickCloseShiftButtons = document.querySelectorAll('.quick-close-shift-btn');
        const canLoadRealtimeSummary = isAdminUser && !!realtimeInsightsPanel;
        let currentSelectedShiftId = Number(selectedShiftId) || 0;

        function showToast(message, isError = false) {
            toast.textContent = message;
            toast.classList.remove('error', 'success', 'show');
            toast.classList.add(isError ? 'error' : 'success');
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => toast.classList.remove('show'), 2800);
        }

        function parseAmount(value) {
            const normalized = String(value).replace(/\./g, '').replace(/\s+/g, '').trim();
            if (!/^\d+$/.test(normalized)) {
                return NaN;
            }
            const amount = Number(normalized);
            return Number.isInteger(amount) ? amount : NaN;
        }

        function bindNumericInput(inputElement) {
            if (!inputElement) return;
            inputElement.addEventListener('input', () => {
                inputElement.value = String(inputElement.value).replace(/\D/g, '');
            });
        }

        [initialCashInput, finalCashInput, resetInitialCashInput, resetSecurityKeyInput, expenseAmountInput, shiftExpenseAmountInput].forEach(bindNumericInput);

        function formatClp(value) {
            return `$${Number(value || 0).toLocaleString('es-CL')}`;
        }

        async function postForm(url, payload) {
            const formData = new FormData();
            Object.entries(payload).forEach(([key, value]) => formData.append(key, value));

            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            return response.json();
        }

        function syncAdminShiftTarget(shiftId) {
            if (!adminShiftTarget) return;
            const targetValue = String(shiftId || 'new');
            const hasOption = Array.from(adminShiftTarget.options).some(option => option.value === targetValue);
            adminShiftTarget.value = hasOption ? targetValue : 'new';
        }

        async function selectShiftForWork(shiftId, showSuccessToast = true) {
            const shiftNumber = Number(shiftId);
            if (!Number.isInteger(shiftNumber) || shiftNumber <= 0) {
                showToast('Selecciona un turno válido.', true);
                return false;
            }

            try {
                const data = await postForm('start_shift_api.php', {
                    mode: 'join',
                    shift_id: shiftNumber
                });

                if (!data.success) {
                    showToast(data.message ? `Error: ${data.message}` : 'No se pudo seleccionar el turno.', true);
                    return false;
                }

                currentSelectedShiftId = shiftNumber;
                syncAdminShiftTarget(shiftNumber);
                if (showSuccessToast) {
                    showToast('Turno compartido seleccionado correctamente.');
                }
                return true;
            } catch (error) {
                showToast('Error de conexión al seleccionar el turno.', true);
                return false;
            }
        }

        function showOnlyPanel(panelToShow) {
            [shiftManagerPanel, securityManagerPanel, realtimeInsightsPanel].forEach(panel => {
                if (!panel) return;
                panel.classList.add('panel-hidden');
                panel.classList.remove('panel-visible');
            });

            if (panelToShow) {
                panelToShow.classList.remove('panel-hidden');
                panelToShow.classList.add('panel-visible');
                panelToShow.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            if (panelToShow === realtimeInsightsPanel) {
                startRealtimeUpdates();
            } else {
                stopRealtimeUpdates();
            }
        }

        openShiftManagerBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            showOnlyPanel(shiftManagerPanel);
        });

        openSecurityManagerBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            showOnlyPanel(securityManagerPanel);
            loadClosShiftSummary();
        });

        openRealtimeInsightsBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            showOnlyPanel(realtimeInsightsPanel);
        });

        let realtimeTimer = null;

        function renderProductBars(container, products, barClass, emptyMessage) {
            if (!container) return;
            const validProducts = Array.isArray(products) ? products : [];
            if (validProducts.length === 0) {
                container.innerHTML = `<p class="panel-note">${emptyMessage}</p>`;
                return;
            }

            const maxQty = Math.max(...validProducts.map(item => Number(item.sold_qty || 0)), 1);
            container.innerHTML = validProducts.map(item => {
                const qty = Number(item.sold_qty || 0);
                const width = Math.max(14, Math.round((qty / maxQty) * 100));
                const label = String(item.name || 'Producto');
                return `
                    <div class="chart-item">
                        <div class="chart-track">
                            <div class="chart-bar ${barClass}" style="width:${width}%">${label}</div>
                        </div>
                        <span class="chart-qty">${qty}</span>
                    </div>
                `;
            }).join('');
        }

        function renderOtherExpensesRows(rows) {
            if (!otherExpensesBody) return;
            const data = Array.isArray(rows) ? rows : [];
            if (data.length === 0) {
                otherExpensesBody.innerHTML = '<tr><td colspan="4">Sin gastos registrados.</td></tr>';
                return;
            }

            otherExpensesBody.innerHTML = data.map(row => {
                const dateValue = row.expense_time ? new Date(row.expense_time.replace(' ', 'T')) : null;
                const formattedDate = dateValue && !Number.isNaN(dateValue.getTime())
                    ? dateValue.toLocaleString('es-CL')
                    : (row.expense_time || '-');
                const note = String(row.description || '-').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const paymentMethod = row.payment_method === 'transfer' ? 'Transferencia' : 'Efectivo';
                return `
                    <tr>
                        <td>${formattedDate}</td>
                        <td>${paymentMethod}</td>
                        <td>${note}</td>
                        <td>${formatClp(row.amount || 0)}</td>
                    </tr>
                `;
            }).join('');
        }

        async function loadRealtimeSummary() {
            if (!canLoadRealtimeSummary) {
                return;
            }
            try {
                const response = await fetch('get_admin_realtime_summary_api.php', { cache: 'no-store' });
                const payload = await response.json();
                if (!payload.success) {
                    showToast(payload.message || 'No se pudo cargar el resumen en tiempo real.', true);
                    return;
                }

                const income = payload.data?.income || {};
                kpiGrossSales.textContent = formatClp(income.gross_sales || 0);
                kpiReturns.textContent = formatClp(income.returns || 0);
                kpiOtherExpenses.textContent = formatClp(income.other_expenses || 0);
                kpiNetIncome.textContent = formatClp(income.net_income_after_expenses || 0);

                renderOtherExpensesRows(payload.data?.other_expenses_notes || []);
                renderProductBars(topProductsChart, payload.data?.top_products || [], 'top', 'Sin ventas suficientes para ranking.');
                renderProductBars(leastProductsChart, payload.data?.least_products || [], 'low', 'Sin ventas suficientes para ranking.');

                const updated = payload.data?.updated_at || null;
                realtimeUpdatedAt.textContent = updated
                    ? `Actualizado: ${new Date(updated.replace(' ', 'T')).toLocaleString('es-CL')}`
                    : 'Actualizado recientemente';
            } catch (error) {
                showToast('No se pudo conectar para actualizar insights.', true);
            }
        }

        function startRealtimeUpdates() {
            stopRealtimeUpdates();
            loadRealtimeSummary();
            realtimeTimer = setInterval(loadRealtimeSummary, 15000);
        }

        function stopRealtimeUpdates() {
            if (realtimeTimer) {
                clearInterval(realtimeTimer);
                realtimeTimer = null;
            }
        }

        async function loadClosShiftSummary() {
            try {
                const response = await fetch('get_admin_realtime_summary_api.php', { cache: 'no-store' });
                const payload = await response.json();
                if (!payload.success) {
                    return;
                }

                const income = payload.data?.income || {};
                const initialCash = income.initial_cash || 0;
                const cashSales = income.cash_sales || 0;
                const transferSales = income.transfer_sales || 0;
                const returns = income.returns || 0;
                const otherExpenses = income.other_expenses || 0;
                
                // Calcular efectivo esperado: inicial + ventas cash + ventas transfer - devoluciones - gastos
                const expectedCash = initialCash + cashSales + transferSales - returns - otherExpenses;

                document.getElementById('close-shift-initial-cash').textContent = formatClp(initialCash);
                document.getElementById('close-shift-cash-sales').textContent = formatClp(cashSales);
                document.getElementById('close-shift-transfer-sales').textContent = formatClp(transferSales);
                document.getElementById('close-shift-returns').textContent = formatClp(returns);
                document.getElementById('close-shift-expenses').textContent = formatClp(otherExpenses);
                document.getElementById('close-shift-expected-cash').textContent = formatClp(expectedCash);

                // Listeners para calcular diferencia cuando se ingresa efectivo real
                const actualCashInput = document.getElementById('close-shift-actual-cash');
                actualCashInput.addEventListener('input', () => {
                    const actual = parseAmount(actualCashInput.value) || 0;
                    const difference = actual - expectedCash;
                    const diffElement = document.getElementById('close-shift-diff-value');
                    diffElement.textContent = formatClp(difference);
                    diffElement.className = 'summary-value';
                    if (difference > 0) {
                        diffElement.classList.add('success');
                    } else if (difference < 0) {
                        diffElement.classList.add('danger');
                    }
                });
            } catch (error) {
                console.error('Error al cargar resumen cuadre:', error);
            }
        }

        quickUseShiftButtons.forEach(button => {
            button.addEventListener('click', async () => {
                const shiftId = Number(button.dataset.shiftId || 0);
                if (!shiftId) {
                    showToast('Turno inválido.', true);
                    return;
                }

                if (shiftId === currentSelectedShiftId) {
                    showToast('Ese turno ya está en uso.');
                    return;
                }

                const confirmSwitch = await openActionModal({
                    title: 'Usar turno activo',
                    message: `Trabajarás sobre el turno <strong>#${shiftId}</strong>.`,
                    confirmText: 'Usar turno',
                    confirmDanger: false
                });
                if (!confirmSwitch.confirmed) {
                    return;
                }

                button.disabled = true;
                try {
                    const selected = await selectShiftForWork(shiftId, false);
                    if (!selected) {
                        return;
                    }

                    showToast('Turno seleccionado correctamente.');
                    setTimeout(() => window.location.reload(), 600);
                } finally {
                    button.disabled = false;
                }
            });
        });

        quickCloseShiftButtons.forEach(button => {
            button.addEventListener('click', async () => {
                const shiftId = Number(button.dataset.shiftId || 0);
                if (!shiftId) {
                    showToast('Turno inválido.', true);
                    return;
                }

                const closePrompt = await openActionModal({
                    title: `Cerrar turno #${shiftId}`,
                    message: 'Ingresa el efectivo final para cerrar este turno.',
                    confirmText: 'Cerrar turno',
                    confirmDanger: true,
                    inputLabel: 'Efectivo final',
                    inputPlaceholder: 'Ej: 145000',
                    requireInput: true
                });
                if (!closePrompt.confirmed) {
                    return;
                }

                const finalCashAmount = parseAmount(closePrompt.value);
                if (!Number.isFinite(finalCashAmount) || finalCashAmount < 0) {
                    showToast('Ingresa un efectivo final válido.', true);
                    return;
                }

                button.disabled = true;
                try {
                    if (shiftId !== currentSelectedShiftId) {
                        const selected = await selectShiftForWork(shiftId, false);
                        if (!selected) {
                            return;
                        }
                    }

                    const data = await postForm('end_shift_api.php', { final_cash: finalCashAmount });
                    if (data.success) {
                        showToast(`Turno #${shiftId} cerrado correctamente.`);
                        setTimeout(() => window.location.reload(), 700);
                        return;
                    }

                    showToast(data.message ? `Error: ${data.message}` : 'No se pudo cerrar el turno.', true);
                } catch (error) {
                    showToast('Error de conexión al cerrar el turno.', true);
                } finally {
                    button.disabled = false;
                }
            });
        });

        saveExpenseBtn?.addEventListener('click', async () => {
            const description = expenseNoteInput.value.trim();
            const amountValue = parseAmount(expenseAmountInput.value);

            if (!description) {
                showToast('Escribe la nota del gasto.', true);
                expenseNoteInput.focus();
                return;
            }

            if (!Number.isFinite(amountValue) || amountValue <= 0) {
                showToast('Ingresa un monto válido para el gasto.', true);
                expenseAmountInput.focus();
                return;
            }

            saveExpenseBtn.disabled = true;
            try {
                const data = await postForm('register_expense_api.php', {
                    description,
                    amount: amountValue,
                    payment_method: 'cash'
                });

                if (!data.success) {
                    showToast(data.message || 'No se pudo guardar el gasto.', true);
                    return;
                }

                expenseNoteInput.value = '';
                expenseAmountInput.value = '';
                showToast('Gasto guardado y descontado de ingresos.');
                await loadRealtimeSummary();
            } catch (error) {
                showToast('Error de conexión al guardar el gasto.', true);
            } finally {
                saveExpenseBtn.disabled = false;
            }
        });

        shiftSaveExpenseBtn?.addEventListener('click', async () => {
            const description = shiftExpenseNoteInput.value.trim();
            const amountValue = parseAmount(shiftExpenseAmountInput.value);
            const paymentMethod = shiftExpenseMethodInput.value === 'transfer' ? 'transfer' : 'cash';

            if (!description) {
                showToast('Escribe la nota del gasto.', true);
                shiftExpenseNoteInput.focus();
                return;
            }

            if (!Number.isFinite(amountValue) || amountValue <= 0) {
                showToast('Ingresa un monto válido para el gasto.', true);
                shiftExpenseAmountInput.focus();
                return;
            }

            shiftSaveExpenseBtn.disabled = true;
            try {
                const data = await postForm('register_expense_api.php', {
                    description,
                    amount: amountValue,
                    payment_method: paymentMethod
                });

                if (!data.success) {
                    showToast(data.message || 'No se pudo guardar el gasto.', true);
                    return;
                }

                shiftExpenseNoteInput.value = '';
                shiftExpenseAmountInput.value = '';
                shiftExpenseMethodInput.value = 'cash';
                showToast('Gasto guardado correctamente.');
                if (canLoadRealtimeSummary) {
                    await loadRealtimeSummary();
                }
            } catch (error) {
                showToast('Error de conexión al guardar el gasto.', true);
            } finally {
                shiftSaveExpenseBtn.disabled = false;
            }
        });

        function openActionModal(options) {
            const {
                title,
                message,
                confirmText = 'Confirmar',
                cancelText = 'Cancelar',
                confirmDanger = false,
                inputLabel = '',
                inputPlaceholder = '',
                inputValue = '',
                requireInput = false
            } = options;

            modalTitle.textContent = title;
            modalMessage.innerHTML = message;
            modalCancelBtn.textContent = cancelText;
            modalConfirmBtn.textContent = confirmText;
            modalConfirmBtn.classList.toggle('danger', confirmDanger);

            if (inputLabel) {
                modalInputWrap.classList.add('show');
                modalInputLabel.textContent = inputLabel;
                modalInput.placeholder = inputPlaceholder;
                modalInput.value = inputValue;
            } else {
                modalInputWrap.classList.remove('show');
                modalInput.value = '';
            }

            actionModal.classList.add('show');
            if (inputLabel) {
                setTimeout(() => modalInput.focus(), 0);
            } else {
                setTimeout(() => modalConfirmBtn.focus(), 0);
            }

            return new Promise(resolve => {
                const cleanup = () => {
                    actionModal.classList.remove('show');
                    modalCancelBtn.removeEventListener('click', onCancel);
                    modalConfirmBtn.removeEventListener('click', onConfirm);
                    actionModal.removeEventListener('click', onBackdropClick);
                };

                const onCancel = () => {
                    cleanup();
                    resolve({ confirmed: false, value: '' });
                };

                const onConfirm = () => {
                    const value = modalInput.value.trim();
                    if (requireInput && !value) {
                        modalInput.focus();
                        return;
                    }
                    cleanup();
                    resolve({ confirmed: true, value });
                };

                const onBackdropClick = (event) => {
                    if (event.target === actionModal) {
                        onCancel();
                    }
                };

                modalCancelBtn.addEventListener('click', onCancel);
                modalConfirmBtn.addEventListener('click', onConfirm);
                actionModal.addEventListener('click', onBackdropClick);
            });
        }

        startShiftBtn.addEventListener('click', async () => {
            if (!isAdminUser && hasActiveShift) {
                showToast('Ya existe un turno activo.', true);
                return;
            }

            if (isAdminUser && adminShiftTarget && adminShiftTarget.value !== 'new') {
                startShiftBtn.disabled = true;
                try {
                    const selected = await selectShiftForWork(adminShiftTarget.value, true);
                    if (selected) {
                        setTimeout(() => window.location.reload(), 600);
                        return;
                    }
                } finally {
                    startShiftBtn.disabled = false;
                }
                return;
            }

            const amount = parseAmount(initialCashInput.value);
            if (!Number.isFinite(amount) || amount < 0) {
                showToast('Ingresa un efectivo inicial válido.', true);
                initialCashInput.focus();
                return;
            }

            const startConfirm = await openActionModal({
                title: 'Confirmar inicio de turno',
                message: `Se iniciará el turno con efectivo inicial <strong>$${amount.toLocaleString('es-CL')}</strong>.`,
                confirmText: 'Iniciar turno',
                confirmDanger: false
            });
            if (!startConfirm.confirmed) {
                return;
            }

            startShiftBtn.disabled = true;
            try {
                const data = await postForm('start_shift_api.php', { initial_cash: amount, mode: 'own' });
                if (data.success) {
                    showToast('Turno iniciado correctamente.');
                    setTimeout(() => window.location.reload(), 700);
                    return;
                }
                showToast(data.message ? `Error: ${data.message}` : 'No se pudo iniciar el turno.', true);
            } catch (error) {
                showToast('Error de conexión al iniciar turno.', true);
            } finally {
                startShiftBtn.disabled = <?php echo $has_active_shift ? 'true' : 'false'; ?>;
            }
        });

        endShiftBtn.addEventListener('click', async () => {
            if (!hasActiveShift) {
                showToast('No hay turno activo para cerrar.', true);
                return;
            }

            const amount = parseAmount(finalCashInput.value);
            if (!Number.isFinite(amount) || amount < 0) {
                showToast('Ingresa un efectivo final válido.', true);
                finalCashInput.focus();
                return;
            }

            const endConfirm = await openActionModal({
                title: 'Confirmar cierre de turno',
                message: `Se cerrará el turno con efectivo final <strong>$${amount.toLocaleString('es-CL')}</strong>.`,
                confirmText: 'Cerrar turno',
                confirmDanger: true
            });
            if (!endConfirm.confirmed) {
                return;
            }

            endShiftBtn.disabled = true;
            try {
                const data = await postForm('end_shift_api.php', { final_cash: amount });
                if (data.success) {
                    showToast('Turno cerrado correctamente.');
                    setTimeout(() => window.location.reload(), 700);
                    return;
                }
                showToast(data.message ? `Error: ${data.message}` : 'No se pudo cerrar el turno.', true);
            } catch (error) {
                showToast('Error de conexión al cerrar turno.', true);
            } finally {
                endShiftBtn.disabled = <?php echo !$has_active_shift ? 'true' : 'false'; ?>;
            }
        });

        resetOperationsBtn.addEventListener('click', async () => {
            const securityKey = resetSecurityKeyInput.value.trim();
            if (securityKey !== '250012') {
                showToast('Clave de seguridad incorrecta.', true);
                resetSecurityKeyInput.focus();
                return;
            }

            const resetConfirm = await openActionModal({
                title: 'Reinicio operativo',
                message: 'Esta acción cerrará todos los turnos, limpiará ventas y gastos. ¿Deseas continuar?',
                confirmText: 'Ejecutar reinicio',
                confirmDanger: true
            });
            if (!resetConfirm.confirmed) {
                return;
            }

            const resetAmount = resetInitialCashInput.value.trim();
            if (resetAmount !== '') {
                const parsed = parseAmount(resetAmount);
                if (!Number.isFinite(parsed) || parsed < 0) {
                    showToast('El efectivo inicial del reinicio no es válido.', true);
                    resetInitialCashInput.focus();
                    return;
                }
            }

            resetOperationsBtn.disabled = true;
            try {
                const payload = { security_key: securityKey };
                if (resetAmount !== '') {
                    payload.initial_cash = parseAmount(resetAmount);
                }
                payload.clear_products = clearProductsCheckbox.checked ? '1' : '0';

                const data = await postForm('reset_operations_api.php', payload);
                if (data.success) {
                    showToast('Reinicio completado. Recargando panel...');
                    setTimeout(() => window.location.reload(), 900);
                    return;
                }
                showToast(data.message ? `Error: ${data.message}` : 'No se pudo ejecutar el reinicio.', true);
            } catch (error) {
                showToast('Error de conexión durante el reinicio.', true);
            } finally {
                resetOperationsBtn.disabled = false;
            }
        });
    </script>
</body>
</html>