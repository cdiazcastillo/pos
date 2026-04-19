<?php
session_start();
require_once 'config/db.php';

// --- Authentication & Shift Check ---
// For now, we'll simulate a logged-in user and an open shift.
// In a real implementation, you would have a full login system.
if (!isset($_SESSION['user_id'])) {
    // Forcing a user for development purposes.
    // header('Location: login.php');
    // exit;
    $_SESSION['user_id'] = 1; // Simulate user 1 (admin) is logged in.
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Check for an active shift for the current user
$active_shift = $db->query("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'", [$_SESSION['user_id']]);
$is_shift_open = $active_shift !== false;

// --- Fetch Products ---
$products = $db->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC", [], true);

/**
 * Determines the stock level indicator class for a product.
 *
 * @param array $product The product data array.
 * @return string The CSS class for the semaphore.
 */
function get_stock_semaphore_class($product) {
    if ($product['stock_level'] <= 0) {
        return 'stock-empty'; // Red / Disabled
    }
    if ($product['stock_level'] <= $product['min_stock_warning']) {
        return 'stock-low'; // Yellow
    }
    return 'stock-ok'; // Green
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VentasCAF - Punto de Venta</title>
    <link rel="apple-touch-icon" href="img/logo.png">
    <style>
        /* --- General & Variables --- */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        body {
            font-family: var(--font-family);
            margin: 0;
            background-color: var(--light-gray);
            color: var(--dark-gray);
        }

        /* --- Main Layout --- */
        #pos-container {
            height: var(--app-height);
            display: flex;
            flex-direction: column;
        }

        #sales-interface {
            display: flex;
            flex-grow: 1;
            height: calc(var(--app-height) - 50px); /* Adjust if you have a header */
        }

        #product-grid {
            flex: 3;
            padding: 15px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            overflow-y: auto;
            min-height: 0; /* Flexbox bug fix */
        }

        #cart {
            flex: 1;
            background-color: #fff;
            border-left: 1px solid #dee2e6;
            display: flex;
            flex-direction: column;
            padding: 10px;
        }

        /* --- Product Cards & Semaphore --- */
        .product-card {
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.2s, filter 0.2s;
            background-color: var(--primary-color);
            color: white;
            position: relative; /* Acts as a container for the overlay */
            overflow: hidden; /* Hides parts of the overlay that might stick out */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 10px;
            width: 120px;
            height: 120px;
            margin: 2px;
        }

        .product-card:hover {
            background-color: #0056b3;
            transform: translateY(-3px);
        }

        .product-card:active {
            transform: scale(0.96);
            filter: brightness(1.1);
        }

        .product-info {
            padding: 0;
            text-align: center;
            z-index: 2; /* Ensures text is above the overlay */
        }

        .product-name {
            display: block;
            font-weight: 600;
            font-size: 1rem;
        }

        .product-price {
            color: #e9ecef;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* Unified Stock Indicator as a bottom banner */
        .stock-indicator {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 25px; /* Fixed height for the banner */
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
            font-size: 0.8rem;
            text-align: center;
            z-index: 1;
            transition: background-color 0.3s ease;
        }

        /* OK Stock: Green banner */
        .product-card.stock-ok .stock-indicator {
            background-color: rgba(40, 167, 69, 0.8); /* success-color */
        }

        /* Low Stock: Yellow banner */
        .product-card.stock-low .stock-indicator {
            background-color: rgba(255, 193, 7, 0.8); /* warning-color */
            color: #343a40; /* Dark text for readability on yellow */
        }

        /* Out of Stock: Red banner */
        .product-card.stock-empty .stock-indicator {
            cursor: not-allowed;
            background-color: rgba(220, 53, 69, 0.8); /* danger-color */
        }

        /* Disabled state for out-of-stock items */
        .product-card.stock-empty {
            cursor: not-allowed;
            background-color: var(--secondary-color); /* Greyed out */
        }
        .product-card.stock-empty:hover {
            transform: none;
            background-color: var(--secondary-color);
        }


        /* --- Cart --- */
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .cart-header h2 { margin: 0; font-size: 1.2rem; }

        #cart-items {
            flex-grow: 1;
            overflow-y: auto;
            padding: 0;
        }

        .cart-item {
            display: flex;
            justify-content: flex-start;
            gap: 5px;
            padding: 0;
            align-items: center;
        }
        .cart-item-name { flex-grow: 1; }
        .cart-item-qty { margin: 0 10px; }
        .cart-item-price { font-weight: 600; }
        .remove-item-btn { cursor: pointer; color: var(--danger-color); background: none; border: none; font-size: 1rem; }

        #cart-summary {
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: auto;
        }
        .summary-row { display: flex; justify-content: space-between; padding: 4px 0; }
        .summary-row.total { font-weight: bold; font-size: 1.2rem; border-top: 2px solid #333; padding-top: 8px; margin-top: 5px; }

        #cart-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px;
            margin-top: 10px;
        }
        .action-btn {
            padding: 8px 4px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
        }
        .action-btn.primary { background-color: var(--primary-color); color: white; }
        .action-btn.secondary { background-color: var(--secondary-color); color: white; }
        .action-btn.success { background-color: var(--success-color); color: white; }
        .action-btn.danger { background-color: var(--danger-color); color: white; }


        /* --- Shift Overlay & Modals --- */
        .shift-closed #sales-interface {
            filter: blur(5px);
            pointer-events: none;
        }

        #shift-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        #start-shift-modal {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
        }
        #start-shift-modal h2 { margin-top: 0; }
        #start-shift-modal .form-group { margin: 15px 0; }
        #start-shift-modal label { display: block; margin-bottom: 5px; }
        #start-shift-modal input { width: 100%; padding: 10px; font-size: 1.2rem; text-align: center; }

        /* General Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1001;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 450px;
        }
        .modal-content h2 { margin-top: 0; }
        .modal-content .form-group { margin: 20px 0; }
        .modal-content label, .modal-content p { font-size: 1.1rem; }
        .modal-content input { width: 100%; padding: 10px; font-size: 1.3rem; }
        .modal-actions { display: flex; justify-content: space-between; gap: 15px; }
        .modal-actions button { flex-grow: 1; }

        button {
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            padding: 10px 15px;
            border: 1px solid transparent;
        }
        button.primary { background-color: var(--primary-color); color: white; }
        button.secondary { background-color: var(--secondary-color); color: white; }

        /* --- Responsive --- */
        @media (max-width: 768px) {
            #sales-interface {
                flex-direction: column;
                height: 100%; /* Explicitly define height for flex children context */
                min-height: 0; /* Flexbox bug fix */
            }
            #product-grid {
                flex: 2;
                min-height: 0; /* Flexbox bug fix */
            }
            #cart {
                border-left: none;
                border-top: 1px solid #dee2e6;
                flex: 1;
                min-height: 250px;
            }
        }

        /* --- Toast Notification --- */
        #toast-notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--success-color);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            z-index: 2000;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.5s, visibility 0.5s, transform 0.5s;
        }

        #toast-notification.show {
            visibility: visible;
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body ontouchstart="">
    <div id="pos-container" class="<?php echo !$is_shift_open ? 'shift-closed' : ''; ?>">
        
        <!-- Main Content: Products and Cart -->
        <main id="sales-interface">
            <!-- Product Grid -->
            <div id="product-grid">
                <?php foreach ($products as $product): ?>
                    <?php $stock_class = get_stock_semaphore_class($product); ?>
                    <div class="product-card <?php echo $stock_class; ?>" 
                         data-id="<?php echo $product['id']; ?>"
                         data-name="<?php echo htmlspecialchars($product['name']); ?>"
                         data-price="<?php echo $product['price']; ?>"
                         <?php echo ($stock_class === 'stock-empty') ? 'disabled' : ''; ?>>
                        
                        <div class="product-info">
                            <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                            <span class="product-price">$<?php echo number_format($product['price'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="stock-indicator">
                            <span>Quedan <?php echo $product['stock_level']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Cart Section -->
            <aside id="cart">
                <div class="cart-header">
                    <h2>Carrito</h2>
                </div>
                <div id="cart-items">
                    <!-- Cart items will be injected here by JavaScript -->
                </div>
                <div id="cart-summary">
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="cart-total">$0.00</span>
                    </div>
                </div>
                <div id="cart-actions">
                    <button id="cash-payment-btn" class="action-btn primary">EFECTIVO</button>
                    <button id="transfer-payment-btn" class="action-btn success">TRANSFERENCIA</button>
                    <button id="clear-cart-btn" class="action-btn danger">LIMPIAR</button>
                    <a href="admin.php" id="main-menu-btn" class="action-btn secondary">MENU</a>
                </div>
            </aside>
        </main>

        <!-- Shift Closed Overlay -->
        <?php if (!$is_shift_open): ?>
        <div id="shift-overlay">
            <div id="start-shift-modal">
                <h2>Iniciar Turno</h2>
                <p>No hay un turno activo. Ingresa el monto inicial en caja para comenzar a vender.</p>
                <form id="start-shift-form">
                    <div class="form-group">
                        <label for="initial_cash">Efectivo Inicial:</label>
                        <input type="number" id="initial_cash" name="initial_cash" step="1" min="0" required>
                    </div>
                    <button type="submit" class="primary">Iniciar Turno</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div> <!-- /pos-container -->

    <div id="toast-notification"></div>
    <script src="main.js"></script>
</body>
</html>