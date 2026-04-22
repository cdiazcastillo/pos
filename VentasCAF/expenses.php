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

// Obtener gastos del turno actual
$expenses = [];
if ($hasActiveShift) {
    $expenses = $db->query(
        "SELECT id, description, amount, payment_method, expense_time
         FROM expenses
         WHERE shift_id = ? AND sale_id IS NULL
         ORDER BY expense_time DESC",
        [$activeShiftId],
        true
    ) ?: [];
}

$totalExpenses = array_sum(array_map(function ($e) { return intval($e['amount']); }, $expenses));
$totalCashExpenses = array_sum(array_map(function ($e) { 
    return ($e['payment_method'] === 'cash') ? intval($e['amount']) : 0; 
}, $expenses));

$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otros Gastos - VentasCAF POS</title>
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
            text-decoration: none;
            min-height: 2.75rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: #fff;
        }

        .btn-primary:hover:not(:disabled) {
            background: #15803d;
        }

        .btn-primary:disabled {
            background: #d1d5db;
            cursor: not-allowed;
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

        .btn-danger {
            background: var(--danger-color);
            color: #fff;
        }

        .btn-danger:hover:not(:disabled) {
            background: #dc2626;
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

        .alert {
            padding: 14px;
            border-radius: 1.25rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-info {
            background-color: #dbeafe;
            border: 1px solid #93c5fd;
            color: #075985;
        }

        .alert-warning {
            background-color: #fed7aa;
            border: 1px solid #fdba74;
            color: #7c2d12;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .expenses-grid {
            margin-top: 16px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
        }

        .expense-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            display: grid;
            gap: 10px;
        }

        .expense-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .expense-title {
            font-weight: 700;
            color: var(--dark-gray);
            font-size: 1rem;
            word-break: break-word;
        }

        .expense-meta {
            color: var(--muted);
            font-size: 0.86rem;
        }

        .expense-card-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .method-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .method-cash {
            background-color: #dcfce7;
            color: #166534;
        }

        .method-transfer {
            background-color: #dbeafe;
            color: #075985;
        }

        .expense-amount {
            font-weight: 700;
            color: var(--dark-gray);
        }

        .expense-delete-btn {
            padding: 6px 10px;
            font-size: 0.8rem;
            background: var(--danger-color);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .expense-delete-btn:hover {
            background: #dc2626;
        }

        .expense-edit-btn {
            padding: 6px 10px;
            font-size: 0.8rem;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .expense-edit-btn:hover {
            background: #1d4ed8;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 1100;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-card {
            width: min(500px, 96vw);
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 18px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.28);
        }

        .modal-card h3 {
            margin-bottom: 12px;
        }

        .summary-box {
            background-color: var(--light-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 14px;
            margin-top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row strong {
            font-weight: 700;
            color: var(--dark-gray);
        }

        .summary-row.total {
            background-color: #fff;
            padding: 10px;
            border-radius: 6px;
            font-size: 1.1rem;
            margin-top: 10px;
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

        @media (max-width: 768px) {
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

            .expense-card-actions button {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>Otros Gastos</h1>
            <div class="header-buttons">
                <a href="admin.php" class="btn btn-menu">Regresar al Menú</a>
                <a href="index.php" class="btn btn-pos">Regresar al POS</a>
                <a href="logout.php" class="btn btn-logout">Cerrar sesión</a>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="page-title">Otros gastos rápidos</div>

        <?php if (!$hasActiveShift): ?>
            <div class="alert alert-warning">
                Aviso: no tienes un turno activo. Los gastos registrados se guardarán cuando abras un turno.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Turno #<?php echo $activeShiftId; ?> activo • Usuario: <?php echo htmlspecialchars($currentUser['username']); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">Registra un gasto indicando si salió de efectivo o transferencia</h2>

            <div class="form-group">
                <label for="expense-description">Detalle</label>
                <textarea id="expense-description" placeholder="Ej: Servilletas" maxlength="255"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="expense-amount">Monto (en pesos)</label>
                    <input id="expense-amount" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Ej: 5000" value="">
                </div>
                <div class="form-group">
                    <label for="expense-method">Método de Pago</label>
                    <select id="expense-method">
                        <option value="cash">Efectivo</option>
                        <option value="transfer">Transferencia</option>
                    </select>
                </div>
            </div>

            <div class="button-group">
                <button class="btn btn-primary" id="save-expense-btn" onclick="saveExpense()" <?php echo $hasActiveShift ? '' : 'disabled'; ?>>Guardar Gasto</button>
            </div>
        </div>

        <?php if ($hasActiveShift && count($expenses) > 0): ?>
        <div class="card">
            <h2 class="card-title">Gastos Registrados (<?php echo count($expenses); ?>)</h2>

            <div class="expenses-grid">
                <?php foreach ($expenses as $expense): ?>
                <article class="expense-card">
                    <div class="expense-card-top">
                        <div class="expense-title"><?php echo htmlspecialchars($expense['description'] ?? 'Sin descripción'); ?></div>
                        <div class="expense-amount">-$<?php echo number_format(intval($expense['amount']), 0, '', '.'); ?></div>
                    </div>
                    <div class="expense-meta">
                        <?php echo date('d/m/Y H:i', strtotime($expense['expense_time'])); ?>
                    </div>
                    <div>
                        <span class="method-badge method-<?php echo ($expense['payment_method'] === 'cash') ? 'cash' : 'transfer'; ?>">
                            <?php echo ($expense['payment_method'] === 'cash') ? 'Efectivo' : 'Transferencia'; ?>
                        </span>
                    </div>
                    <div class="expense-card-actions">
                        <button
                            class="expense-edit-btn"
                            type="button"
                            data-id="<?php echo intval($expense['id']); ?>"
                            data-description="<?php echo htmlspecialchars($expense['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-amount="<?php echo intval($expense['amount']); ?>"
                            data-method="<?php echo htmlspecialchars($expense['payment_method'] ?? 'cash', ENT_QUOTES, 'UTF-8'); ?>"
                            onclick="openEditExpenseModal(this)"
>Editar</button>
                        <button class="expense-delete-btn" type="button" onclick="deleteExpense(<?php echo intval($expense['id']); ?>)">Quitar</button>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <div class="summary-box">
                <div class="summary-row">
                    <span>Total Gastos en Efectivo:</span>
                    <strong>$<?php echo number_format($totalCashExpenses, 0, '', '.'); ?></strong>
                </div>
                <div class="summary-row">
                    <span>Total Gastos en Transferencia:</span>
                    <strong>$<?php echo number_format($totalExpenses - $totalCashExpenses, 0, '', '.'); ?></strong>
                </div>
                <div class="summary-row total">
                    <span>Total Gastos Registrados:</span>
                    <strong style="color: var(--danger-color); font-size: 1.2rem;">$<?php echo number_format($totalExpenses, 0, '', '.'); ?></strong>
                </div>
            </div>
        </div>
        <?php elseif ($hasActiveShift): ?>
        <div class="empty-state">
            <div class="empty-state-icon">—</div>
            <p>No hay gastos registrados en este turno.</p>
        </div>
        <?php endif; ?>
    </main>

    <div id="toast" class="toast"></div>

    <div id="edit-expense-modal" class="modal-overlay" aria-hidden="true">
        <div class="modal-card">
            <h3>Editar gasto</h3>
            <input type="hidden" id="edit-expense-id" value="">
            <div class="form-group">
                <label for="edit-expense-description">Detalle</label>
                <textarea id="edit-expense-description" maxlength="255" placeholder="Ej: Servilletas"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit-expense-amount">Monto</label>
                    <input id="edit-expense-amount" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Ej: 5000">
                </div>
                <div class="form-group">
                    <label for="edit-expense-method">Método de Pago</label>
                    <select id="edit-expense-method">
                        <option value="cash">Efectivo</option>
                        <option value="transfer">Transferencia</option>
                    </select>
                </div>
            </div>
            <div class="button-group">
                <button type="button" class="btn btn-secondary" onclick="closeEditExpenseModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" id="save-edit-expense-btn" onclick="updateExpense()">Guardar cambios</button>
            </div>
        </div>
    </div>

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

        function openEditExpenseModal(button) {
            const modal = document.getElementById('edit-expense-modal');
            document.getElementById('edit-expense-id').value = button.dataset.id || '';
            document.getElementById('edit-expense-description').value = button.dataset.description || '';
            document.getElementById('edit-expense-amount').value = button.dataset.amount || '';
            document.getElementById('edit-expense-method').value = (button.dataset.method === 'transfer') ? 'transfer' : 'cash';
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeEditExpenseModal() {
            const modal = document.getElementById('edit-expense-modal');
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
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

        async function saveExpense() {
            const description = document.getElementById('expense-description').value.trim();
            const amount = parseAmount(document.getElementById('expense-amount').value);
            const method = document.getElementById('expense-method').value;

            if (!description) {
                showToast('Ingresa una descripción.', true);
                return;
            }

            if (isNaN(amount) || amount <= 0) {
                showToast('Ingresa un monto válido mayor a $0.', true);
                return;
            }

            const btnSave = document.getElementById('save-expense-btn');
            btnSave.disabled = true;

            try {
                const data = await postForm('register_expense_api.php', {
                    description: description,
                    amount: amount,
                    payment_method: method
                });

                if (data.success) {
                    showToast('Gasto registrado correctamente.');
                    document.getElementById('expense-description').value = '';
                    document.getElementById('expense-amount').value = '';
                    document.getElementById('expense-method').value = 'cash';
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    showToast(data.message || 'No se pudo registrar el gasto.', true);
                }
            } catch (error) {
                showToast('Error al registrar el gasto.', true);
                console.error(error);
            } finally {
                btnSave.disabled = false;
            }
        }

        async function deleteExpense(expenseId) {
            try {
                const data = await postForm('register_expense_api.php', {
                    mode: 'delete',
                    expense_id: expenseId
                });

                if (data.success) {
                    showToast('Gasto eliminado.');
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(data.message || 'No se pudo eliminar el gasto.', true);
                }
            } catch (error) {
                showToast('Error al eliminar el gasto.', true);
                console.error(error);
            }
        }

        async function updateExpense() {
            const expenseId = Number(document.getElementById('edit-expense-id').value || 0);
            const description = document.getElementById('edit-expense-description').value.trim();
            const amount = parseAmount(document.getElementById('edit-expense-amount').value);
            const method = document.getElementById('edit-expense-method').value;

            if (!Number.isInteger(expenseId) || expenseId <= 0) {
                showToast('Gasto inválido.', true);
                return;
            }

            if (!description) {
                showToast('Ingresa un detalle.', true);
                return;
            }

            if (isNaN(amount) || amount <= 0) {
                showToast('Ingresa un monto válido mayor a $0.', true);
                return;
            }

            const saveBtn = document.getElementById('save-edit-expense-btn');
            saveBtn.disabled = true;

            try {
                const data = await postForm('register_expense_api.php', {
                    mode: 'update',
                    expense_id: expenseId,
                    description: description,
                    amount: amount,
                    payment_method: method
                });

                if (data.success) {
                    showToast('Gasto actualizado.');
                    closeEditExpenseModal();
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showToast(data.message || 'No se pudo actualizar el gasto.', true);
                }
            } catch (error) {
                showToast('Error al actualizar el gasto.', true);
                console.error(error);
            } finally {
                saveBtn.disabled = false;
            }
        }

        document.getElementById('expense-amount').addEventListener('input', function() {
            this.value = String(this.value).replace(/\D/g, '');
        });

        const editExpenseAmount = document.getElementById('edit-expense-amount');
        if (editExpenseAmount) {
            editExpenseAmount.addEventListener('input', function() {
                this.value = String(this.value).replace(/\D/g, '');
            });
        }

        const editModal = document.getElementById('edit-expense-modal');
        if (editModal) {
            editModal.addEventListener('click', (event) => {
                if (event.target === editModal) {
                    closeEditExpenseModal();
                }
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && editModal && editModal.classList.contains('show')) {
                closeEditExpenseModal();
            }
        });
    </script>
</body>
</html>
