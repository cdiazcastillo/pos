<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
$currentUser = auth_require_role(['cashier', 'admin'], 'admin_login.php', 'index.php');
$isAdmin = (($currentUser['role'] ?? '') === 'admin');

$db = Database::getInstance();

// --- Filtering Logic ---
$filter = $_GET['filter'] ?? 'all';
$sql_where = [];
$params = [];
$salesContextNote = '';

if (!$isAdmin) {
    $activeShift = $db->query(
        "SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1",
        [$_SESSION['user_id']]
    );

    if ($activeShift && isset($activeShift['id'])) {
        $sql_where[] = "shift_id = ?";
        $params[] = intval($activeShift['id']);
        $salesContextNote = 'Mostrando solo ventas de tu turno activo.';
    } else {
        $sql_where[] = "1 = 0";
        $salesContextNote = 'No tienes un turno activo. Inicia turno para ver ventas en este panel.';
    }
}

if (in_array($filter, ['cash', 'transfer'])) {
    $sql_where[] = "payment_method = ?";
    $params[] = $filter;
} elseif ($filter === 'voided') {
    $sql_where[] = "status = 'voided'";
}

$sql = "SELECT * FROM sales";
if (!empty($sql_where)) {
    $sql .= " WHERE " . implode(' AND ', $sql_where);
}
$sql .= " ORDER BY sale_time DESC";
$sales = $db->query($sql, $params, true);

$expensesWhere = ["sale_id IS NULL"];
$expensesParams = [];

if (!$isAdmin) {
    if ($activeShift && isset($activeShift['id'])) {
        $expensesWhere[] = "shift_id = ?";
        $expensesParams[] = intval($activeShift['id']);
    } else {
        $expensesWhere[] = "1 = 0";
    }
}

if (in_array($filter, ['cash', 'transfer'], true)) {
    $expensesWhere[] = "payment_method = ?";
    $expensesParams[] = $filter;
}

$other_expenses = [];
if ($filter !== 'voided') {
    $expensesSql = "SELECT id, shift_id, description, amount, payment_method, expense_time FROM expenses";
    if (!empty($expensesWhere)) {
        $expensesSql .= " WHERE " . implode(' AND ', $expensesWhere);
    }
    $expensesSql .= " ORDER BY expense_time DESC";
    $other_expenses = $db->query($expensesSql, $expensesParams, true) ?: [];
}

$timeline = [];
foreach (($sales ?: []) as $saleRow) {
    $timeline[] = [
        'type' => 'sale',
        'time' => (string)($saleRow['sale_time'] ?? ''),
        'sale' => $saleRow
    ];
}

foreach ($other_expenses as $expenseRow) {
    $timeline[] = [
        'type' => 'expense',
        'time' => (string)($expenseRow['expense_time'] ?? ''),
        'expense' => $expenseRow
    ];
}

usort($timeline, static function ($a, $b) {
    $timeA = strtotime((string)($a['time'] ?? '')) ?: 0;
    $timeB = strtotime((string)($b['time'] ?? '')) ?: 0;
    return $timeB <=> $timeA;
});

// --- Data Fetching for Details ---
$sale_items_grouped = [];
$returns_grouped = [];
if ($sales) {
    $sale_ids = array_map(fn($s) => $s['id'], $sales);
    if (!empty($sale_ids)) { // Only query if there are sales
        $placeholders = implode(',', array_fill(0, count($sale_ids), '?'));

        // Fetch all sale items for the displayed sales
        $sale_items_raw = $db->query(
            "SELECT si.*, p.name AS product_name 
             FROM sale_items si 
             JOIN products p ON si.product_id = p.id 
             WHERE si.sale_id IN ($placeholders)
             ORDER BY si.sale_id ASC",
            $sale_ids,
            true
        );
        if($sale_items_raw){
            foreach ($sale_items_raw as $item) {
                $sale_items_grouped[$item['sale_id']][] = $item;
            }
        }

        // Fetch all return expenses for the displayed sales
        $return_expenses_raw = $db->query(
            "SELECT sale_id, SUM(amount) as total_returned FROM expenses WHERE sale_id IN ($placeholders) GROUP BY sale_id",
            $sale_ids,
            true
        );
        if($return_expenses_raw){
            foreach ($return_expenses_raw as $expense) {
                $returns_grouped[$expense['sale_id']] = $expense['total_returned'];
            }
        }
    }
}

// --- Helper Function ---
function format_payment_method($method) {
    if ($method === 'cash') return 'PAGO: EFECTIVO';
    if ($method === 'transfer') return 'PAGO: TRANSFERENCIA';
    return 'PAGO: N/A';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas - 4 Básico A</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary-color: #7c3aed; --secondary-color: #6b7280; --danger-color: #fb7185;
            --success-color: #14b8a6; --info-color: #3b82f6; --light-gray: #f3f0ff; --dark-gray: #1e1b4b;
            --font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            --card-shadow: 0 0.5rem 1.5rem rgba(30, 27, 75, 0.1);
        }
        body { font-family: var(--font-family); background-color: var(--light-gray); color: var(--dark-gray); margin: 0; padding: 0.875rem; overflow-x: hidden; }
        .container { max-width: 1200px; margin: auto; background-color: transparent; box-shadow: none; padding: 0; }
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--light-gray);
            padding: 8px 0 10px;
        }
        .header {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .title-wrap {
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        h1 { margin: 0; color: var(--dark-gray); font-size: 1.15rem; line-height: 1.1; }
        .logo-column {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }
        .logo-column img {
            max-width: 72px;
            border-radius: 1.25rem;
            background: #fff;
            border: 1px solid #dbe4ff;
            padding: 4px;
        }
        .btn { padding: 0.65rem 0.95rem; border: none; border-radius: 999rem; cursor: pointer; text-decoration: none; font-size: 0.95rem; font-weight: 700; color: white !important; min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; }
        .btn-secondary { background-color: var(--secondary-color) !important; }
        .btn-danger { background-color: var(--danger-color) !important; }
        .btn-warning { background-color: #ffc107 !important; }
        .btn-info { background-color: var(--danger-color) !important; }
        .filters-wrap {
            display: flex;
            justify-content: center;
            margin-top: 0.35rem;
        }

        .filter-buttons {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.2rem;
            background: rgba(124, 58, 237, 0.12);
            padding: 0.2rem;
            border-radius: 999rem;
            width: min(100%, 760px);
        }
        .filter-btn {
            padding: 0.45rem 0.7rem;
            text-decoration: none;
            color: var(--secondary-color);
            font-weight: 700;
            border-radius: 999rem;
            transition: all 0.2s;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 2.3rem;
            line-height: 1.1;
            font-size: 0.86rem;
        }
        .filter-btn:hover {
            background-color: rgba(124, 58, 237, 0.16);
        }
        .filter-btn.active {
            background-color: #fff;
            color: var(--dark-gray);
            box-shadow: 0 0.1rem 0.4rem rgba(30, 27, 75, 0.12);
        }
        .context-note {
            margin: 6px 0 0;
            font-size: 0.84rem;
            color: #475569;
            font-weight: 600;
        }

        /* Card Grid Layout */
        #sales-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, 320px);
            gap: 20px;
            justify-content: center;
            margin-top: 8px;
        }
        .sale-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            border-left: 5px solid var(--success-color);
        }
        .sale-card.voided {
            border-left-color: var(--danger-color);
            background-color: #f8f9fa;
            opacity: 0.7;
        }
        .sale-card.expense {
            border-left-color: #f97316;
            background: #fff7ed;
        }
        .card-main-info { padding: 20px; }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .card-header h2 {
            margin: 0;
            font-size: 1.2rem;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
        }
        .status-badge.completed { background-color: var(--success-color); }
        .status-badge.voided { background-color: var(--danger-color); }
        .status-badge.expense { background-color: #f97316; }
        
        .sale-details { font-size: 0.9rem; color: var(--secondary-color); }
        .sale-total { margin-top: 10px; }
        .sale-total p { margin: 2px 0; font-weight: bold; }
        .sale-total .original { font-size: 1.1rem; color: var(--secondary-color); }
        .sale-total .returns { font-size: 1.1rem; color: var(--danger-color); }
        .sale-total .net { font-size: 1.5rem; color: var(--dark-gray); border-top: 1px solid #eee; padding-top: 5px; margin-top: 5px; }
        .card-items-list { background-color: var(--light-gray); padding: 15px 20px; margin: 0; border-top: 1px solid #eee; flex-grow: 1; }
        .card-items-list ul { margin: 0; padding-left: 20px; }
        .card-items-list li { font-size: 0.9rem; }
        .card-footer { padding: 15px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
        .card-footer .btn {
            border-radius: 0.85rem;
            font-size: 0.82rem;
            min-height: 2.75rem;
            padding: 0.55rem 0.9rem;
        }
        .card-footer .btn-warning {
            background: linear-gradient(135deg, #7c3aed, #8b5cf6) !important;
            color: #fff !important;
        }
        .card-footer .btn-danger {
            background: rgba(251, 113, 133, 0.12) !important;
            color: #9f1239 !important;
            border: 1px solid rgba(251, 113, 133, 0.2);
        }
        .btn.disabled-link { pointer-events: none; opacity: 0.6; }
        #toast-notification { position: fixed; bottom: 20px; right: 20px; background-color: var(--success-color); color: white; padding: 12px 25px; border-radius: 5px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); z-index: 2000; visibility: hidden; opacity: 0; transition: all 0.3s; transform: translateX(110%); }
        #toast-notification.show { visibility: visible; opacity: 1; transform: translateX(0); }

        @media (max-width: 760px) {
            .filter-buttons {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                border-radius: 1rem;
            }

            .card-footer .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <?php $activePage = 'history'; include 'top-nav.php'; ?>
    <div class="container">
        <div class="sticky-top">
            <div class="filters-wrap">
                <div class="filter-buttons">
                    <a href="sales_history.php?filter=all" class="filter-btn <?php if ($filter === 'all') echo 'active'; ?>">Todos</a>
                    <a href="sales_history.php?filter=cash" class="filter-btn <?php if ($filter === 'cash') echo 'active'; ?>">Efectivo</a>
                    <a href="sales_history.php?filter=transfer" class="filter-btn <?php if ($filter === 'transfer') echo 'active'; ?>">Transferencia</a>
                    <a href="sales_history.php?filter=voided" class="filter-btn <?php if ($filter === 'voided') echo 'active'; ?>">Anuladas</a>
                </div>
            </div>
            <?php if ($salesContextNote !== ''): ?>
                <p class="context-note"><?php echo htmlspecialchars($salesContextNote); ?></p>
            <?php endif; ?>
        </div>

        <div id="sales-card-grid">
            <?php if (empty($timeline)): ?>
                <div class="sale-card" style="grid-column: 1 / -1; border-left-color:#9ca3af;">
                    <div class="card-main-info">
                        <h2>Sin movimientos</h2>
                        <p class="sale-details">No hay ventas ni gastos para el filtro seleccionado.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach($timeline as $entry): ?>
                <?php if (($entry['type'] ?? '') === 'sale'): ?>
                    <?php $sale = $entry['sale']; ?>
                    <div class="sale-card <?php echo $sale['status']; ?>" id="sale-<?php echo $sale['id']; ?>">
                        <div class="card-main-info">
                            <div class="card-header">
                                <h2>Venta #<?php echo $sale['id']; ?></h2>
                                <span class="status-badge <?php echo $sale['status']; ?>">
                                    <?php echo $sale['status'] === 'completed' ? 'Completada' : 'Anulada'; ?>
                                </span>
                            </div>
                            <p class="sale-details">
                                <?php echo date('d/m/Y h:i A', strtotime($sale['sale_time'])); ?>
                                <br>
                                <strong><?php echo format_payment_method($sale['payment_method'] ?? null); ?></strong>
                            </p>
                            <div class="sale-total">
                                <?php 
                                    $total_returned = $returns_grouped[$sale['id']] ?? 0;
                                    if ($total_returned > 0) {
                                        echo '<p class="original">Total Original: $' . number_format($sale['total_amount'], 0, ',', '.') . '</p>';
                                        echo '<p class="returns">Devoluciones: -$' . number_format($total_returned, 0, ',', '.') . '</p>';
                                        echo '<p class="net">Neto: $' . number_format($sale['total_amount'] - $total_returned, 0, ',', '.') . '</p>';
                                    } else {
                                        echo '<p class="net">Total: $' . number_format($sale['total_amount'], 0, ',', '.') . '</p>';
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="card-items-list">
                            <strong>Items:</strong>
                            <ul>
                                <?php if (isset($sale_items_grouped[$sale['id']])): ?>
                                    <?php foreach($sale_items_grouped[$sale['id']] as $item): ?>
                                        <li><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['product_name']); ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>No se encontraron items.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="card-footer">
                            <?php if ($sale['status'] === 'completed'): ?>
                                <a href="return.php?sale_id=<?php echo $sale['id']; ?>" class="btn btn-warning">Devolución</a>
                                <button class="btn btn-danger void-sale-btn" data-id="<?php echo $sale['id']; ?>">Anular Vta</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php $expense = $entry['expense']; ?>
                    <div class="sale-card expense" id="expense-<?php echo intval($expense['id']); ?>">
                        <div class="card-main-info">
                            <div class="card-header">
                                <h2>OTROS GASTOS</h2>
                                <span class="status-badge expense">Gasto</span>
                            </div>
                            <p class="sale-details">
                                <?php echo date('d/m/Y h:i A', strtotime((string)$expense['expense_time'])); ?>
                                <br>
                                <strong><?php echo format_payment_method($expense['payment_method'] ?? null); ?></strong>
                            </p>
                            <div class="sale-total">
                                <p class="returns">Detalle: <?php echo htmlspecialchars((string)($expense['description'] ?? 'Sin detalle')); ?></p>
                                <p class="net">Monto: -$<?php echo number_format(intval($expense['amount'] ?? 0), 0, ',', '.'); ?></p>
                            </div>
                        </div>
                        <div class="card-items-list">
                            <strong>Registro:</strong>
                            <ul>
                                <li>Movimiento operacional registrado como OTROS GASTOS.</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div id="toast-notification"></div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const salesGrid = document.getElementById('sales-card-grid');
            salesGrid.addEventListener('click', (e) => {
                const voidBtn = e.target.closest('.void-sale-btn');
                if (voidBtn) {
                    e.preventDefault();
                    const saleId = voidBtn.dataset.id;
                    voidSale(voidBtn, saleId); // Call directly without confirm
                }
            });
            function showToast(message, isError = false) {
                const toast = document.getElementById('toast-notification');
                if (!toast) return;
                toast.textContent = message;
                toast.style.backgroundColor = isError ? 'var(--danger-color)' : 'var(--success-color)';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 3000);
            }
            async function voidSale(button, saleId) {
                const formData = new FormData();
                formData.append('sale_id', saleId);
                try {
                    const response = await fetch('void_sale_api.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) {
                        showToast('Venta anulada con éxito.');
                        // Update UI dynamically
                        const card = document.getElementById(`sale-${saleId}`);
                        const statusBadge = card.querySelector('.status-badge');
                        const partialReturnLink = card.querySelector('.btn-warning');
                        
                        card.classList.add('voided');
                        statusBadge.classList.remove('completed');
                        statusBadge.classList.add('voided');
                        statusBadge.textContent = 'Anulada';
                        button.disabled = true; // Disable the void button
                        if (partialReturnLink) {
                            partialReturnLink.classList.add('disabled-link'); // Visually disable the link
                            partialReturnLink.disabled = true; // For robustness, though pointer-events takes care
                        }
                        // Optionally, update the total display if needed, but a reload is simpler for total calculations.
                        // For now, will keep the reload as total calculations can be complex due to returns.
                        setTimeout(() => window.location.reload(), 1500); // Keep reload for total recalculation
                    } else {
                        showToast('Error: ' + result.message, true);
                    }
                } catch (error) {
                    console.error('Void sale error:', error);
                    showToast('Error de conexión al anular la venta.', true);
                }
            }
        });
    </script>
</body>
</html>