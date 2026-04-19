<?php
session_start();
require_once 'config/db.php';

// In a real application, you'd also check for an 'admin' role.
if (!isset($_SESSION['user_id'])) {
    die('Acceso denegado. Por favor, inicie sesión.');
}

$db = Database::getInstance();
$products = $db->query("SELECT * FROM products ORDER BY id ASC", [], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Productos - VentasCAF</title>
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --danger-color: #dc3545;
            --success-color: #28a745;
            --warning-color: #ffc107;
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
            max-width: 1200px;
            margin: auto;
            background-color: transparent;
            box-shadow: none;
            padding: 0;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        h1 { margin: 0; color: var(--dark-gray); }
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
        .btn-primary { background-color: var(--primary-color) !important; }
        .btn-secondary { background-color: var(--secondary-color) !important; }
        .btn-success { background-color: var(--success-color) !important; }
        .btn-danger { background-color: var(--danger-color) !important; }
        .btn-warning { background-color: var(--warning-color) !important; }

        /* New Card Grid Layout */
        #product-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .product-admin-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: box-shadow 0.2s;
        }
        .product-admin-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        .product-admin-card.inactive-card {
            opacity: 0.6;
            background-color: #f8f9fa;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .card-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            color: white;
        }
        .status-badge.active { background-color: var(--success-color); }
        .status-badge.inactive { background-color: var(--secondary-color); }

        .card-body {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .info-item { font-size: 0.95rem; }
        .info-item strong { color: var(--dark-gray); }
        .info-item span { color: var(--secondary-color); }

        .card-footer {
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Modal Styles */
        .modal-overlay { display: none; } /* Simplified */

        /* Toast Notification */
        #toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--success-color);
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            z-index: 2000;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s, visibility 0.3s, transform 0.3s;
            transform: translateX(100%);
        }
        #toast-notification.show {
            visibility: visible;
            opacity: 1;
            transform: translateX(0);
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <img src="img/logo.png" alt="Logo" style="max-width: 100px;">
            <h1>Gestionar Productos</h1>
            <div>
                <a href="admin.php" class="btn btn-secondary">Volver al Menú</a>
                <button id="new-product-btn" class="btn btn-primary">Crear Nuevo Producto</button>
            </div>
        </div>

        <div id="product-card-grid">
            <?php foreach($products as $product): ?>
            <div class="product-admin-card <?php echo $product['is_active'] ? '' : 'inactive-card'; ?>" id="product-<?php echo $product['id']; ?>">
                <div class="card-header">
                    <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                    <span class="status-badge <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
                        <?php echo $product['is_active'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="info-item"><strong>Precio:</strong> <span>$<?php echo number_format($product['price']); ?></span></div>
                    <div class="info-item"><strong>ID:</strong> <span><?php echo $product['id']; ?></span></div>
                    <div class="info-item"><strong>Stock:</strong> <span><?php echo $product['stock_level']; ?></span></div>
                    <div class="info-item"><strong>Alerta Stock:</strong> <span><?php echo $product['min_stock_warning']; ?></span></div>
                </div>
                <div class="card-footer actions">
                    <button class="btn btn-warning edit-btn"
                       data-id="<?php echo $product['id']; ?>"
                       data-name="<?php echo htmlspecialchars($product['name']); ?>"
                       data-price="<?php echo $product['price']; ?>"
                       data-stock="<?php echo $product['stock_level']; ?>"
                       data-min-stock="<?php echo $product['min_stock_warning']; ?>">Editar</button>
                    
                    <?php if ($product['is_active']): ?>
                        <button class="btn btn-danger toggle-active-btn" data-id="<?php echo $product['id']; ?>" data-status="0">Desactivar</button>
                    <?php else: ?>
                        <button class="btn btn-success toggle-active-btn" data-id="<?php echo $product['id']; ?>" data-status="1">Activar</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Product Modal (remains the same) -->
    <div id="product-modal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; z-index: 1001;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px;">
            <h2 id="modal-title">Crear Nuevo Producto</h2>
            <form id="product-form">
                <input type="hidden" id="product-id" name="id">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="name" style="display: block; margin-bottom: 5px; font-weight: 600;">Nombre del Producto</label>
                    <input type="text" id="name" name="name" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="price" style="display: block; margin-bottom: 5px; font-weight: 600;">Precio</label>
                    <input type="number" id="price" name="price" step="1" min="0" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="stock_level" style="display: block; margin-bottom: 5px; font-weight: 600;">Stock Actual</label>
                    <input type="number" id="stock_level" name="stock_level" step="1" min="0" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="min_stock_warning" style="display: block; margin-bottom: 5px; font-weight: 600;">Alerta de Stock Mínimo</label>
                    <input type="number" id="min_stock_warning" name="min_stock_warning" step="1" min="0" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                </div>
                <div class="modal-actions" style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" id="cancel-btn" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="toast-notification"></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('product-modal');
    const newProductBtn = document.getElementById('new-product-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const productForm = document.getElementById('product-form');
    const modalTitle = document.getElementById('modal-title');
    const productIdInput = document.getElementById('product-id');
    const productGrid = document.getElementById('product-card-grid');

    // --- Event Listeners ---

    newProductBtn.addEventListener('click', () => {
        productForm.reset();
        productIdInput.value = '';
        modalTitle.textContent = 'Crear Nuevo Producto';
        modal.style.display = 'flex';
    });

    cancelBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    productGrid.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn');
        if (!btn) return;

        if (btn.classList.contains('edit-btn')) {
            e.preventDefault();
            modalTitle.textContent = 'Editar Producto';
            productIdInput.value = btn.dataset.id;
            document.getElementById('name').value = btn.dataset.name;
            document.getElementById('price').value = btn.dataset.price;
            document.getElementById('stock_level').value = btn.dataset.stock;
            document.getElementById('min_stock_warning').value = btn.dataset.minStock;
            modal.style.display = 'flex';
        }

        if (btn.classList.contains('toggle-active-btn')) {
            e.preventDefault();
            const id = btn.dataset.id;
            const newStatus = btn.dataset.status;
            // The confirm dialog is removed as per the user's request.
            toggleProductStatus(btn, id, newStatus);
        }
    });

    productForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(productForm);
        const id = formData.get('id');
        const endpoint = id ? 'update_product_api.php' : 'add_product_api.php';
        
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                window.location.reload();
            } else {
                showToast('Error: ' + result.message, true);
            }
        } catch (error) {
            console.error('Form submission error:', error);
            showToast('Error de conexión al guardar el producto.', true);
        }
    });

    // --- Helper Functions ---

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

    async function toggleProductStatus(button, id, status) {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('is_active', status);

        try {
            const response = await fetch('toggle_product_status_api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showToast(`Producto ${status === '1' ? 'activado' : 'desactivado'} con éxito.`);
                
                // --- Dynamic UI Update ---
                const card = document.getElementById(`product-${id}`);
                const statusBadge = card.querySelector('.status-badge');
                
                const isActivating = status === '1';

                // 1. Update card and badge styles
                card.classList.toggle('inactive-card', !isActivating);
                statusBadge.classList.toggle('active', isActivating);
                statusBadge.classList.toggle('inactive', !isActivating);
                statusBadge.textContent = isActivating ? 'Activo' : 'Inactivo';

                // 2. Update the button
                button.textContent = isActivating ? 'Desactivar' : 'Activar';
                button.dataset.status = isActivating ? '0' : '1';
                button.classList.toggle('btn-danger', isActivating);
                button.classList.toggle('btn-success', !isActivating);

            } else {
                showToast('Error al cambiar el estado: ' + result.message, true);
            }
        } catch (error) {
            console.error('Toggle status error:', error);
            showToast('Error de conexión al cambiar el estado.', true);
        }
    }
});
</script>

</body>
</html>
