<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    die('Acceso denegado. Por favor, inicie sesión.');
}

$sale_id = filter_input(INPUT_GET, 'sale_id', FILTER_VALIDATE_INT);
if (!$sale_id) {
    die('ID de venta inválido.');
}

$db = Database::getInstance();

// Fetch the sale
$sale = $db->query("SELECT * FROM sales WHERE id = ?", [$sale_id]);
if (!$sale || $sale['status'] === 'voided') {
    die('Venta no encontrada o ya ha sido anulada completamente.');
}

// Fetch sale items
$items = $db->query(
    "SELECT si.id, si.product_id, si.quantity, si.quantity_returned, si.price_per_unit, p.name 
     FROM sale_items si
     JOIN products p ON si.product_id = p.id
     WHERE si.sale_id = ?",
    [$sale_id],
    true
);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesar Devolución - Venta #<?php echo $sale_id; ?></title>
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            font-family: var(--font-family);
            background-color: var(--light-gray);
            color: var(--dark-gray);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 20px;
        }
        h1 { margin: 0; color: var(--primary-color); }
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 600;
            color: white !important;
        }
        .btn-secondary { background-color: var(--secondary-color) !important; }
        .btn-primary { background-color: var(--primary-color) !important; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th { background-color: var(--light-gray); text-align: left;}
        td:last-child { text-align: center; }
        .return-qty-input {
            width: 80px;
            padding: 8px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .item-fully-returned {
            text-decoration: line-through;
            opacity: 0.6;
        }
        
        .footer-summary {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid var(--dark-gray);
            text-align: right;
        }
        #total-refund-amount {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--danger-color);
        }
        .footer-actions {
            margin-top: 20px;
            text-align: right;
        }

        #toast-notification {
            position: fixed; bottom: 20px; right: 20px; background-color: var(--success-color);
            color: white; padding: 12px 25px; border-radius: 5px; box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            z-index: 2000; visibility: hidden; opacity: 0; transition: all 0.3s; transform: translateX(110%);
        }
        #toast-notification.show { visibility: visible; opacity: 1; transform: translateX(0); }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <h1>Devolución para Venta #<?php echo $sale_id; ?></h1>
            <a href="sales_history.php" class="btn btn-secondary">Volver al Historial</a>
        </div>

        <form id="return-form">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cant. Comprada</th>
                        <th>Cant. Devuelta</th>
                        <th>A Devolver Ahora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): 
                        $max_returnable = $item['quantity'] - $item['quantity_returned'];
                    ?>
                    <tr id="item-row-<?php echo $item['id']; ?>" class="<?php if ($max_returnable <= 0) echo 'item-fully-returned'; ?>">
                        <td data-label="Producto"><?php echo htmlspecialchars($item['name']); ?></td>
                        <td data-label="Cant. Comprada"><?php echo $item['quantity']; ?></td>
                        <td data-label="Cant. Devuelta" class="returned-qty-cell"><?php echo $item['quantity_returned']; ?></td>
                        <td data-label="A Devolver Ahora">
                            <?php if($max_returnable > 0): ?>
                            <input type="number" 
                                   class="return-qty-input" 
                                   name="items[<?php echo $item['id']; ?>]"
                                   value="0" 
                                   min="0" 
                                   max="<?php echo $max_returnable; ?>"
                                   data-price="<?php echo $item['price_per_unit']; ?>">
                            <?php else: ?>
                                <span>Todo Devuelto</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="footer-summary">
                <h2>Total a Devolver: <span id="total-refund-amount">$0</span></h2>
            </div>

            <div class="footer-actions">
                <button type="submit" id="submit-return-btn" class="btn btn-primary">Procesar Devolución</button>
            </div>
        </form>
    </div>

    <div id="toast-notification"></div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('return-form');
    const totalRefundElem = document.getElementById('total-refund-amount');
    const inputs = form.querySelectorAll('.return-qty-input');

    function calculateTotalRefund() {
        let total = 0;
        inputs.forEach(input => {
            const qty = parseInt(input.value, 10) || 0;
            const price = parseInt(input.dataset.price, 10);
            total += qty * price;
        });
        totalRefundElem.textContent = `$${total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")}`;
    }

    inputs.forEach(input => {
        input.addEventListener('input', calculateTotalRefund);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        const saleId = <?php echo $sale_id; ?>;
        formData.append('sale_id', saleId);
        
        const itemsToReturn = Array.from(inputs).filter(i => parseInt(i.value, 10) > 0);
        if (itemsToReturn.length === 0) {
            showToast('No has seleccionado ningún item para devolver.', true);
            return;
        }

        const submitBtn = document.getElementById('submit-return-btn');
        submitBtn.disabled = true;

        try {
            const response = await fetch('process_partial_return_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showToast('Devolución procesada con éxito.');
                
                // --- DYNAMIC UI UPDATE ---
                itemsToReturn.forEach(input => {
                    const quantityReturned = parseInt(input.value, 10);
                    const row = input.closest('tr');
                    const returnedCell = row.querySelector('.returned-qty-cell');
                    
                    // 1. Update returned quantity cell
                    const newReturnedQty = (parseInt(returnedCell.textContent, 10) || 0) + quantityReturned;
                    returnedCell.textContent = newReturnedQty;
                    
                    // 2. Update the input's max value
                    const newMax = parseInt(input.getAttribute('max'), 10) - quantityReturned;
                    input.setAttribute('max', newMax);
                    input.value = 0; // Reset input

                    // 3. If no more items can be returned, disable and style
                    if (newMax <= 0) {
                        input.disabled = true;
                        row.classList.add('item-fully-returned');
                    }
                });

                calculateTotalRefund(); // Recalculate to show $0
                // Check if all items are now returned and disable the main button
                const allInputs = Array.from(form.querySelectorAll('.return-qty-input'));
                if (allInputs.every(i => i.disabled)) {
                    submitBtn.disabled = true;
                } else {
                    submitBtn.disabled = false;
                }

            } else {
                showToast('Error: ' + result.message, true);
                submitBtn.disabled = false;
            }
        } catch(error) {
            console.error('Return processing error:', error);
            showToast('Error de conexión al procesar la devolución.', true);
            submitBtn.disabled = false;
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
});
</script>

</body>
</html>
