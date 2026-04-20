<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    die('Acceso denegado. Por favor, inicie sesión.');
}

$db = Database::getInstance();
$active_shift = $db->query("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'", [$_SESSION['user_id']]);
$has_active_shift = $active_shift && isset($active_shift['id']);

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
            font-size: 1.1rem;
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
                <p>Gestiona productos, turnos y reportes desde un solo lugar.</p>
            </div>
            <div class="status-pill <?php echo $has_active_shift ? 'open' : 'closed'; ?>">
                <span class="status-dot"></span>
                <?php echo $has_active_shift ? 'Turno activo' : 'Turno cerrado'; ?>
            </div>
        </div>

        <div class="admin-main">
            <section>
                <h2 class="section-title">Accesos rápidos</h2>
                <div class="admin-menu">
                    <a href="products.php" class="menu-card">
                        <span class="card-icon">📦</span>
                        <span class="card-title">Gestionar productos</span>
                        <span class="card-subtitle">Precios, stock y estado de venta.</span>
                    </a>
                    <a href="sales_history.php" class="menu-card">
                        <span class="card-icon">🧾</span>
                        <span class="card-title">Historial de ventas</span>
                        <span class="card-subtitle">Consulta ventas, anulaciones y detalles.</span>
                    </a>
                    <a href="dashboard.php" class="menu-card">
                        <span class="card-icon">📊</span>
                        <span class="card-title">Panel turno en curso</span>
                        <span class="card-subtitle">Visualiza ventas del turno activo.</span>
                    </a>
                    <a href="reports.php" class="menu-card">
                        <span class="card-icon">📈</span>
                        <span class="card-title">Reportes de turno</span>
                        <span class="card-subtitle">Exporta y revisa resultados diarios.</span>
                    </a>
                    <a href="totals.php" class="menu-card">
                        <span class="card-icon">💰</span>
                        <span class="card-title">Ventas totales</span>
                        <span class="card-subtitle">Resumen acumulado del negocio.</span>
                    </a>
                    <a href="monitor.php" class="menu-card">
                        <span class="card-icon">🛰️</span>
                        <span class="card-title">Control operativo</span>
                        <span class="card-subtitle">Resumen clave y monitoreo de correos.</span>
                    </a>
                    <a href="#" id="open-shift-manager-btn" class="menu-card">
                        <span class="card-icon">⏱️</span>
                        <span class="card-title">Gestión de turno</span>
                        <span class="card-subtitle">Abrir panel práctico para iniciar o terminar turno.</span>
                    </a>
                    <a href="#" id="open-security-manager-btn" class="menu-card">
                        <span class="card-icon">🔐</span>
                        <span class="card-title">Seguridad</span>
                        <span class="card-subtitle">Reinicio operativo con clave de seguridad.</span>
                    </a>
                </div>
            </section>

            <section id="shift-manager-panel" class="shift-panel panel-hidden">
                <h2 class="section-title">Gestión de turno</h2>
                <p class="panel-note">Usa este panel para abrir o cerrar turno de forma rápida.</p>
                <div class="shift-grid">
                    <div class="field-group">
                        <label for="initial-cash">Efectivo inicial para abrir turno</label>
                        <input id="initial-cash" class="money-input" type="number" min="0" step="1" placeholder="Ej: 50000">
                    </div>
                    <div class="field-group">
                        <label for="final-cash">Efectivo final para cerrar turno</label>
                        <input id="final-cash" class="money-input" type="number" min="0" step="1" placeholder="Ej: 145000">
                    </div>
                </div>

                <div class="btn-row">
                    <button id="start-shift-btn" class="menu-button success" <?php if ($has_active_shift) echo 'disabled'; ?>>Iniciar turno</button>
                    <button id="end-shift-btn" class="menu-button danger" <?php if (!$has_active_shift) echo 'disabled'; ?>>Terminar turno</button>
                </div>
            </section>

            <section id="security-manager-panel" class="danger-panel panel-hidden">
                <h2 class="section-title">Reinicio operativo (día nuevo)</h2>
                <p>Esta acción cierra todos los turnos abiertos, devuelve stock de ventas completadas y elimina ventas, detalle y gastos para iniciar desde cero.</p>
                <div class="shift-grid">
                    <div class="field-group">
                        <label for="reset-initial-cash">Efectivo inicial del nuevo turno (opcional)</label>
                        <input id="reset-initial-cash" class="money-input" type="number" min="0" step="1" placeholder="Si lo ingresas, abre un turno nuevo automáticamente">
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

            <div class="footer-actions">
                <a href="index.php" class="menu-button secondary">Volver al POS</a>
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
        const shiftManagerPanel = document.getElementById('shift-manager-panel');
        const securityManagerPanel = document.getElementById('security-manager-panel');
        const toast = document.getElementById('toast');
        const actionModal = document.getElementById('action-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalMessage = document.getElementById('modal-message');
        const modalInputWrap = document.getElementById('modal-input-wrap');
        const modalInputLabel = document.getElementById('modal-input-label');
        const modalInput = document.getElementById('modal-input');
        const modalCancelBtn = document.getElementById('modal-cancel-btn');
        const modalConfirmBtn = document.getElementById('modal-confirm-btn');

        function showToast(message, isError = false) {
            toast.textContent = message;
            toast.classList.remove('error', 'success', 'show');
            toast.classList.add(isError ? 'error' : 'success');
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => toast.classList.remove('show'), 2800);
        }

        function parseAmount(value) {
            const amount = parseInt(String(value).replace(/\./g, '').trim(), 10);
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

        function showOnlyPanel(panelToShow) {
            [shiftManagerPanel, securityManagerPanel].forEach(panel => {
                if (!panel) return;
                panel.classList.add('panel-hidden');
                panel.classList.remove('panel-visible');
            });

            if (panelToShow) {
                panelToShow.classList.remove('panel-hidden');
                panelToShow.classList.add('panel-visible');
                panelToShow.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        openShiftManagerBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            showOnlyPanel(shiftManagerPanel);
        });

        openSecurityManagerBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            showOnlyPanel(securityManagerPanel);
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
            if (hasActiveShift) {
                showToast('Ya existe un turno activo.', true);
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
                const data = await postForm('start_shift_api.php', { initial_cash: amount });
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