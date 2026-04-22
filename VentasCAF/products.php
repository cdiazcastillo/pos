<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['cashier', 'admin'], 'admin_login.php', 'index.php');

$db = Database::getInstance();
$products = $db->query("SELECT * FROM products ORDER BY id ASC", [], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Productos - 4 Básico A</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary-color: #7c3aed;
            --secondary-color: #6b7280;
            --danger-color: #fb7185;
            --success-color: #14b8a6;
            --warning-color: #f97316;
            --light-gray: #f3f0ff;
            --dark-gray: #1e1b4b;
            --font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            --card-shadow: 0 0.5rem 1.5rem rgba(30, 27, 75, 0.1);
        }
        body {
            font-family: var(--font-family);
            background-color: var(--light-gray);
            color: var(--dark-gray);
            margin: 0;
            padding: 0.75rem;
            overflow-x: hidden;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background-color: transparent;
            box-shadow: none;
            padding: 0;
        }
        .sticky-top {
            position: sticky;
            top: 0;
            z-index: 90;
            background: var(--light-gray);
            padding: 6px 0 10px;
            margin-bottom: 10px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        h1 { margin: 0; color: var(--dark-gray); font-size: 1.15rem; }
        .title-wrap { display: flex; align-items: center; gap: 10px; }
        .logo-column img {
            width: 66px;
            height: 66px;
            object-fit: contain;
            border-radius: 1.25rem;
            border: 1px solid #dbe4ff;
            background: #fff;
            padding: 4px;
        }
        .top-menu-row { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 999rem;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 700;
            color: white !important;
            white-space: nowrap;
            min-height: 2.75rem;
        }
        .btn-primary { background-color: var(--primary-color) !important; }
        .btn-secondary { background-color: var(--secondary-color) !important; }
        .btn-success { background-color: var(--success-color) !important; }
        .btn-danger { background-color: var(--danger-color) !important; }
        .btn-warning { background-color: var(--warning-color) !important; }
        .btn-menu { background-color: #16a34a !important; }
        .btn-pos { background-color: #dc2626 !important; }
        .btn-logout { background-color: #2563eb !important; }

        .product-modal-content {
            background: #fff;
            padding: clamp(16px, 3vw, 30px);
            border-radius: 1.25rem;
            width: min(500px, 94vw);
            max-height: 92vh;
            overflow-y: auto;
            box-sizing: border-box;
        }

        .product-modal-content .form-group {
            margin-bottom: 15px;
        }

        .product-modal-content label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .product-modal-content input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 0.9rem;
            box-sizing: border-box;
            font-size: 0.95rem;
        }

        .product-modal-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 640px) {
            .product-modal-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .product-modal-actions .btn {
                width: 100%;
                text-align: center;
            }
        }

        /* New Card Grid Layout */
        #product-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .product-admin-card {
            background-color: #fff;
            border-radius: 1.4rem;
            box-shadow: var(--card-shadow);
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
            border-radius: 999rem;
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
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(2px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 1400;
        }

        .modal-overlay.is-open {
            display: flex;
        }

        .modal-content.product-modal-content {
            width: min(500px, 94vw);
            border-radius: 1.25rem;
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.25);
            animation: modalPopIn 0.2s ease-out;
        }

        @keyframes modalPopIn {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

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

        /* FAB Flotante */
        .fab-button {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            z-index: 999;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .fab-button:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
            transform: scale(1.1);
        }

        .fab-button:active {
            transform: scale(0.95);
        }

        @media (max-width: 640px) {
            .fab-button {
                bottom: 16px;
                right: 16px;
                width: 56px;
                height: 56px;
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <?php $activePage = 'admin'; include 'top-nav.php'; ?>

    <div class="container">
        <div class="sticky-top">
            <div class="header">
                <div class="title-wrap">
                    <h1>Gestionar Productos</h1>
                </div>
                <div class="logo-column">
                    <img src="img/logo.png" alt="Logo">
                </div>
            </div>
            <div class="top-menu-row">
                <a href="admin.php" class="btn btn-menu">Menú</a>
                <a href="index.php" class="btn btn-pos">Regresar al POS</a>
                <a href="logout.php" class="btn btn-logout">Cerrar sesión</a>
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

    <!-- FAB Flotante para Crear Producto -->
    <button id="fab-create-product" class="fab-button" title="Crear nuevo producto">+</button>

    <!-- Product Modal (remains the same) -->
    <div id="product-modal" class="modal-overlay" aria-hidden="true">
        <div class="modal-content product-modal-content">
            <h2 id="modal-title">Crear Nuevo Producto</h2>
            <form id="product-form">
                <input type="hidden" id="product-id" name="id">
                <div class="form-group">
                    <label for="name">Nombre del Producto</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="price">Precio</label>
                    <input type="text" id="price" name="price" inputmode="numeric" pattern="[0-9]*" step="1" min="0" required>
                </div>
                <div class="form-group">
                    <label for="stock_level">Stock Actual</label>
                    <input type="text" id="stock_level" name="stock_level" inputmode="numeric" pattern="[0-9]*" step="1" min="0" required>
                </div>
                <div class="form-group">
                    <label for="min_stock_warning">Alerta de Stock Mínimo</label>
                    <input type="text" id="min_stock_warning" name="min_stock_warning" inputmode="numeric" pattern="[0-9]*" step="1" min="0" required>
                </div>
                <div class="modal-actions product-modal-actions">
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
    const fabCreateProductBtn = document.getElementById('fab-create-product');
    const cancelBtn = document.getElementById('cancel-btn');
    const productForm = document.getElementById('product-form');
    const modalTitle = document.getElementById('modal-title');
    const productIdInput = document.getElementById('product-id');
    const productGrid = document.getElementById('product-card-grid');
    const priceInput = document.getElementById('price');
    const stockLevelInput = document.getElementById('stock_level');
    const minStockWarningInput = document.getElementById('min_stock_warning');

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function openCreateModal() {
        productForm.reset();
        productIdInput.value = '';
        modalTitle.textContent = 'Crear Nuevo Producto';
        openModal();
    }

    function openEditModal(btn) {
        modalTitle.textContent = 'Editar Producto';
        productIdInput.value = btn.dataset.id;
        document.getElementById('name').value = btn.dataset.name;
        document.getElementById('price').value = btn.dataset.price;
        document.getElementById('stock_level').value = btn.dataset.stock;
        document.getElementById('min_stock_warning').value = btn.dataset.minStock;
        openModal();
    }

    function forceNumericInput(input) {
        if (!input) return;
        input.addEventListener('input', () => {
            input.value = String(input.value || '').replace(/\D/g, '');
        });
    }

    [priceInput, stockLevelInput, minStockWarningInput].forEach(forceNumericInput);

    // --- Event Listeners ---

    newProductBtn.addEventListener('click', openCreateModal);

    fabCreateProductBtn.addEventListener('click', openCreateModal);

    cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    productGrid.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn');
        if (!btn) return;

        if (btn.classList.contains('edit-btn')) {
            e.preventDefault();
            openEditModal(btn);
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

        if (!/^\d+$/.test(String(priceInput.value || '')) || !/^\d+$/.test(String(stockLevelInput.value || '')) || !/^\d+$/.test(String(minStockWarningInput.value || ''))) {
            showToast('Precio, stock y alerta deben contener solo números.', true);
            return;
        }

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
