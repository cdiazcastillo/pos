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
            --primary-color: #16a34a;
            --danger-color: #ef4444;
            --warning-color: #f97316;
            --muted: #6b7280;
            --dark-gray: #1f2937;
            --light-bg: #f9fafb;
            --border-color: #e5e7eb;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-gray);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--dark-gray);
        }

        .alert {
            padding: 14px;
            border-radius: 8px;
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

        .expenses-table-wrap {
            overflow-x: auto;
            margin-top: 20px;
        }

        .expenses-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .expenses-table thead {
            background-color: var(--light-bg);
            border-bottom: 2px solid var(--border-color);
        }

        .expenses-table th {
            padding: 12px;
            text-align: left;
            font-weight: 700;
            color: var(--dark-gray);
        }

        .expenses-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .expenses-table tbody tr:hover {
            background-color: var(--light-bg);
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

            .expenses-table {
                font-size: 0.8rem;
            }

            .expenses-table th,
            .expenses-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>💰 Otros Gastos</h1>
            <div class="header-buttons">
                <a href="admin.php" class="btn btn-secondary">📋 Menú</a>
                <a href="index.php" class="btn btn-secondary">🛒 POS</a>
            </div>
        </div>
    </header>

    <main class="main">
        <div class="page-title">Registrar Otros Gastos</div>

        <?php if (!$hasActiveShift): ?>
            <div class="alert alert-warning">
                ⚠️ No tienes un turno activo. Los gastos registrados se guardarán cuando abras un turno.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                ✓ Turno #<?php echo $activeShiftId; ?> activo • Usuario: <?php echo htmlspecialchars($currentUser['username']); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">Nuevo Gasto</h2>

            <div class="form-group">
                <label for="expense-description">Descripción del Gasto</label>
                <textarea id="expense-description" placeholder="Ej: Compra de bolsas, cambio, transporte, alquileres, etc." maxlength="255"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="expense-amount">Monto (en pesos)</label>
                    <input id="expense-amount" type="text" inputmode="numeric" pattern="[0-9]*" placeholder="Ej: 5000" value="">
                </div>
                <div class="form-group">
                    <label for="expense-method">Método de Pago</label>
                    <select id="expense-method">
                        <option value="cash">💵 Efectivo</option>
                        <option value="transfer">🏦 Transferencia</option>
                    </select>
                </div>
            </div>

            <div class="button-group">
                <button class="btn btn-primary" id="save-expense-btn" onclick="saveExpense()" <?php echo $hasActiveShift ? '' : 'disabled'; ?>>✅ Guardar Gasto</button>
            </div>
        </div>

        <?php if ($hasActiveShift && count($expenses) > 0): ?>
        <div class="card">
            <h2 class="card-title">Gastos Registrados (<?php echo count($expenses); ?>)</h2>

            <div class="expenses-table-wrap">
                <table class="expenses-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Descripción</th>
                            <th style="width: 15%;">Monto</th>
                            <th style="width: 15%;">Método</th>
                            <th style="width: 30%;">Hora</th>
                            <th style="width: 15%;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($expense['description'] ?? 'Sin descripción'); ?></td>
                            <td class="expense-amount">$<?php echo number_format(intval($expense['amount']), 0, '', '.'); ?></td>
                            <td>
                                <span class="method-badge method-<?php echo ($expense['payment_method'] === 'cash') ? 'cash' : 'transfer'; ?>">
                                    <?php echo ($expense['payment_method'] === 'cash') ? 'Efectivo' : 'Transferencia'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($expense['expense_time'])); ?></td>
                            <td>
                                <button class="expense-delete-btn" onclick="deleteExpense(<?php echo intval($expense['id']); ?>)">🗑️ Borrar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
            <div class="empty-state-icon">📋</div>
            <p>No hay gastos registrados en este turno.</p>
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
            if (confirm('¿Confirmas que quieres eliminar este gasto?')) {
                try {
                    const data = await postForm('register_expense_api.php', {
                        mode: 'delete',
                        expense_id: expenseId
                    });

                    if (data.success) {
                        showToast('Gasto eliminado.');
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        showToast(data.message || 'No se pudo eliminar el gasto.', true);
                    }
                } catch (error) {
                    showToast('Error al eliminar el gasto.', true);
                    console.error(error);
                }
            }
        }

        document.getElementById('expense-amount').addEventListener('input', function() {
            this.value = String(this.value).replace(/\D/g, '');
        });
    </script>
</body>
</html>
