<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['cashier', 'admin'], 'admin_login.php', 'index.php');

$db = Database::getInstance();

$shift_id = null;
$initial_cash = 0;

$gross_cash_sales_completed = 0;
$gross_transfer_sales_completed = 0;

$net_returns_on_completed_sales_cash = 0;
$net_returns_on_completed_sales_transfer = 0;

$other_expenses_amount = 0;
$other_expenses_cash_amount = 0;

$net_cash_sales = 0;
$net_transfer_sales = 0;
$total_sales_current_net = 0; // Venta Actual
$total_returns_amount = 0; // Anulacion o Cancelada
$expected_cash_in_drawer = 0;

try {
    $current_shift = $db->query("SELECT id, initial_cash FROM shifts WHERE user_id = ? AND status = 'open'", [$_SESSION['user_id']]);

    if ($current_shift) {
        $shift_id = $current_shift['id'];
        $initial_cash = $current_shift['initial_cash'];

        // 1. Gross Sales Data (only completed sales)
        $sales_summary_query = "SELECT SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) AS cash_sales,
                                SUM(CASE WHEN payment_method = 'transfer' THEN total_amount ELSE 0 END) AS transfer_sales
                         FROM sales
                         WHERE shift_id = ? AND status = 'completed'";
        $sales_summary = $db->query($sales_summary_query, [$shift_id]);
        
        if ($sales_summary) {
            $gross_cash_sales_completed = $sales_summary['cash_sales'] ?? 0;
            $gross_transfer_sales_completed = $sales_summary['transfer_sales'] ?? 0;
        }

        // 2. Returns Data (from expenses linked to sales that are STILL completed)
        $returns_summary_query = "SELECT SUM(CASE WHEN s.payment_method = 'cash' THEN e.amount ELSE 0 END) AS cash_returns,
                                 SUM(CASE WHEN s.payment_method = 'transfer' THEN e.amount ELSE 0 END) AS transfer_returns
                          FROM expenses e
                          JOIN sales s ON e.sale_id = s.id
                          WHERE e.shift_id = ? AND e.sale_id IS NOT NULL AND s.status = 'completed'";
        $returns_summary = $db->query($returns_summary_query, [$shift_id]);
        
        if ($returns_summary) {
            $net_returns_on_completed_sales_cash = $returns_summary['cash_returns'] ?? 0;
            $net_returns_on_completed_sales_transfer = $returns_summary['transfer_returns'] ?? 0;
        }

        // 3. Other Expenses (not linked to sales)
        $hasExpensePaymentMethod = $db->query("SHOW COLUMNS FROM expenses LIKE 'payment_method'");
        if ($hasExpensePaymentMethod) {
            $other_expenses_query = $db->query(
                "SELECT
                    SUM(amount) AS total_other_expenses,
                    SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) AS total_other_expenses_cash
                 FROM expenses
                 WHERE shift_id = ? AND sale_id IS NULL",
                [$shift_id]
            );
            if ($other_expenses_query) {
                $other_expenses_amount = $other_expenses_query['total_other_expenses'] ?? 0;
                $other_expenses_cash_amount = $other_expenses_query['total_other_expenses_cash'] ?? 0;
            }
        } else {
            $other_expenses_query = $db->query(
                "SELECT SUM(amount) AS total_other_expenses
                 FROM expenses
                 WHERE shift_id = ? AND sale_id IS NULL",
                [$shift_id]
            );
            if ($other_expenses_query) {
                $other_expenses_amount = $other_expenses_query['total_other_expenses'] ?? 0;
                $other_expenses_cash_amount = $other_expenses_amount;
            }
        }

        // --- Final KPI Calculations ---
        
        // Venta por Efectivo (NET)
        $net_cash_sales = $gross_cash_sales_completed - $net_returns_on_completed_sales_cash;

        // Venta por Transferencia (NET)
        $net_transfer_sales = $gross_transfer_sales_completed - $net_returns_on_completed_sales_transfer;

        // Venta Actual (Total Sales NETO)
        $total_sales_current_net = $net_cash_sales + $net_transfer_sales;

        // Anulación o Cancelada (Total NET Returns from Completed Sales)
        $total_returns_amount = $net_returns_on_completed_sales_cash + $net_returns_on_completed_sales_transfer;

        // Efectivo Esperado en Caja (Money actually in the drawer)
        // Initial Cash
        // + Venta por Efectivo (NET)
        // - Otros Gastos
        $expected_cash_in_drawer = $initial_cash + $net_cash_sales - $other_expenses_cash_amount;


    }

} catch (Exception $e) {
    // silently fail
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Turno en Curso - 4 Básico A</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary-color: #7c3aed; --secondary-color: #6b7280; --danger-color: #fb7185;
            --success-color: #14b8a6; --warning-color: #f97316; --info-color: #3b82f6;
            --light-gray: #f3f0ff; --dark-gray: #1e1b4b;
            --font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            --card-shadow: 0 0.5rem 1.5rem rgba(30, 27, 75, 0.1);
        }
        body { font-family: var(--font-family); background-color: var(--light-gray); color: var(--dark-gray); margin: 0; padding: 0.75rem; overflow-x: hidden; }
        .container { max-width: 1200px; margin: auto; background-color: transparent; box-shadow: none; padding: 0; }
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--light-gray);
            padding: 6px 0 10px;
            margin-bottom: 10px;
        }
        .header {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            margin-bottom: 8px;
            gap: 10px;
        }
        h1 { margin: 0; color: var(--dark-gray); }
        .title-wrap { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .logo-column img {
            width: 68px;
            height: 68px;
            object-fit: contain;
            border-radius: 1.25rem;
            border: 1px solid #dbe4ff;
            background: #fff;
            padding: 4px;
        }
        .top-menu-row {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .shift-badge {
            background: #eef4ff;
            color: var(--primary-color);
            border: 1px solid #cfe0ff;
            border-radius: 999rem;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 4px 8px;
        }
        .btn { padding: 0.65rem 0.95rem; border: none; border-radius: 999rem; cursor: pointer; text-decoration: none; font-size: 0.9rem; font-weight: 700; color: white !important; white-space: nowrap; min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; }
        .btn-secondary { background-color: var(--secondary-color) !important; }
        .btn-primary { background-color: var(--primary-color) !important; }
        .btn-success { background-color: var(--success-color) !important; }
        .btn-info { background-color: var(--info-color) !important; }
        .btn-danger { background-color: var(--danger-color) !important; }
        .btn-menu { background-color: #16a34a !important; }
        .btn-pos { background-color: #dc2626 !important; }
        .btn-logout { background-color: #2563eb !important; }
        .btn-end-blue { background-color: #2563eb !important; }

        /* Dashboard Grid */
        #dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .kpi-card {
            background-color: #fff;
            padding: 1.25rem;
            border-radius: 1.4rem;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 120px;
        }
        .kpi-title { font-size: 0.9rem; color: var(--secondary-color); margin-bottom: 10px; }
        .kpi-value { font-size: 2rem; font-weight: bold; color: var(--dark-gray); }
        .kpi-value.positive { color: var(--success-color); }
        .kpi-value.negative { color: var(--danger-color); }
        .kpi-value.info { color: var(--info-color); }
        .kpi-value.warning { color: var(--warning-color); }

        .kpi-card.highlight-primary { border-left: 5px solid var(--primary-color); }
        .kpi-card.highlight-success { border-left: 5px solid var(--success-color); }
        .kpi-card.highlight-danger { border-left: 5px solid var(--danger-color); }
        .kpi-card.highlight-warning { border-left: 5px solid var(--warning-color); }
        .kpi-card.highlight-info { border-left: 5px solid var(--info-color); }

        .footer-actions {
            margin-top: 30px;
            text-align: right;
        }

        @media (max-width: 760px) {
            body { padding: 8px; }
            h1 { font-size: 1.1rem; }
            .kpi-value { font-size: 1.55rem; }
        }
    </style>
</head>
<body>
    <?php $activePage = 'admin'; include 'top-nav.php'; ?>

    <div class="container">
        <div class="sticky-top">
            <div class="header">
                <div class="title-wrap">
                    <h1>Panel Turno en Curso</h1>
                    <span class="shift-badge"><?php echo $shift_id ? 'Turno actual (ID: ' . $shift_id . ')' : 'Sin turno activo'; ?></span>
                </div>
                <div class="logo-column">
                    <img src="img/logo.png" alt="Logo">
                </div>
            </div>

            <div class="top-menu-row">
                <?php if ($shift_id): ?>
                    <button id="end-shift-btn" class="btn btn-end-blue">Cerrar Turno (ID: <?php echo $shift_id; ?>)</button>
                <?php endif; ?>
                <a href="index.php" class="btn btn-pos">Regresar al POS</a>
                <a href="logout.php" class="btn btn-logout">Cerrar sesión</a>
            </div>
        </div>

        <?php if ($shift_id): ?>
            <p>Mostrando datos del turno activo para control rápido de caja.</p>
        <?php else: ?>
            <p>No hay un turno activo. Inicia uno para ver el resumen.</p>
        <?php endif; ?>

        <div id="dashboard-grid">
            <div class="kpi-card highlight-success">
                <div class="kpi-title">Venta Actual</div>
                <div class="kpi-value">$<?php echo number_format($total_sales_current_net, 0, ',', '.'); ?></div>
            </div>
            <div class="kpi-card highlight-info">
                <div class="kpi-title">Venta por Efectivo</div>
                <div class="kpi-value">$<?php echo number_format($net_cash_sales, 0, ',', '.'); ?></div>
            </div>
            <div class="kpi-card highlight-info">
                <div class="kpi-title">Venta por Transferencia</div>
                <div class="kpi-value">$<?php echo number_format($net_transfer_sales, 0, ',', '.'); ?></div>
            </div>
            <div class="kpi-card highlight-danger">
                <div class="kpi-title">Anulación o Cancelada</div>
                <div class="kpi-value negative">$<?php echo number_format($total_returns_amount, 0, ',', '.'); ?></div>
            </div>
            <div class="kpi-card highlight-warning">
                <div class="kpi-title">Otros Gastos</div>
                <div class="kpi-value">$<?php echo number_format($other_expenses_amount, 0, ',', '.'); ?></div>
            </div>
            <div class="kpi-card highlight-primary">
                <div class="kpi-title">Efectivo Inicial</div>
                <div class="kpi-value">$<?php echo number_format($initial_cash, 0, ',', '.'); ?></div>
            </div>
            <div class="kpi-card highlight-success">
                <div class="kpi-title">Efectivo Esperado en Caja</div>
                <div class="kpi-value">$<?php echo number_format($expected_cash_in_drawer, 0, ',', '.'); ?></div>
            </div>
        </div>

        <div class="footer-actions"></div>
    </div>

    <div id="toast-notification"></div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const endShiftBtn = document.getElementById('end-shift-btn');
            
            if (endShiftBtn) {
                endShiftBtn.addEventListener('click', () => {
                    const shiftId = <?php echo json_encode($shift_id); ?>;
                    const expectedCash = <?php echo json_encode($expected_cash_in_drawer); ?>;
                    
                    if (confirm(`¿Estás seguro de que quieres cerrar el Turno ${shiftId}? El efectivo esperado en caja es: $${expectedCash.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")} `)) {
                        closeShift(shiftId, expectedCash);
                    }
                });
            }

            function showToast(message, isError = false) {
                const toast = document.getElementById('toast-notification');
                if (!toast) return;

                toast.textContent = message;
                toast.style.backgroundColor = isError ? 'var(--danger-color)' : 'var(--success-color)';
                toast.classList.add('show');

                setTimeout(() => {
                    toast.classList.remove('show');
                }, 3000);
            }

            async function closeShift(shiftId, finalCash) {
                const formData = new FormData();
                formData.append('shift_id', shiftId);
                formData.append('final_cash', finalCash);

                try {
                    const response = await fetch('end_shift_api.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        showToast('Turno cerrado con éxito!');
                        setTimeout(() => window.location.href = 'index.php', 1000); // Go back to POS
                    } else {
                        showToast('Error al cerrar turno: ' + result.message, true);
                    }
                } catch (error) {
                    console.error('Failed to close shift:', error);
                    showToast('Falla de conexión al intentar cerrar el turno.', true);
                }
            }
        });
    </script>
</body>
</html>