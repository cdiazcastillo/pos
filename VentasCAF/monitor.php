<?php
session_start();
require_once 'config/db.php';
require_once 'includes/notification_helper.php';

if (!isset($_SESSION['user_id'])) {
    die('Acceso denegado. Por favor, inicie sesión.');
}

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
            padding: 18px;
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
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
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
        <header class="header">
            <div>
                <h1>Control Operativo Resumido</h1>
                <p>Puntos clave del día y monitoreo de correos de cierre.</p>
            </div>
            <div class="actions">
                <a href="admin.php" class="btn secondary">Volver a Admin</a>
            </div>
        </header>

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

        function showToast(message) {
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2600);
        }

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
