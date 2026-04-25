<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['admin'], 'admin_login.php', 'index.php');

$db = Database::getInstance();
$user = $db->query('SELECT id, role, username FROM users WHERE id = ?', [$_SESSION['user_id']]);
if (!$user || ($user['role'] ?? '') !== 'admin') {
    die('Acceso denegado. Solo administradores.');
}

$openShifts = $db->query("SELECT COUNT(*) as total FROM shifts WHERE status = 'open'");
$openShiftsCount = intval($openShifts['total'] ?? 0);

$closedToday = $db->query("SELECT COUNT(*) as total FROM shifts WHERE status = 'closed' AND DATE(end_time) = CURDATE()");
$closedTodayCount = intval($closedToday['total'] ?? 0);

$activeShift = $db->query("SELECT id FROM shifts WHERE status = 'open' ORDER BY start_time DESC LIMIT 1");
$activeShiftId = intval($activeShift['id'] ?? 0);

$salesTodayCount = 0;
$salesTodayAmount = 0;
$expensesTodayAmount = 0;
$returnesTodayAmount = 0;
$netToday = 0;

if ($activeShiftId > 0) {
    $salesToday = $db->query(
        "SELECT COUNT(*) as total_sales, COALESCE(SUM(total_amount), 0) as total_amount
         FROM sales
         WHERE shift_id = ? AND status = 'completed'",
        [$activeShiftId]
    );
    $salesTodayCount = intval($salesToday['total_sales'] ?? 0);
    $salesTodayAmount = intval($salesToday['total_amount'] ?? 0);

    $expensesToday = $db->query(
        "SELECT COALESCE(SUM(amount), 0) as total
         FROM expenses
         WHERE shift_id = ? AND sale_id IS NULL",
        [$activeShiftId]
    );
    $expensesTodayAmount = intval($expensesToday['total'] ?? 0);

    $returnesToday = $db->query(
        "SELECT COALESCE(SUM(amount), 0) as total
         FROM expenses
         WHERE shift_id = ? AND sale_id IS NOT NULL",
        [$activeShiftId]
    );
    $returnesTodayAmount = intval($returnesToday['total'] ?? 0);

    $netToday = $salesTodayAmount - $returnesTodayAmount - $expensesTodayAmount;
    if ($salesTodayAmount > $returnesTodayAmount) {
        $netToday = max(0, $netToday);
    }
}

$topProducts = $db->query(
    "SELECT p.name, COALESCE(SUM(si.quantity - si.quantity_returned), 0) as qty
     FROM products p
     LEFT JOIN sale_items si ON si.product_id = p.id
     LEFT JOIN sales s ON s.id = si.sale_id AND s.status = 'completed'
     WHERE s.shift_id = ?
     GROUP BY p.id, p.name
     HAVING qty > 0
     ORDER BY qty DESC LIMIT 5",
    [$activeShiftId],
    true
) ?: [];

$netRatio = ($salesTodayAmount > 0) ? max(0, min(100, (int)round(($netToday / $salesTodayAmount) * 100))) : 0;
$safeSales = max(1, $salesTodayAmount);
$returnSlice = max(0, min(100, (int)round(($returnesTodayAmount / $safeSales) * 100)));
$expenseSlice = max(0, min(100 - $returnSlice, (int)round(($expensesTodayAmount / $safeSales) * 100)));
$netSlice = max(0, 100 - $returnSlice - $expenseSlice);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Financiero - POS</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary: #7c3aed;
            --bg-app: #f3f0ff;
            --bg-card: #ffffff;
            --text-dark: #1e1b4b;
            --text-muted: #6b7280;
            --success: #14b8a6;
            --danger: #fb7185;
            --warning: #f97316;
            --blue: #3b82f6;
            --pink: #ec4899;
            --surface-shadow: 0 0.5rem 1.5rem rgba(30, 27, 75, 0.1);
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Poppins', 'Segoe UI', sans-serif;
            background: var(--bg-app);
            color: var(--text-dark);
        }

        body { overflow-x: hidden; }

        .app {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            padding-bottom: 5.5rem;
        }


        .content {
            width: min(100%, 80rem);
            margin-inline: auto;
            padding: 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr));
            gap: 1rem;
            align-content: start;
        }

        .card {
            background: var(--bg-card);
            border-radius: 1.4rem;
            box-shadow: var(--surface-shadow);
            padding: 1rem;
            display: grid;
            gap: 0.7rem;
        }

        .card.full { grid-column: 1 / -1; }

        .card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .kpi-label {
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 800;
        }

        .kpi-value {
            font-size: clamp(1.4rem, 4vw, 2rem);
            line-height: 1.1;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .kpi-value.success { color: var(--success); }
        .kpi-value.danger { color: var(--danger); }
        .kpi-value.warning { color: var(--warning); }

        .kpi-sub {
            color: var(--text-muted);
            font-size: 0.82rem;
        }

        .icon {
            inline-size: 1.4rem;
            block-size: 1.4rem;
            color: rgba(124, 58, 237, 0.75);
            flex-shrink: 0;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999rem;
            padding: 0.35rem 0.65rem;
            font-size: 0.72rem;
            font-weight: 700;
            width: fit-content;
        }

        .badge.success { background: rgba(20, 184, 166, 0.13); color: #0f766e; }
        .badge.warning { background: rgba(249, 115, 22, 0.12); color: #9a3412; }
        .badge.danger { background: rgba(251, 113, 133, 0.12); color: #9f1239; }

        .distribution-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
            gap: 0.65rem;
        }

        .dist-item {
            border-radius: 1rem;
            border: 0.06rem solid rgba(124, 58, 237, 0.12);
            background: #faf8ff;
            padding: 0.75rem;
            display: grid;
            gap: 0.2rem;
        }

        .dist-item .name {
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 700;
        }

        .dist-item .value {
            font-size: 1.05rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .dist-item .value.success { color: var(--success); }
        .dist-item .value.danger { color: var(--danger); }
        .dist-item .value.warning { color: var(--warning); }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
            gap: 0.65rem;
        }

        .stat {
            border-radius: 1rem;
            padding: 0.7rem;
            background: #faf8ff;
            border: 0.06rem solid rgba(124, 58, 237, 0.12);
        }

        .stat .label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
        }

        .stat .value {
            margin-top: 0.25rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            font-size: 1.05rem;
        }

        .donut-wrap {
            display: grid;
            place-items: center;
            margin-top: 0.3rem;
        }

        .donut {
            inline-size: min(14rem, 68vw);
            aspect-ratio: 1;
            border-radius: 50%;
            background:
                radial-gradient(circle at center, #fff 0 53%, transparent 54%),
                conic-gradient(
                    var(--success) 0 <?php echo $netSlice; ?>%,
                    var(--danger) <?php echo $netSlice; ?>% <?php echo ($netSlice + $returnSlice); ?>%,
                    var(--warning) <?php echo ($netSlice + $returnSlice); ?>% 100%
                );
            display: grid;
            place-items: center;
        }

        .donut-center {
            text-align: center;
        }

        .donut-center .small {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
        }

        .donut-center .amount {
            font-size: clamp(1rem, 3.2vw, 1.3rem);
            font-weight: 800;
        }

        .line-chart {
            width: 100%;
            height: clamp(7rem, 18vw, 10rem);
            border-radius: 1rem;
            background: linear-gradient(180deg, rgba(124, 58, 237, 0.12), rgba(124, 58, 237, 0.02));
            padding: 0.45rem;
        }

        .line-chart svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .progress-track {
            inline-size: 100%;
            block-size: 0.42rem;
            border-radius: 999rem;
            background: rgba(124, 58, 237, 0.13);
            overflow: hidden;
        }

        .progress-fill {
            block-size: 100%;
            background: linear-gradient(90deg, var(--primary), #8b5cf6);
            border-radius: inherit;
            width: <?php echo $netRatio; ?>%;
            min-width: 0.3rem;
        }

        .products {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
            gap: 0.6rem;
        }

        .product {
            border-radius: 1rem;
            border: 0.06rem solid rgba(124, 58, 237, 0.12);
            background: #faf8ff;
            padding: 0.7rem;
            display: grid;
            gap: 0.2rem;
        }

        .product .name {
            font-size: 0.77rem;
            color: var(--text-dark);
            font-weight: 600;
            line-height: 1.3;
            min-height: 2.1rem;
        }

        .product .qty {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary);
            font-variant-numeric: tabular-nums;
        }

        .tx-list {
            display: grid;
            gap: 0.65rem;
        }

        .tx-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.6rem;
            align-items: center;
            border-radius: 0.9rem;
            background: #faf8ff;
            padding: 0.65rem;
        }

        .tx-meta {
            min-width: 0;
        }

        .tx-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-dark);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .tx-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .tx-amount {
            font-size: 0.88rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            text-align: right;
        }

        .tx-amount.neg { color: #c2410c; }
        .tx-amount.pos { color: #111827; }

        .action-link {
            border: 0;
            border-radius: 0.85rem;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            color: #fff;
            font-weight: 700;
            font-size: 0.82rem;
            min-height: 2.75rem;
            padding: 0.55rem 0.8rem;
            width: 100%;
            cursor: pointer;
        }

        .bottom-nav {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background: #fff;
            border-top: 0.06rem solid rgba(124, 58, 237, 0.15);
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            padding: 0.5rem 0.75rem calc(0.5rem + env(safe-area-inset-bottom));
            gap: 0.5rem;
            z-index: 50;
        }

        .nav-item {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.2rem;
            min-height: 2.75rem;
            padding: 0.35rem 0.55rem;
            border-radius: 0.8rem;
        }

        .fab {
            inline-size: 3.2rem;
            block-size: 3.2rem;
            border-radius: 50%;
            border: 0;
            background: var(--text-dark);
            color: #fff;
            display: grid;
            place-items: center;
            text-decoration: none;
            min-width: 2.75rem;
            min-height: 2.75rem;
            box-shadow: 0 0.5rem 1rem rgba(30, 27, 75, 0.22);
        }

        @media (min-width: 48rem) {
            .content { padding: 1.25rem; gap: 1.1rem; }
        }

        @media (min-width: 80rem) {
            .app { padding-bottom: 0; }
            .bottom-nav {
                position: static;
                grid-template-columns: repeat(5, auto);
                justify-content: center;
                padding: 0.8rem;
                border-top: 0;
                border-bottom: 0.06rem solid rgba(124, 58, 237, 0.15);
                order: 1;
            }
            .content { order: 0; }
        }
    </style>
</head>
<body>
    <?php $activePage = 'admin'; include 'top-nav.php'; ?>
    <div class="app">
        <main class="content">
            <article class="card">
                <div class="card-head">
                    <div>
                        <div class="kpi-label">Turnos activos</div>
                        <div class="kpi-value"><?php echo $openShiftsCount; ?></div>
                    </div>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </div>
                <div class="kpi-sub">Turnos abiertos en este momento</div>
                <span class="badge success">Estado activo</span>
            </article>

            <article class="card">
                <div class="card-head">
                    <div>
                        <div class="kpi-label">Transacciones hoy</div>
                        <div class="kpi-value"><?php echo $salesTodayCount; ?></div>
                    </div>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>
                </div>
                <div class="kpi-sub">Ventas completadas del día</div>
                <div class="stats">
                    <div class="stat">
                        <div class="label">Monto total</div>
                        <div class="value">$<?php echo number_format($salesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                </div>
            </article>

            <article class="card">
                <div class="card-head">
                    <div>
                        <div class="kpi-label">Ingreso neto</div>
                        <div class="kpi-value <?php echo ($netToday >= 0) ? 'success' : 'danger'; ?>">$<?php echo number_format($netToday, 0, '', '.'); ?></div>
                    </div>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v18"/><path d="M17 7.5c0-1.9-2.2-3.5-5-3.5s-5 1.6-5 3.5 2.2 3.5 5 3.5 5 1.6 5 3.5-2.2 3.5-5 3.5-5-1.6-5-3.5"/></svg>
                </div>
                <div class="kpi-sub">Ventas menos devoluciones y gastos</div>
                <span class="badge <?php echo ($netToday >= 0) ? 'success' : 'danger'; ?>"><?php echo ($netToday >= 0) ? 'Resultado positivo' : 'Resultado negativo'; ?></span>
            </article>

            <article class="card">
                <div class="card-head">
                    <div>
                        <div class="kpi-label">Devoluciones</div>
                        <div class="kpi-value danger">$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 8H5v4"/><path d="M5 12a7 7 0 1 0 2-4.9"/></svg>
                </div>
                <div class="kpi-sub">Reintegros del día</div>
                <span class="badge warning">-$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></span>
            </article>

            <article class="card">
                <div class="card-head">
                    <div>
                        <div class="kpi-label">Otros gastos</div>
                        <div class="kpi-value warning">$<?php echo number_format($expensesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.5h.1a1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.5 1z"/></svg>
                </div>
                <div class="kpi-sub">Gastos operacionales del día</div>
                <button class="action-link" type="button" onclick="location.href='expenses.php'">Ver detalles</button>
            </article>

            <article class="card">
                <div class="card-head">
                    <div>
                        <div class="kpi-label">Turnos cerrados</div>
                        <div class="kpi-value success"><?php echo $closedTodayCount; ?></div>
                    </div>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                </div>
                <div class="kpi-sub">Turnos finalizados hoy</div>
                <span class="badge success">Operación cerrada</span>
            </article>

            <article class="card full">
                <div class="card-head">
                    <div>
                        <div class="kpi-label">Distribución diaria</div>
                        <div class="kpi-sub">Resumen actual de neto, devoluciones y gastos</div>
                    </div>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 21H4a1 1 0 0 1-1-1V3"/><path d="M7 14l4-4 3 3 5-5"/></svg>
                </div>

                <div class="distribution-grid" role="group" aria-label="Distribución de neto, devoluciones y gastos">
                    <div class="dist-item">
                        <div class="name">Neto</div>
                        <div class="value success">$<?php echo number_format($netToday, 0, '', '.'); ?></div>
                    </div>
                    <div class="dist-item">
                        <div class="name">Devoluciones</div>
                        <div class="value danger">-$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="dist-item">
                        <div class="name">Gastos</div>
                        <div class="value warning">-$<?php echo number_format($expensesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                </div>

                <div class="line-chart" aria-label="Evolución diaria">
                    <svg viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-hidden="true">
                        <defs>
                            <linearGradient id="lineFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#7c3aed" stop-opacity="0.35"/>
                                <stop offset="100%" stop-color="#7c3aed" stop-opacity="0.02"/>
                            </linearGradient>
                        </defs>
                        <path d="M0,34 L15,30 L30,24 L45,26 L60,18 L75,20 L90,12 L100,14 L100,40 L0,40 Z" fill="url(#lineFill)"/>
                        <path d="M0,34 L15,30 L30,24 L45,26 L60,18 L75,20 L90,12 L100,14" fill="none" stroke="#7c3aed" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>

                <div class="progress-track" aria-label="Progreso de margen neto">
                    <div class="progress-fill"></div>
                </div>
            </article>

            <article class="card full">
                <div class="card-head">
                    <div>
                        <div class="kpi-label">Top productos</div>
                    </div>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17.3 18.2 21l-1.6-7L22 9.2l-7.1-.6L12 2 9.1 8.6 2 9.2 7.4 14 5.8 21z"/></svg>
                </div>
                <?php if (count($topProducts) > 0): ?>
                    <div class="products">
                        <?php foreach ($topProducts as $product): ?>
                            <div class="product">
                                <div class="name"><?php echo htmlspecialchars(substr((string)$product['name'], 0, 24)); ?></div>
                                <div class="qty"><?php echo intval($product['qty']); ?></div>
                                <div class="kpi-sub">unidades</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="kpi-sub">Sin ventas registradas hoy.</div>
                <?php endif; ?>
            </article>

            <article class="card full">
                <div class="card-head">
                    <div class="kpi-label">Lista de movimientos</div>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>
                </div>
                <div class="tx-list">
                    <div class="tx-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                        <div class="tx-meta">
                            <div class="tx-title">Ventas del día</div>
                            <div class="tx-sub">Total bruto acumulado</div>
                        </div>
                        <div class="tx-amount pos">$<?php echo number_format($salesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="tx-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 8H5v4"/><path d="M5 12a7 7 0 1 0 2-4.9"/></svg>
                        <div class="tx-meta">
                            <div class="tx-title">Devoluciones</div>
                            <div class="tx-sub">Ajustes por reintegro</div>
                        </div>
                        <div class="tx-amount neg">-$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="tx-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h16"/><path d="M12 4v16"/></svg>
                        <div class="tx-meta">
                            <div class="tx-title">Otros gastos</div>
                            <div class="tx-sub">Gastos operacionales</div>
                        </div>
                        <div class="tx-amount neg">-$<?php echo number_format($expensesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                </div>
            </article>
        </main>

    </div>

    <script>
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
