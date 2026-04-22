<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['admin'], 'admin_login.php', 'index.php');

$db = Database::getInstance();
$user = $db->query('SELECT id, role, username FROM users WHERE id = ?', [$_SESSION['user_id']]);
if (!$user || ($user['role'] ?? '') !== 'admin') {
    die('Acceso denegado. Solo administradores.');
}

// Obtener datos para el dashboard (valores ENTEROS)
$openShifts = $db->query("SELECT COUNT(*) as total FROM shifts WHERE status = 'open'");
$openShiftsCount = intval($openShifts['total'] ?? 0);

$closedToday = $db->query("SELECT COUNT(*) as total FROM shifts WHERE status = 'closed' AND DATE(end_time) = CURDATE()");
$closedTodayCount = intval($closedToday['total'] ?? 0);

$salesToday = $db->query(
    "SELECT COUNT(*) as total_sales, COALESCE(SUM(total_amount), 0) as total_amount FROM sales WHERE status = 'completed' AND DATE(sale_time) = CURDATE()"
);
$salesTodayCount = intval($salesToday['total_sales'] ?? 0);
$salesTodayAmount = intval($salesToday['total_amount'] ?? 0);

$expensesToday = $db->query(
    "SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE DATE(expense_time) = CURDATE() AND sale_id IS NULL"
);
$expensesTodayAmount = intval($expensesToday['total'] ?? 0);

$returnesToday = $db->query(
    "SELECT COALESCE(SUM(e.amount), 0) as total FROM expenses e INNER JOIN sales s ON s.id = e.sale_id WHERE e.sale_id IS NOT NULL AND DATE(e.expense_time) = CURDATE()"
);
$returnesTodayAmount = intval($returnesToday['total'] ?? 0);

$netToday = $salesTodayAmount - $returnesTodayAmount - $expensesTodayAmount;

// Top 5 productos vendidos (cantidad ENTERO)
$topProducts = $db->query(
    "SELECT p.name, COALESCE(SUM(si.quantity - si.quantity_returned), 0) as qty
     FROM products p
     LEFT JOIN sale_items si ON si.product_id = p.id
     LEFT JOIN sales s ON s.id = si.sale_id AND s.status = 'completed'
     WHERE DATE(s.sale_time) = CURDATE()
     GROUP BY p.id, p.name
     HAVING qty > 0
     ORDER BY qty DESC LIMIT 5",
    [],
    true
) ?: [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Financiero - VentasCAF POS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #7c3aed;
            --bg-app: #f3f0ff;
            --bg-card: #ffffff;
            --text-dark: #1e1b4b;
            --text-muted: #6b7280;
            --border-soft: #e5e7eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
        }

        html, body {
            width: 100%;
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Poppins', sans-serif;
            background: var(--bg-app);
            color: var(--text-dark);
            overflow: hidden;
        }

        .app-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* Header Premium */
        header {
            background: linear-gradient(135deg, var(--primary) 0%, #a855f7 100%);
            padding: 20px 24px;
            color: white;
            box-shadow: 0 4px 20px rgba(124, 58, 237, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn-header {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-header:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            align-content: start;
        }

        /* Card Base Style - iOS inspired */
        .card {
            background: var(--bg-card);
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06), 0 4px 16px rgba(124, 58, 237, 0.08);
            border: 1px solid rgba(124, 58, 237, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.12);
        }

        .card.card-full {
            grid-column: 1 / -1;
        }

        /* Card Header con Emoji Icon */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .card-icon-large {
            font-size: 2.5rem;
            opacity: 0.8;
        }

        .card-meta {
            flex: 1;
        }

        .card-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .card-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--primary);
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
        }

        .card-value.success { color: var(--success); }
        .card-value.danger { color: var(--danger); }
        .card-value.warning { color: var(--warning); }

        .card-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 10px;
        }

        /* Badges/Pills */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 12px;
            width: fit-content;
        }

        .badge.success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge.danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge.warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge.info { background: rgba(59, 130, 246, 0.1); color: var(--info); }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 14px;
        }

        .stat-item {
            background: var(--bg-app);
            padding: 12px;
            border-radius: 14px;
            text-align: center;
            border: 1px solid rgba(124, 58, 237, 0.1);
        }

        .stat-item-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .stat-item-value {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-dark);
            font-variant-numeric: tabular-nums;
        }

        /* Progress Bar Rounded */
        .progress-container {
            margin-top: 16px;
        }

        .progress-bar-bg {
            height: 8px;
            background: rgba(124, 58, 237, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #7c3aed, #a855f7);
            border-radius: 10px;
            transition: width 0.4s ease;
        }

        /* Product Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .product-tile {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(168, 85, 247, 0.05));
            border: 1px solid rgba(124, 58, 237, 0.15);
            border-radius: 16px;
            padding: 14px 10px;
            text-align: center;
            transition: all 0.2s;
        }

        .product-tile:hover {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(168, 85, 247, 0.1));
            transform: scale(1.04);
        }

        .product-tile-name {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.3;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-tile-qty {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary);
            font-variant-numeric: tabular-nums;
        }

        .product-tile-unit {
            font-size: 0.65rem;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Button con Action */
        .btn-action {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary), #a855f7);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 12px;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(124, 58, 237, 0.3);
        }

        /* FAB Floating Action Button */
        .fab {
            position: fixed;
            bottom: 32px;
            right: 32px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #a855f7);
            color: white;
            border: none;
            font-size: 28px;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 100;
        }

        .fab:hover {
            transform: scale(1.12) translateY(-3px);
            box-shadow: 0 12px 32px rgba(124, 58, 237, 0.45);
        }

        .fab:active {
            transform: scale(0.96);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .main-content {
                grid-template-columns: 1fr;
                padding: 16px;
                gap: 14px;
            }

            .card {
                padding: 16px;
                border-radius: 20px;
            }

            .card-value {
                font-size: 1.8rem;
            }

            .fab {
                bottom: 20px;
                right: 20px;
                width: 56px;
                height: 56px;
                font-size: 24px;
            }

            header h1 {
                font-size: 1.4rem;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card { animation: fadeIn 0.4s ease forwards; }
        .card:nth-child(2) { animation-delay: 0.05s; }
        .card:nth-child(3) { animation-delay: 0.1s; }
        .card:nth-child(4) { animation-delay: 0.15s; }
    </style>
</head>
<body>
    <div class="app-container">
        <header>
            <h1>📊 Monitor Financiero</h1>
            <div class="header-actions">
                <a href="admin.php" class="btn-header">← Panel Admin</a>
            </div>
        </header>

        <main class="main-content">
            <!-- Turno Activo -->
            <div class="card">
                <div class="card-header">
                    <div class="card-meta">
                        <div class="card-label">Turnos Activos</div>
                        <div class="card-value"><?php echo $openShiftsCount; ?></div>
                    </div>
                    <div class="card-icon-large">⏱️</div>
                </div>
                <div class="card-subtitle">Turno(s) abierto(s) ahora</div>
                <div class="badge success">Activos en vivo</div>
            </div>

            <!-- Ventas Hoy -->
            <div class="card">
                <div class="card-header">
                    <div class="card-meta">
                        <div class="card-label">Transacciones Hoy</div>
                        <div class="card-value"><?php echo $salesTodayCount; ?></div>
                    </div>
                    <div class="card-icon-large">🛍️</div>
                </div>
                <div class="card-subtitle">Ventas completadas</div>
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-item-label">Monto Total</div>
                        <div class="stat-item-value">$<?php echo number_format($salesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Ingresos Netos -->
            <div class="card">
                <div class="card-header">
                    <div class="card-meta">
                        <div class="card-label">Ingreso Neto</div>
                        <div class="card-value <?php echo ($netToday >= 0) ? 'success' : 'danger'; ?>">$<?php echo number_format($netToday, 0, '', '.'); ?></div>
                    </div>
                    <div class="card-icon-large">💰</div>
                </div>
                <div class="card-subtitle">Después de gastos y devoluciones</div>
                <?php if ($netToday < 0): ?>
                    <div class="badge danger">Negativo</div>
                <?php else: ?>
                    <div class="badge success">Positivo</div>
                <?php endif; ?>
            </div>

            <!-- Devoluciones -->
            <div class="card">
                <div class="card-header">
                    <div class="card-meta">
                        <div class="card-label">Devoluciones</div>
                        <div class="card-value danger">$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="card-icon-large">↩️</div>
                </div>
                <div class="card-subtitle">Reintegros procesados</div>
                <div class="badge warning">-$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></div>
            </div>

            <!-- Otros Gastos -->
            <div class="card">
                <div class="card-header">
                    <div class="card-meta">
                        <div class="card-label">Otros Gastos</div>
                        <div class="card-value warning">$<?php echo number_format($expensesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="card-icon-large">🔧</div>
                </div>
                <div class="card-subtitle">Gastos operativos</div>
                <button class="btn-action" onclick="location.href='expenses.php'">Ver Detalles →</button>
            </div>

            <!-- Turnos Cerrados -->
            <div class="card">
                <div class="card-header">
                    <div class="card-meta">
                        <div class="card-label">Cerrados Hoy</div>
                        <div class="card-value success"><?php echo $closedTodayCount; ?></div>
                    </div>
                    <div class="card-icon-large">✓</div>
                </div>
                <div class="card-subtitle">Turnos finalizados</div>
                <div class="badge success">Completados</div>
            </div>

            <!-- Top 5 Productos Full Width -->
            <div class="card card-full">
                <div class="card-header">
                    <div class="card-meta">
                        <div class="card-label">Top 5 Productos</div>
                    </div>
                    <div class="card-icon-large">⭐</div>
                </div>
                <?php if (count($topProducts) > 0): ?>
                <div class="products-grid">
                    <?php foreach ($topProducts as $product): ?>
                    <div class="product-tile">
                        <div class="product-tile-name"><?php echo htmlspecialchars(substr($product['name'], 0, 18)); ?></div>
                        <div class="product-tile-qty"><?php echo intval($product['qty']); ?></div>
                        <div class="product-tile-unit">Vendidas</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📈</div>
                    <p>Sin ventas registradas hoy</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Resumen Diario Full Width -->
            <div class="card card-full">
                <div class="card-header">
                    <div class="card-meta">
                        <div class="card-label">Resumen Diario</div>
                    </div>
                    <div class="card-icon-large">📊</div>
                </div>
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-item-label">Bruta</div>
                        <div class="stat-item-value">$<?php echo number_format($salesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-item-label">Devoluciones</div>
                        <div class="stat-item-value danger">-$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-item-label">Gastos</div>
                        <div class="stat-item-value warning">-$<?php echo number_format($expensesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-item-label">Neto Final</div>
                        <div class="stat-item-value" style="color: <?php echo ($netToday >= 0) ? 'var(--success)' : 'var(--danger)'; ?>">$<?php echo number_format($netToday, 0, '', '.'); ?></div>
                    </div>
                </div>
                <div class="progress-container">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" style="width: <?php echo ($salesTodayAmount > 0) ? min(100, max(5, ($netToday / $salesTodayAmount) * 100)) : 0; ?>%"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- FAB -->
    <button class="fab" onclick="location.href='admin.php'" title="Volver al panel">📋</button>

    <script>
        // Auto-refresh cada 30 segundos
        setInterval(() => { location.reload(); }, 30000);
    </script>
</body>
</html>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #7c3aed;
            --primary-light: #a78bfa;
            --bg-light: #f3f0ff;
            --bg-white: #ffffff;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        html, body { width: 100%; height: 100%; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif; background: var(--bg-light); color: var(--text-dark); overflow: hidden; }
        .container { display: flex; height: 100vh; flex-direction: column; }
        header { background: var(--bg-white); border-bottom: 1px solid var(--border); padding: 18px 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 1.5rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 8px; }
        .header-buttons { display: flex; gap: 8px; }
        .btn-header { padding: 8px 14px; border: none; border-radius: 6px; background: var(--text-muted); color: var(--bg-white); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-header:hover { background: #4b5563; transform: translateY(-1px); }
        .main-content { flex: 1; overflow-y: auto; padding: 24px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: var(--bg-white); border-radius: 20px; padding: 20px; box-shadow: 0 4px 12px rgba(124,58,237,0.08); border: 1px solid rgba(124,58,237,0.1); transition: all 0.3s; display: flex; flex-direction: column; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(124,58,237,0.12); }
        .card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .card-title { font-size: 0.95rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .card-icon { font-size: 1.8rem; }
        .card-value { font-size: 2.5rem; font-weight: 800; color: var(--primary); margin: 8px 0; font-variant-numeric: tabular-nums; }
        .card-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 8px; }
        .card-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-top: 8px; width: fit-content; }
        .badge-success { background: rgba(16,185,129,0.1); color: var(--success); }
        .badge-danger { background: rgba(239,68,68,0.1); color: var(--danger); }
        .badge-warning { background: rgba(245,158,11,0.1); color: var(--warning); }
        .card-full { grid-column: 1 / -1; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; }
        .product-item { background: var(--bg-light); border-radius: 12px; padding: 12px; text-align: center; border: 1px solid rgba(124,58,237,0.2); transition: all 0.2s; }
        .product-item:hover { background: rgba(124,58,237,0.05); transform: scale(1.02); }
        .product-name { font-size: 0.8rem; font-weight: 600; color: var(--text-dark); margin-bottom: 6px; line-height: 1.2; }
        .product-qty { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 12px; }
        .stat-mini { background: var(--bg-light); padding: 12px; border-radius: 10px; border: 1px solid rgba(124,58,237,0.15); }
        .stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 4px; }
        .stat-value { font-size: 1.3rem; font-weight: 700; color: var(--primary); font-variant-numeric: tabular-nums; }
        .progress-bar { height: 6px; background: rgba(124,58,237,0.1); border-radius: 3px; overflow: hidden; margin-top: 8px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--primary-light)); border-radius: 3px; transition: width 0.3s ease; }
        .fab { position: fixed; bottom: 30px; right: 30px; width: 56px; height: 56px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); border-radius: 50%; border: none; color: var(--bg-white); font-size: 1.5rem; cursor: pointer; box-shadow: 0 8px 24px rgba(124,58,237,0.4); transition: all 0.3s; z-index: 100; display: flex; align-items: center; justify-content: center; }
        .fab:hover { transform: scale(1.1) rotate(90deg); box-shadow: 0 12px 32px rgba(124,58,237,0.5); }
        .fab:active { transform: scale(0.95); }
        @media (max-width: 768px) { header { flex-direction: column; align-items: flex-start; gap: 12px; } .main-content { grid-template-columns: 1fr; padding: 16px; gap: 16px; } .card { padding: 16px; border-radius: 16px; } .card-value { font-size: 2rem; } .fab { bottom: 20px; right: 20px; width: 48px; height: 48px; font-size: 1.2rem; } }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 Monitor en Tiempo Real</h1>
            <div class="header-buttons">
                <a href="admin.php" class="btn-header">← Volver al Panel</a>
            </div>
        </header>

        <main class="main-content">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Turnos Activos</div>
                        <div class="card-value"><?php echo $openShiftsCount; ?></div>
                    </div>
                    <div class="card-icon">⏱️</div>
                </div>
                <div class="card-subtitle">Turno(s) abierto(s) en el sistema</div>
                <div class="card-badge badge-success">Activos en vivo</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Ventas Hoy</div>
                        <div class="card-value"><?php echo $salesTodayCount; ?></div>
                    </div>
                    <div class="card-icon">🛍️</div>
                </div>
                <div class="card-subtitle">Transacciones completadas</div>
                <div class="stats-row">
                    <div class="stat-mini">
                        <div class="stat-label">Total</div>
                        <div class="stat-value">$<?php echo number_format($salesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Ingresos Netos</div>
                        <div class="card-value">$<?php echo number_format($netToday, 0, '', '.'); ?></div>
                    </div>
                    <div class="card-icon">💰</div>
                </div>
                <div class="card-subtitle">Después de devoluciones y gastos</div>
                <?php if ($netToday < 0): ?>
                    <div class="card-badge badge-danger">Negativo</div>
                <?php else: ?>
                    <div class="card-badge badge-success">Positivo</div>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Devoluciones</div>
                        <div class="card-value">$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="card-icon">↩️</div>
                </div>
                <div class="card-subtitle">Reintegros de hoy</div>
                <div class="card-badge badge-warning">-$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Otros Gastos</div>
                        <div class="card-value">$<?php echo number_format($expensesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="card-icon">🔧</div>
                </div>
                <div class="card-subtitle">Gastos operativos hoy</div>
                <a href="expenses.php" style="margin-top: 10px; text-decoration: none;">
                    <button style="width: 100%; padding: 8px; background: var(--primary); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Ver Detalles →</button>
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Cerrados Hoy</div>
                        <div class="card-value"><?php echo $closedTodayCount; ?></div>
                    </div>
                    <div class="card-icon">✓</div>
                </div>
                <div class="card-subtitle">Turnos finalizados</div>
                <div class="card-badge badge-success">Completados</div>
            </div>

            <div class="card card-full">
                <div class="card-header">
                    <div>
                        <div class="card-title">Top 5 Productos Vendidos</div>
                    </div>
                    <div class="card-icon">⭐</div>
                </div>
                <?php if (count($topProducts) > 0): ?>
                <div class="products-grid">
                    <?php foreach ($topProducts as $i => $product): ?>
                    <div class="product-item">
                        <div class="product-name"><?php echo htmlspecialchars(substr($product['name'], 0, 20)); ?></div>
                        <div class="product-qty"><?php echo intval($product['qty']); ?></div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">unidades</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                    <p>Sin ventas registradas hoy</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="card card-full">
                <div class="card-header">
                    <div>
                        <div class="card-title">Resumen del Día</div>
                    </div>
                    <div class="card-icon">📈</div>
                </div>
                <div class="stats-row">
                    <div class="stat-mini">
                        <div class="stat-label">Venta Bruta</div>
                        <div class="stat-value">$<?php echo number_format($salesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-label">Devoluciones</div>
                        <div class="stat-value">-$<?php echo number_format($returnesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-label">Gastos</div>
                        <div class="stat-value">-$<?php echo number_format($expensesTodayAmount, 0, '', '.'); ?></div>
                    </div>
                    <div class="stat-mini">
                        <div class="stat-label">Neto Final</div>
                        <div class="stat-value" style="color: <?php echo ($netToday >= 0) ? 'var(--success)' : 'var(--danger)'; ?>">$<?php echo number_format($netToday, 0, '', '.'); ?></div>
                    </div>
                </div>
                <div class="progress-bar" style="margin-top: 14px;">
                    <div class="progress-fill" style="width: <?php echo ($salesTodayAmount > 0) ? min(100, max(10, ($netToday / $salesTodayAmount) * 100)) : 0; ?>%"></div>
                </div>
            </div>
        </main>
    </div>

    <button class="fab" onclick="location.href='admin.php'" title="Ir al panel de admin">📋</button>

    <script>
        setInterval(() => { location.reload(); }, 30000);
    </script>
</body>
</html>
