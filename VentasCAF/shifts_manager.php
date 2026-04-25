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
    if ($isAdmin) {
        $active_shift = $db->query(
            "SELECT s.id, s.user_id, s.start_time, u.username
             FROM shifts s
             JOIN users u ON u.id = s.user_id
             WHERE s.status = 'open'
             ORDER BY s.start_time ASC
             LIMIT 1"
        );
    } else {
        $active_shift = $db->query(
            "SELECT s.id, s.user_id, s.start_time, u.username
             FROM shifts s
             JOIN users u ON u.id = s.user_id
             WHERE s.user_id = ? AND s.status = 'open'",
            [$_SESSION['user_id']]
        );
    }

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
    <title>Gestión de Turnos - 4 Básico A</title>
    <base href="<?php echo htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="styles.css">
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

        * { box-sizing: border-box; }

        body {
            font-family: var(--font-family);
            background-color: var(--light-gray);
            color: var(--dark-gray);
            margin: 0;
            min-height: 100vh;
            padding: 20px;
        }

        .page-wrap {
            max-width: 980px;
            margin: 0 auto;
        }

        .shift-panel {
            border: 1px solid #e6e9f5;
            border-radius: 14px;
            padding: 18px;
            background-color: #fcfcff;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
        }

        .section-title {
            margin: 0 0 12px;
            font-size: 1.1rem;
        }

        .panel-note {
            margin: 0 0 8px;
            color: var(--muted);
            font-size: 0.9rem;
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
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .menu-button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .menu-button.success { background-color: var(--success-color); }
        .menu-button.success:hover { background-color: #168553; }

        .menu-button.secondary { background-color: var(--secondary-color); }
        .menu-button.secondary:hover { background-color: #5a6268; }

        .menu-button.end-blue { background-color: #2563eb; }
        .menu-button.end-blue:hover { background-color: #1d4ed8; }

        .menu-button:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .active-shifts-panel {
            margin-top: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            padding: 12px;
        }

        .active-shifts-head h3 { margin: 0; font-size: 0.96rem; }
        .active-shifts-head p { margin: 6px 0 0; color: var(--muted); font-size: 0.86rem; }

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

        .active-shifts-table tbody tr:last-child td { border-bottom: none; }

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

        .expense-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 8px;
            margin-bottom: 10px;
        }

        .expense-form input,
        .expense-form select {
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

        #toast.error { background-color: var(--danger-color); }
        #toast.success { background-color: var(--success-color); }

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

        #action-modal.show { display: flex; }

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

        .modal-input-wrap { margin-top: 10px; display: none; }
        .modal-input-wrap.show { display: block; }

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

        .modal-btn.cancel { background: #e5e7eb; color: #374151; }
        .modal-btn.confirm { background: var(--primary-color); color: #fff; }
        .modal-btn.confirm.danger { background: var(--danger-color); }

        @media (max-width: 760px) {
            body { padding: 10px; }
            .expense-form { grid-template-columns: 1fr; }
            .menu-button { width: 100%; }
        }
    </style>
</head>
<body>
    <?php $activePage = 'admin'; include 'top-nav.php'; ?>

    <div class="page-wrap">
        <section id="shift-manager-panel" class="shift-panel">
            <h2 class="section-title">Gestión de turno</h2>
            <p class="panel-note">Usa este panel para abrir o cerrar turno de forma rápida.</p>
            <div class="shift-grid">
                <?php if (!$isAdmin): ?>
                <div class="field-group">
                    <label for="initial-cash">Efectivo inicial para abrir turno</label>
                    <input id="initial-cash" class="money-input" type="text" inputmode="numeric" pattern="[0-9]*" min="0" step="1" placeholder="Ej: 50000">
                </div>
                <?php endif; ?>
                <div class="field-group">
                    <label for="final-cash">Efectivo final para cerrar turno</label>
                    <input id="final-cash" class="money-input" type="text" inputmode="numeric" pattern="[0-9]*" min="0" step="1" placeholder="Ej: 145000">
                </div>
                <?php if ($isAdmin): ?>
                <div class="field-group">
                    <label for="admin-shift-target">Turno para trabajar</label>
                    <select id="admin-shift-target" class="money-input" style="height:42px;">
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
                <button id="start-shift-btn" class="menu-button success" <?php if (($isAdmin && empty($open_shifts_for_admin)) || (!$isAdmin && $has_active_shift)) echo 'disabled'; ?>><?php echo $isAdmin ? 'Usar turno seleccionado' : 'Iniciar turno'; ?></button>
                <button id="end-shift-btn" class="menu-button end-blue" <?php if (!$has_active_shift) echo 'disabled'; ?>>Terminar turno <?php echo $has_active_shift ? '(ID: '.intval($active_shift['id']).')' : ''; ?></button>
                <a href="admin.php" class="menu-button secondary">Regresar al menú admin</a>
            </div>

            <?php if ($isAdmin): ?>
            <div class="active-shifts-panel">
                <div class="active-shifts-head">
                    <h3>Turnos activos del equipo</h3>
                    <p>Usa estas acciones rápidas para trabajar o cerrar un turno específico.</p>
                </div>
                <?php if (empty($open_shifts_for_admin)): ?>
                    <p class="panel-note" style="margin-top:10px;">No hay turnos abiertos. Se debe abrir uno.</p>
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

            <div style="border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; padding: 12px; margin-top:14px;">
                <h3 style="margin:0 0 10px; font-size:0.95rem;">Otros gastos rápidos</h3>
                <p class="panel-note">Registra un gasto indicando si salió de efectivo o transferencia.</p>
                <div class="expense-form">
                    <input id="shift-expense-note-input" type="text" maxlength="255" placeholder="Ej: Servilletas">
                    <input id="shift-expense-amount-input" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Monto">
                    <select id="shift-expense-method-input">
                        <option value="cash">Efectivo</option>
                        <option value="transfer">Transferencia</option>
                    </select>
                    <button id="shift-save-expense-btn" type="button">Guardar gasto</button>
                </div>
            </div>
        </section>
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
        const adminShiftTarget = document.getElementById('admin-shift-target');
        const quickUseShiftButtons = document.querySelectorAll('.quick-use-shift-btn');
        const quickCloseShiftButtons = document.querySelectorAll('.quick-close-shift-btn');

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
            if (!/^\d+$/.test(normalized)) return NaN;
            const amount = Number(normalized);
            return Number.isInteger(amount) ? amount : NaN;
        }

        function bindNumericInput(inputElement) {
            if (!inputElement) return;
            inputElement.addEventListener('input', () => {
                inputElement.value = String(inputElement.value).replace(/\D/g, '');
            });
        }

        [initialCashInput, finalCashInput, shiftExpenseAmountInput].forEach(bindNumericInput);

        async function postForm(url, payload) {
            const formData = new FormData();
            Object.entries(payload).forEach(([key, value]) => formData.append(key, value));
            const response = await fetch(url, { method: 'POST', body: formData });
            return response.json();
        }

        function syncAdminShiftTarget(shiftId) {
            if (!adminShiftTarget) return;
            const targetValue = String(shiftId || '');
            const hasOption = Array.from(adminShiftTarget.options).some(option => option.value === targetValue);
            adminShiftTarget.value = hasOption ? targetValue : (adminShiftTarget.options[0]?.value || '');
        }

        async function selectShiftForWork(shiftId, showSuccessToast = true) {
            const shiftNumber = Number(shiftId);
            if (!Number.isInteger(shiftNumber) || shiftNumber <= 0) {
                showToast('Selecciona un turno válido.', true);
                return false;
            }

            try {
                const data = await postForm('start_shift_api.php', { mode: 'join', shift_id: shiftNumber });
                if (!data.success) {
                    showToast(data.message ? `Error: ${data.message}` : 'No se pudo seleccionar el turno.', true);
                    return false;
                }
                currentSelectedShiftId = shiftNumber;
                syncAdminShiftTarget(shiftNumber);
                if (showSuccessToast) showToast('Turno compartido seleccionado correctamente.');
                return true;
            } catch (error) {
                showToast('Error de conexión al seleccionar el turno.', true);
                return false;
            }
        }

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
                if (requireInput) {
                    modalInput.setAttribute('inputmode', 'numeric');
                    modalInput.setAttribute('pattern', '[0-9]*');
                    modalInput.oninput = () => {
                        modalInput.value = String(modalInput.value).replace(/\D/g, '');
                    };
                } else {
                    modalInput.removeAttribute('inputmode');
                    modalInput.removeAttribute('pattern');
                    modalInput.oninput = null;
                }
            } else {
                modalInputWrap.classList.remove('show');
                modalInput.value = '';
                modalInput.removeAttribute('inputmode');
                modalInput.removeAttribute('pattern');
                modalInput.oninput = null;
            }

            actionModal.classList.add('show');

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
                    if (event.target === actionModal) onCancel();
                };

                modalCancelBtn.addEventListener('click', onCancel);
                modalConfirmBtn.addEventListener('click', onConfirm);
                actionModal.addEventListener('click', onBackdropClick);
            });
        }

        startShiftBtn?.addEventListener('click', async () => {
            if (!isAdminUser && hasActiveShift) {
                showToast('Ya existe un turno activo.', true);
                return;
            }

            if (isAdminUser) {
                if (!adminShiftTarget || !adminShiftTarget.value) {
                    showToast('No hay turnos abiertos. Se debe abrir uno.', true);
                    return;
                }
                startShiftBtn.disabled = true;
                try {
                    const selected = await selectShiftForWork(adminShiftTarget.value, true);
                    if (selected) {
                        setTimeout(() => window.location.reload(), 600);
                    }
                } finally {
                    startShiftBtn.disabled = false;
                }
                return;
            }

            const amount = parseAmount(initialCashInput?.value || '');
            if (!Number.isFinite(amount) || amount < 0) {
                showToast('Ingresa un efectivo inicial válido.', true);
                initialCashInput?.focus();
                return;
            }

            const startConfirm = await openActionModal({
                title: 'Confirmar inicio de turno',
                message: `Se iniciará el turno con efectivo inicial <strong>$${amount.toLocaleString('es-CL')}</strong>.`,
                confirmText: 'Iniciar turno',
                confirmDanger: false
            });
            if (!startConfirm.confirmed) return;

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

        endShiftBtn?.addEventListener('click', async () => {
            if (!hasActiveShift) {
                showToast('No hay turno activo para cerrar.', true);
                return;
            }

            const amount = parseAmount(finalCashInput?.value || '');
            if (!Number.isFinite(amount) || amount < 0) {
                showToast('Ingresa un efectivo final válido.', true);
                finalCashInput?.focus();
                return;
            }

            const endConfirm = await openActionModal({
                title: 'Confirmar cierre de turno',
                message: `Se cerrará el turno con efectivo final <strong>$${amount.toLocaleString('es-CL')}</strong>.`,
                confirmText: 'Cerrar turno',
                confirmDanger: true
            });
            if (!endConfirm.confirmed) return;

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
                if (!confirmSwitch.confirmed) return;

                button.disabled = true;
                try {
                    const selected = await selectShiftForWork(shiftId, false);
                    if (!selected) return;
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
                if (!closePrompt.confirmed) return;

                const finalCashAmount = parseAmount(closePrompt.value);
                if (!Number.isFinite(finalCashAmount) || finalCashAmount < 0) {
                    showToast('Ingresa un efectivo final válido.', true);
                    return;
                }

                button.disabled = true;
                try {
                    if (shiftId !== currentSelectedShiftId) {
                        const selected = await selectShiftForWork(shiftId, false);
                        if (!selected) return;
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
            } catch (error) {
                showToast('Error de conexión al guardar el gasto.', true);
            } finally {
                shiftSaveExpenseBtn.disabled = false;
            }
        });
    </script>
</body>
</html>
