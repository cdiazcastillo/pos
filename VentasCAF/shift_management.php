<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['cashier', 'admin'], 'admin_login.php', 'index.php');
$isAdmin = (($currentUser['role'] ?? '') === 'admin');

$db = Database::getInstance();
$userId = intval($_SESSION['user_id'] ?? 0);

// Obtener turno activo del usuario
$activeShift = $db->query(
    "SELECT s.id, s.user_id, s.start_time, s.initial_cash, u.username
     FROM shifts s
     JOIN users u ON u.id = s.user_id
     WHERE s.user_id = ? AND s.status = 'open'
     ORDER BY s.start_time DESC LIMIT 1",
    [$userId]
);

$hasActiveShift = $activeShift && isset($activeShift['id']);
$activeShiftId = $activeShift ? intval($activeShift['id']) : 0;

// Para admin, obtener todos los turnos abiertos
$openShifts = $isAdmin
    ? ($db->query(
        "SELECT s.id, s.user_id, s.start_time, s.initial_cash, u.username
         FROM shifts s
         JOIN users u ON u.id = s.user_id
         WHERE s.status = 'open'
         ORDER BY s.start_time DESC",
        [],
        true
    ) ?: [])
    : [];

$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Turno - VentasCAF POS</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary-color: #7c3aed;
            --danger-color: #fb7185;
            --warning-color: #f97316;
            --muted: #6b7280;
            --dark-gray: #1e1b4b;
            --light-bg: #f3f0ff;
            --border-color: #e5e7eb;
            --card-shadow: 0 0.5rem 1.5rem rgba(30, 27, 75, 0.1);
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-gray);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .header {
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .header-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 999rem;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 2.75rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: #fff;
        }

        .btn-primary:hover {
            background: #15803d;
        }

        .btn-secondary {
            background: #6b7280;
            color: #fff;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-menu {
            background: #16a34a;
            color: #fff;
        }

        .btn-menu:hover {
            background: #15803d;
        }

        .btn-pos {
            background: #dc2626;
            color: #fff;
        }

        .btn-pos:hover {
            background: #b91c1c;
        }

        .btn-logout {
            background: #2563eb;
            color: #fff;
        }

        .btn-logout:hover {
            background: #1d4ed8;
        }

        .main {
            flex: 1;
            padding: 24px 16px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 1.4rem;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--dark-gray);
        }

        .shift-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .status-item {
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: 1.25rem;
            padding: 14px;
            text-align: center;
        }

        .status-item-label {
            font-size: 0.85rem;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .status-item-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-gray);
        }

        .active-shift-banner {
            background: linear-gradient(135deg, #d1fae5 0%, #dbeafe 100%);
            border-left: 4px solid var(--primary-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .active-shift-banner h3 {
            margin: 0 0 8px 0;
            color: var(--primary-color);
            font-size: 1rem;
        }

        .active-shift-banner p {
            margin: 0;
            color: #047857;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--dark-gray);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 20px;
        }

        .btn-danger {
            background: var(--danger-color);
            color: #fff;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .shifts-list {
            margin-top: 20px;
        }

        .shift-item {
            background: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .shift-info {
            flex: 1;
            min-width: 200px;
        }

        .shift-info-label {
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .shift-info-value {
            font-weight: 600;
            color: var(--dark-gray);
        }

        .shift-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 14px 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s;
            z-index: 1000;
            max-width: 300px;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast.success {
            border-left: 4px solid var(--primary-color);
        }

        .toast.error {
            border-left: 4px solid var(--danger-color);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 12px;
        }

        @media (max-width: 640px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-buttons {
                width: 100%;
            }

            .header-buttons .btn {
                flex: 1;
                justify-content: center;
            }

            .button-group {
                grid-template-columns: 1fr;
            }

            .shift-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .shift-actions {
                width: 100%;
            }

            .shift-actions .btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>Gestión de Turno</h1>
            <div class="header-buttons">
                <a href="admin.php" class="btn btn-menu">Menú</a>
                <a href="index.php" class="btn btn-pos">POS</a>
                <a href="logout.php" class="btn btn-logout">Cerrar sesión</a>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="page-title">Panel de Control de Turnos</div>

        <?php if ($hasActiveShift): ?>
            <div class="active-shift-banner">
                <h3>Turno Activo</h3>
                <p>Trabajando en Turno #<?php echo intval($activeShift['id']); ?> desde <?php echo date('H:i:s', strtotime($activeShift['start_time'])); ?> • Usuario: <strong><?php echo htmlspecialchars($activeShift['username']); ?></strong></p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">Información del Turno</h2>
            
            <div class="shift-status">
                <div class="status-item">
                    <div class="status-item-label">Turno Activo</div>
                    <div class="status-item-value"><?php echo $hasActiveShift ? '#' . intval($activeShift['id']) : 'Ninguno'; ?></div>
                </div>
                <div class="status-item">
                    <div class="status-item-label">Usuario</div>
                    <div class="status-item-value"><?php echo htmlspecialchars($currentUser['username'] ?? 'N/A'); ?></div>
                </div>
                <div class="status-item">
                    <div class="status-item-label">Rol</div>
                    <div class="status-item-value"><?php echo ucfirst(htmlspecialchars($currentUser['role'] ?? 'N/A')); ?></div>
                </div>
                <div class="status-item">
                    <div class="status-item-label">Zona Horaria</div>
                    <div class="status-item-value">Chile (Santiago)</div>
                </div>
            </div>

            <?php if ($hasActiveShift): ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Efectivo Inicial Ingresado</label>
                    <input type="text" value="$<?php echo number_format(intval($activeShift['initial_cash']), 0, '', '.'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Hora de Inicio del Turno</label>
                    <input type="text" value="<?php echo date('d/m/Y H:i:s', strtotime($activeShift['start_time'])); ?>" disabled>
                </div>
            </div>

            <div class="button-group">
                <button class="btn btn-primary" onclick="goToCloseTurn()">Ir a Cierre de Caja</button>
                <button class="btn btn-danger" onclick="confirmCloseTurn()">❌ Cerrar Turno Ahora</button>
            </div>
            <?php else: ?>
            <div class="form-group">
                <label for="new-initial-cash">Efectivo Inicial para Abrir Turno</label>
                <input id="new-initial-cash" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Ej: 50000" value="">
            </div>

            <div class="button-group">
                <button class="btn btn-primary" onclick="openNewShift()">Abrir Nuevo Turno</button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($isAdmin && count($openShifts) > 0): ?>
        <div class="card">
            <h2 class="card-title">Turnos Abiertos del Equipo</h2>
            <div class="shifts-list">
                <?php foreach ($openShifts as $shift): ?>
                <div class="shift-item">
                    <div class="shift-info">
                        <div class="shift-info-label">Turno #<?php echo intval($shift['id']); ?> • <?php echo htmlspecialchars($shift['username']); ?></div>
                        <div class="shift-info-value">Inicial: $<?php echo number_format(intval($shift['initial_cash']), 0, '', '.'); ?> • Desde: <?php echo date('H:i:s', strtotime($shift['start_time'])); ?></div>
                    </div>
                    <div class="shift-actions">
                        <button class="btn btn-small btn-primary" onclick="selectShiftForWork(<?php echo intval($shift['id']); ?>)">Usar</button>
                        <button class="btn btn-small btn-danger" onclick="closeShift(<?php echo intval($shift['id']); ?>)">Cerrar</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$hasActiveShift && !$isAdmin): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📭</div>
            <p>No tienes un turno activo. Abre uno nuevo en la sección anterior.</p>
        </div>
        <?php endif; ?>
    </main>

    <div id="toast" class="toast"></div>

    <script>
        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${isError ? 'error' : 'success'} show`;
            setTimeout(() => toast.classList.remove('show'), 2800);
        }

        function parseAmount(value) {
            const normalized = String(value).replace(/\D/g, '');
            const amount = Number(normalized);
            return Number.isInteger(amount) ? amount : NaN;
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

        function goToCloseTurn() {
            document.getElementById('open-security-manager-btn')?.click?.();
            window.location.href = 'admin.php#security';
        }

        function confirmCloseTurn() {
            if (confirm('¿Deseas cerrar el turno actual? Asegúrate de haber cuadrado la caja.')) {
                closeTurn(<?php echo $activeShiftId; ?>);
            }
        }

        function openNewShift() {
            const initialCash = parseAmount(document.getElementById('new-initial-cash').value);
            if (isNaN(initialCash) || initialCash <= 0) {
                showToast('Ingresa un monto válido mayor a $0.', true);
                return;
            }

            postForm('start_shift_api.php', {
                mode: 'start',
                initial_cash: initialCash
            }).then(data => {
                if (data.success) {
                    showToast('Turno abierto correctamente.');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.message || 'No se pudo abrir el turno.', true);
                }
            }).catch(err => {
                showToast('Error al abrir el turno.', true);
                console.error(err);
            });
        }

        function closeTurn(shiftId) {
            postForm('end_shift_api.php', {
                shift_id: shiftId
            }).then(data => {
                if (data.success) {
                    showToast('Turno cerrado correctamente.');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.message || 'No se pudo cerrar el turno.', true);
                }
            }).catch(err => {
                showToast('Error al cerrar el turno.', true);
                console.error(err);
            });
        }

        function selectShiftForWork(shiftId) {
            postForm('start_shift_api.php', {
                mode: 'join',
                shift_id: shiftId
            }).then(data => {
                if (data.success) {
                    showToast('Turno seleccionado. Recargando...');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.message || 'No se pudo seleccionar el turno.', true);
                }
            }).catch(err => {
                showToast('Error al seleccionar el turno.', true);
                console.error(err);
            });
        }

        function closeShift(shiftId) {
            if (confirm('¿Cierras este turno?')) {
                closeTurn(shiftId);
            }
        }
    </script>
</body>
</html>
