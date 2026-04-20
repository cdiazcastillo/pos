<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    die('Acceso denegado. Por favor, inicie sesión.');
}

$db = Database::getInstance();

// --- Filtering Logic ---
$filter = $_GET['filter'] ?? 'all';
$sql_where = [];
$params = [];

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
    <style>
        :root {
            --primary-color: #007bff; --secondary-color: #6c757d; --danger-color: #dc3545;
            --success-color: #28a745; --info-color: #17a2b8; --light-gray: #f8f9fa; --dark-gray: #343a40;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body { font-family: var(--font-family); background-color: var(--light-gray); color: var(--dark-gray); margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background-color: transparent; box-shadow: none; padding: 0; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 20px; }
        h1 { margin: 0; color: var(--dark-gray); }
        .btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; font-size: 1rem; font-weight: 600; color: white !important; }
        .btn-secondary { background-color: var(--secondary-color) !important; }
        .btn-danger { background-color: var(--danger-color) !important; }
        .btn-warning { background-color: #ffc107 !important; }
        .btn-info { background-color: var(--info-color) !important; }

        .filter-buttons {
            display: flex;
            gap: 10px;
            background-color: #e9ecef;
            padding: 5px;
            border-radius: 8px;
            flex-wrap: wrap;
        }
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-btn {
            padding: 8px 15px;
            text-decoration: none;
            color: var(--secondary-color);
            font-weight: 600;
            border-radius: 5px;
            transition: all 0.2s;
        }
        .filter-btn:hover {
            background-color: #d1d5db;
        }
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Card Grid Layout */
        #sales-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, 320px);
            gap: 20px;
            justify-content: center;
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
        
        .sale-details { font-size: 0.9rem; color: var(--secondary-color); }
        .sale-total { margin-top: 10px; }
        .sale-total p { margin: 2px 0; font-weight: bold; }
        .sale-total .original { font-size: 1.1rem; color: var(--secondary-color); }
        .sale-total .returns { font-size: 1.1rem; color: var(--danger-color); }
        .sale-total .net { font-size: 1.5rem; color: var(--dark-gray); border-top: 1px solid #eee; padding-top: 5px; margin-top: 5px; }
        .card-items-list { background-color: var(--light-gray); padding: 15px 20px; margin: 0; border-top: 1px solid #eee; flex-grow: 1; }
        .card-items-list ul { margin: 0; padding-left: 20px; }
        .card-items-list li { font-size: 0.9rem; }
        .card-footer { padding: 15px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }
        .btn.disabled-link { pointer-events: none; opacity: 0.6; }
        #toast-notification { position: fixed; bottom: 20px; right: 20px; background-color: var(--success-color); color: white; padding: 12px 25px; border-radius: 5px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); z-index: 2000; visibility: hidden; opacity: 0; transition: all 0.3s; transform: translateX(110%); }
        #toast-notification.show { visibility: visible; opacity: 1; transform: translateX(0); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="img/logo.png" alt="Logo" style="max-width: 100px;">
            <h1>Historial de Ventas</h1>
            <div class="filter-buttons">
                <a href="sales_history.php?filter=all" class="filter-btn <?php if ($filter === 'all') echo 'active'; ?>">Todos</a>
                <a href="sales_history.php?filter=cash" class="filter-btn <?php if ($filter === 'cash') echo 'active'; ?>">Efectivo</a>
                <a href="sales_history.php?filter=transfer" class="filter-btn <?php if ($filter === 'transfer') echo 'active'; ?>">Transferencia</a>
                <a href="sales_history.php?filter=voided" class="filter-btn <?php if ($filter === 'voided') echo 'active'; ?>">Anuladas</a>
            </div>
            <div class="header-actions">
                <a href="reports.php" class="btn btn-info">Reportes</a>
                <a href="index.php" class="btn btn-info">Regresar al POS</a>
                <a href="admin.php" class="btn btn-secondary">Regresar a Ventas</a>
            </div>
        </div>
        <div id="sales-card-grid">
            <?php foreach($sales as $sale): ?>
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
                        <a href="return.php?sale_id=<?php echo $sale['id']; ?>" class="btn btn-warning">Procesar Devolución</a>
                        <button class="btn btn-danger void-sale-btn" data-id="<?php echo $sale['id']; ?>">Anular Venta Completa</button>
                    <?php endif; ?>
                </div>
            </div>
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