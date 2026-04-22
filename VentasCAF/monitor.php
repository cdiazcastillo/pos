<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['admin'], 'admin_login.php', 'index.php');
require_once 'includes/notification_helper.php';

$db = Database::getInstance();
$conn = $db->getConnection();
ensure_notification_logs_table($conn);

$user = $db->query('SELECT id, role, username FROM users WHERE id = ?', [$_SESSION['user_id']]);
if (!$user || ($user['role'] ?? '') !== 'admin') {
    die('Acceso denegado. Solo administradores.');
}

$open_shifts = $db->query("SELECT COUNT(*) as total FROM shifts WHERE status = 'open'");
$closed_today = $db->query("SELECT COUNT(*) as total FROM shifts WHERE status = 'closed' AND DATE(end_time) = CURDATE()");

$sales_today = $db->query(
    "SELECT COUNT(*) as total_sales, COALESCE(SUM(total_amount),0) as total_amount
     FROM sales
     WHERE status = 'completed' AND DATE(sale_time) = CURDATE()"
);

$notifications_summary_stmt = $conn->query(
    "SELECT
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_total,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_total
     FROM notification_logs"
);
$notifications_summary = $notifications_summary_stmt->fetch(PDO::FETCH_ASSOC) ?: ['sent_total' => 0, 'failed_total' => 0, 'pending_total' => 0];

$notifications_stmt = $conn->query(
    "SELECT id, notification_type, reference_id, recipient, subject, status, attempts, last_error, last_attempt_at, sent_at, created_at
     FROM notification_logs
     ORDER BY id DESC
     LIMIT 30"
);
$notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

$basePath = str_replace('\\\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = rtrim($basePath, '/');
$baseHref = ($basePath === '' || $basePath === '.') ? '/' : $basePath . '/';

function format_clp_local($amount) {
    return '$' . number_format((float)$amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Operativo - 4 Básico A</title>
    <base href="<?php echo htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        :root {
            --primary: #3457dc;
            --dark: #1f2937;
            --muted: #6b7280;
            --bg: #f4f6fb;
            --card: #fff;
            --ok: #1f9d61;
            --warn: #d97706;
            --danger: #dc3545;
            --border: #e5e7eb;
            --font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: var(--font);
            background: var(--bg);
            color: var(--dark);
            padding: 10px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            gap: 14px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary), #4f46e5);
            color: #fff;
            border-radius: 14px;
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--bg);
            padding: 2px 0 8px;
        }

        .header h1 { margin: 0; font-size: 1.3rem; }
        .header p { margin: 6px 0 0; opacity: 0.95; font-size: 0.9rem; }

        .actions { display: flex; gap: 8px; }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 10px 12px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.35);
        }

        .btn.secondary { background: #374151; border-color: #374151; }

                .logo-column img {
                    width: 62px;
                    height: 62px;
                    object-fit: contain;
                    border-radius: 10px;
                    border: 1px solid rgba(255,255,255,0.45);
                    padding: 4px;
                    background: rgba(255,255,255,0.14);
                }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
        }

        .card .label { font-size: 0.82rem; color: var(--muted); font-weight: 700; }
        .card .value { margin-top: 6px; font-size: 1.5rem; font-weight: 800; }

        .value.ok { color: var(--ok); }
        .value.warn { color: var(--warn); }
        .value.danger { color: var(--danger); }

        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
        }

        .monitor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }

        .metric-card {
            background: #f9fbff;
            border: 1px solid #e4e8f7;
            border-radius: 10px;
            padding: 12px;
        }

        .metric-label {
            margin: 0;
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 700;
        }

        .metric-value {
            margin: 8px 0 0;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--dark);
        }

        .monitor-panels {
            margin-top: 12px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 10px;
        }

        .subpanel {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            padding: 12px;
        }

        .subpanel h3 {
            margin: 0 0 8px;
            font-size: 0.92rem;
        }

        .list-clean {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 7px;
        }

        .list-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            align-items: center;
            border-bottom: 1px solid #f1f3f9;
            padding-bottom: 6px;
            font-size: 0.84rem;
        }

        .list-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .mini-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 3px 8px;
            font-size: 0.74rem;
            font-weight: 700;
            background: #eef2ff;
            color: #334155;
        }

        .mini-badge.warn { background: #ffedd5; color: #9a3412; }
        .mini-badge.danger { background: #fee2e2; color: #991b1b; }

        .tiny-muted {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.8rem;
        }

        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .panel-head h2 { margin: 0; font-size: 1rem; }
        .panel-head p { margin: 0; color: var(--muted); font-size: 0.88rem; }

        .btn-retry {
            border: none;
            background: var(--primary);
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font-weight: 700;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.86rem;
        }

        th, td {
            border-bottom: 1px solid var(--border);
            text-align: left;
            padding: 8px 6px;
            vertical-align: top;
        }

        th { color: var(--muted); font-size: 0.8rem; }

        .badge {
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }

        .badge.sent { background: #dcfce7; color: #166534; }
        .badge.failed { background: #fee2e2; color: #991b1b; }
        .badge.pending { background: #fef3c7; color: #92400e; }

        #toast {
            position: fixed;
            right: 16px;
            bottom: 16px;
            background: #111827;
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            opacity: 0;
            transform: translateY(6px);
            transition: all .2s ease;
        }

        #toast.show { opacity: 1; transform: translateY(0); }

        @media (max-width: 700px) {
            body { padding: 10px; }
            table { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sticky-top">
            <header class="header">
                <div>
                    <h1>Control Operativo Resumido</h1>
                    <p>Puntos clave del día y monitoreo de correos de cierre.</p>
                </div>
                <div class="logo-column">
                    <img src="img/logo.png" alt="Logo">
                </div>
                <div class="actions">
                    <a href="index.php" class="btn secondary">Regresar al POS</a>
                </div>
            </header>
        </div>

        <section class="grid">
            <article class="card">
                <div class="label">Turnos abiertos</div>
                <div class="value <?php echo intval($open_shifts['total'] ?? 0) > 0 ? 'warn' : 'ok'; ?>"><?php echo intval($open_shifts['total'] ?? 0); ?></div>
            </article>
            <article class="card">
                <div class="label">Turnos cerrados hoy</div>
                <div class="value"><?php echo intval($closed_today['total'] ?? 0); ?></div>
            </article>
            <article class="card">
                <div class="label">Ventas completadas hoy</div>
                <div class="value"><?php echo intval($sales_today['total_sales'] ?? 0); ?></div>
            </article>
            <article class="card">
                <div class="label">Monto vendido hoy</div>
                <div class="value"><?php echo format_clp_local($sales_today['total_amount'] ?? 0); ?></div>
            </article>
            <article class="card">
                <div class="label">Correos enviados</div>
                <div class="value ok"><?php echo intval($notifications_summary['sent_total'] ?? 0); ?></div>
            </article>
            <article class="card">
                <div class="label">Correos con fallo</div>
                <div class="value danger"><?php echo intval($notifications_summary['failed_total'] ?? 0); ?></div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Monitores útiles en tiempo real</h2>
                    <p>Indicadores financieros, stock crítico, top productos y tendencia semanal.</p>
                </div>
            </div>

            <div class="monitor-grid">
                <article class="metric-card">
                    <p class="metric-label">Ingreso neto hoy</p>
                    <p class="metric-value" id="metric-net-income">$0</p>
                </article>
                <article class="metric-card">
                    <p class="metric-label">Otros gastos hoy</p>
                    <p class="metric-value danger" id="metric-other-expenses">$0</p>
                </article>
                <article class="metric-card">
                    <p class="metric-label">Ticket promedio hoy</p>
                    <p class="metric-value" id="metric-avg-ticket">$0</p>
                </article>
                <article class="metric-card">
                    <p class="metric-label">Ventas última hora</p>
                    <p class="metric-value" id="metric-last-hour">0</p>
                </article>
                <article class="metric-card">
                    <p class="metric-label">Productos stock bajo</p>
                    <p class="metric-value warn" id="metric-low-stock">0</p>
                </article>
                <article class="metric-card">
                    <p class="metric-label">Productos sin stock</p>
                    <p class="metric-value danger" id="metric-out-stock">0</p>
                </article>
            </div>

            <div class="monitor-panels">
                <div class="subpanel">
                    <h3>Alertas de stock</h3>
                    <ul id="stock-alerts-list" class="list-clean">
                        <li class="list-row"><span>Cargando alertas...</span></li>
                    </ul>
                </div>
                <div class="subpanel">
                    <h3>Productos top del día</h3>
                    <ul id="top-products-list" class="list-clean">
                        <li class="list-row"><span>Cargando ranking...</span></li>
                    </ul>
                </div>
                <div class="subpanel">
                    <h3>Últimos otros gastos</h3>
                    <ul id="latest-expenses-list" class="list-clean">
                        <li class="list-row"><span>Cargando gastos...</span></li>
                    </ul>
                </div>
                <div class="subpanel">
                    <h3>Método de pago hoy</h3>
                    <ul id="payment-mix-list" class="list-clean">
                        <li class="list-row"><span>Cargando distribución...</span></li>
                    </ul>
                </div>
                <div class="subpanel">
                    <h3>Tendencia neta (7 días)</h3>
                    <ul id="trend-list" class="list-clean">
                        <li class="list-row"><span>Cargando tendencia...</span></li>
                    </ul>
                </div>
                <div class="subpanel">
                    <h3>Estado de turno abierto</h3>
                    <ul id="open-shift-list" class="list-clean">
                        <li class="list-row"><span>Sin datos todavía...</span></li>
                    </ul>
                </div>
            </div>

            <p id="monitor-updated-at" class="tiny-muted">Actualizando monitores...</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2>Notificaciones de correo</h2>
                    <p>Últimos 30 envíos registrados. Puedes reintentar fallidos/pending.</p>
                </div>
                <button id="retry-btn" class="btn-retry">Reintentar fallidos</button>
            </div>

            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Referencia</th>
                            <th>Destino</th>
                            <th>Estado</th>
                            <th>Intentos</th>
                            <th>Último error</th>
                            <th>Último intento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="8">Sin registros todavía.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $row): ?>
                                <tr>
                                    <td>#<?php echo intval($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['notification_type']); ?></td>
                                    <td><?php echo htmlspecialchars((string)($row['reference_id'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($row['recipient']); ?></td>
                                    <td><span class="badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                    <td><?php echo intval($row['attempts']); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_error'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['last_attempt_at'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="toast"></div>

    <script>
        const retryBtn = document.getElementById('retry-btn');
        const toast = document.getElementById('toast');
        const metricNetIncome = document.getElementById('metric-net-income');
        const metricOtherExpenses = document.getElementById('metric-other-expenses');
        const metricAvgTicket = document.getElementById('metric-avg-ticket');
        const metricLastHour = document.getElementById('metric-last-hour');
        const metricLowStock = document.getElementById('metric-low-stock');
        const metricOutStock = document.getElementById('metric-out-stock');
        const stockAlertsList = document.getElementById('stock-alerts-list');
        const topProductsList = document.getElementById('top-products-list');
        const latestExpensesList = document.getElementById('latest-expenses-list');
        const paymentMixList = document.getElementById('payment-mix-list');
        const trendList = document.getElementById('trend-list');
        const openShiftList = document.getElementById('open-shift-list');
        const monitorUpdatedAt = document.getElementById('monitor-updated-at');

        function formatClp(value) {
            return `$${Number(value || 0).toLocaleString('es-CL')}`;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatDateLocal(value) {
            if (!value) return '-';
            const parsed = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(parsed.getTime())) return String(value);
            return parsed.toLocaleString('es-CL');
        }

        function elapsedTextSince(value) {
            if (!value) return 'Sin turnos abiertos';
            const start = new Date(String(value).replace(' ', 'T'));
            if (Number.isNaN(start.getTime())) return 'Sin datos de inicio';
            const diffMs = Date.now() - start.getTime();
            const totalMinutes = Math.max(0, Math.floor(diffMs / 60000));
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;
            return `${hours}h ${minutes}m abierto`;
        }

        function showToast(message) {
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2600);
        }

        function renderList(container, items, emptyText) {
            if (!container) return;
            if (!Array.isArray(items) || items.length === 0) {
                container.innerHTML = `<li class="list-row"><span>${emptyText}</span></li>`;
                return;
            }
            container.innerHTML = items.join('');
        }

        async function loadMonitorSummary() {
            try {
                const response = await fetch('get_monitor_summary_api.php', { cache: 'no-store' });
                const payload = await response.json();
                if (!payload.success) {
                    showToast(payload.message || 'No se pudieron cargar los monitores.');
                    return;
                }

                const data = payload.data || {};
                const kpis = data.kpis || {};
                const paymentMix = data.payment_mix_today || {};

                metricNetIncome.textContent = formatClp(kpis.net_income_today || 0);
                metricOtherExpenses.textContent = formatClp(kpis.other_expenses_today || 0);
                metricAvgTicket.textContent = formatClp(kpis.avg_ticket_today || 0);
                metricLastHour.textContent = Number(kpis.sales_last_hour || 0).toLocaleString('es-CL');
                metricLowStock.textContent = Number(kpis.low_stock_count || 0).toLocaleString('es-CL');
                metricOutStock.textContent = Number(kpis.out_stock_count || 0).toLocaleString('es-CL');

                const stockAlertsRows = (data.stock_alerts || []).map(item => {
                    const stock = Number(item.stock_level || 0);
                    const warning = Number(item.min_stock_warning || 0);
                    const badgeClass = stock <= 0 ? 'danger' : 'warn';
                    const badgeText = stock <= 0 ? 'Sin stock' : 'Bajo stock';
                    return `<li class="list-row"><span>${escapeHtml(item.name)} (${stock}/${warning})</span><span class="mini-badge ${badgeClass}">${badgeText}</span></li>`;
                });
                renderList(stockAlertsList, stockAlertsRows, 'Sin alertas de stock.');

                const topProductsRows = (data.top_products_today || []).map(item => (
                    `<li class="list-row"><span>${escapeHtml(item.name)}</span><strong>${Number(item.sold_qty || 0).toLocaleString('es-CL')} uds</strong></li>`
                ));
                renderList(topProductsList, topProductsRows, 'No hay ventas hoy para ranking.');

                const latestExpensesRows = (data.latest_other_expenses || []).map(item => (
                    `<li class="list-row"><span>${escapeHtml(item.description)}</span><strong>${formatClp(item.amount || 0)}</strong></li>`
                ));
                renderList(latestExpensesList, latestExpensesRows, 'Sin otros gastos registrados.');

                const paymentMixRows = [
                    `<li class="list-row"><span>Efectivo</span><strong>${formatClp(paymentMix.cash?.amount || 0)} (${Number(paymentMix.cash?.qty || 0).toLocaleString('es-CL')})</strong></li>`,
                    `<li class="list-row"><span>Transferencia</span><strong>${formatClp(paymentMix.transfer?.amount || 0)} (${Number(paymentMix.transfer?.qty || 0).toLocaleString('es-CL')})</strong></li>`
                ];
                renderList(paymentMixList, paymentMixRows, 'Sin datos de método de pago.');

                const trendRows = (data.activity_trend_7d || []).map(item => (
                    `<li class="list-row"><span>${escapeHtml(item.day)}</span><strong>${formatClp(item.net || 0)}</strong></li>`
                ));
                renderList(trendList, trendRows, 'Sin actividad suficiente para tendencia.');

                const shiftRows = [
                    `<li class="list-row"><span>Turnos abiertos</span><strong>${Number(kpis.open_shifts || 0).toLocaleString('es-CL')}</strong></li>`,
                    `<li class="list-row"><span>Duración estimada</span><strong>${escapeHtml(elapsedTextSince(kpis.open_shift_earliest_start || null))}</strong></li>`,
                    `<li class="list-row"><span>Inicio más antiguo</span><strong>${escapeHtml(formatDateLocal(kpis.open_shift_earliest_start || null))}</strong></li>`
                ];
                renderList(openShiftList, shiftRows, 'Sin información de turnos abiertos.');

                monitorUpdatedAt.textContent = `Monitores actualizados: ${formatDateLocal(data.updated_at || null)}`;
            } catch (error) {
                showToast('Error de conexión cargando monitores.');
            }
        }

        loadMonitorSummary();
        setInterval(loadMonitorSummary, 20000);

        retryBtn.addEventListener('click', async () => {
            retryBtn.disabled = true;
            try {
                const formData = new FormData();
                formData.append('limit', '30');

                const response = await fetch('retry_notifications_api.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    const processed = data.data?.processed ?? 0;
                    const sent = data.data?.sent ?? 0;
                    const failed = data.data?.failed ?? 0;
                    showToast(`Reintento OK: ${sent} enviados, ${failed} fallidos, ${processed} procesados.`);
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    showToast(data.message || 'No fue posible reintentar envíos.');
                }
            } catch (error) {
                showToast('Error de conexión al reintentar notificaciones.');
            } finally {
                retryBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
