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
$products = $db->query(
    "SELECT *
     FROM products
     WHERE is_active = 1
     ORDER BY CASE WHEN stock_level <= 0 THEN 1 ELSE 0 END ASC, name ASC",
    [],
    true
);
$products = is_array($products) ? $products : [];

$stock_available_count = 0;
foreach ($products as $item) {
    if (isset($item['stock_level']) && intval($item['stock_level']) > 0) {
        $stock_available_count++;
    }
}

$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = rtrim($basePath, '/');
$baseHref = ($basePath === '' || $basePath === '.') ? '/' : $basePath . '/';

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
    <base href="<?php echo htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="img/logo.png">
    <style>
        :root {
            --primary-color: #3457dc;
            --primary-dark: #253ea8;
            --secondary-color: #6b7280;
            --success-color: #1f9d61;
            --warning-color: #f59e0b;
            --danger-color: #dc3545;
            --light-gray: #f3f5fb;
            --surface: #ffffff;
            --surface-soft: #f8faff;
            --dark-gray: #111827;
            --muted: #6b7280;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            margin: 0;
            background-color: var(--light-gray);
            color: var(--dark-gray);
        }

        #pos-container {
            height: var(--app-height);
            display: flex;
            flex-direction: column;
            max-width: 1600px;
            margin: 0 auto;
            padding: 14px;
            gap: 12px;
        }

        .topbar {
            background: linear-gradient(135deg, var(--primary-color), #5146e5);
            color: #fff;
            border-radius: 16px;
            padding: 14px 16px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 12px;
            align-items: center;
            box-shadow: 0 10px 24px rgba(37, 62, 168, 0.25);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background-color: rgba(255, 255, 255, 0.95);
            padding: 4px;
        }

        .brand h1 {
            margin: 0;
            font-size: 1.2rem;
        }

        .brand p {
            margin: 3px 0 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }

        .topbar-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .pill {
            background-color: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .topbar-actions {
            display: flex;
            gap: 8px;
            justify-self: end;
        }

        .topbar-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.36);
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            font-size: 0.85rem;
            font-weight: 700;
            padding: 9px 12px;
            transition: all 0.2s ease;
        }

        .topbar-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .topbar-link.secondary {
            background-color: rgba(17, 24, 39, 0.2);
        }

        #sales-interface {
            display: flex;
            flex-grow: 1;
            min-height: 0;
            gap: 12px;
        }

        .products-panel {
            flex: 2.9;
            background-color: var(--surface);
            border-radius: 16px;
            border: 1px solid #e8ecf8;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid #eef2fb;
            background-color: var(--surface-soft);
        }

        .panel-head h2 {
            margin: 0;
            font-size: 1rem;
        }

        .panel-head span {
            font-size: 0.84rem;
            color: var(--muted);
            font-weight: 600;
        }

        #product-grid {
            flex: 1;
            padding: 14px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
            gap: 10px;
            align-content: start;
            overflow-y: auto;
            min-height: 0;
        }

        #cart {
            flex: 1.1;
            background-color: var(--surface);
            border: 1px solid #e8ecf8;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            padding: 12px;
            min-height: 0;
        }

        .product-card {
            border: 1px solid #e5e9fb;
            border-radius: 14px;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            background-color: var(--surface-soft);
            color: var(--dark-gray);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            min-height: 124px;
            padding: 12px 10px 30px;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(52, 87, 220, 0.14);
            border-color: #cfd9ff;
        }

        .product-card:active {
            transform: scale(0.98);
        }

        .product-info {
            z-index: 2;
            display: grid;
            gap: 6px;
        }

        .product-name {
            display: block;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .product-price {
            color: var(--primary-dark);
            font-size: 0.95rem;
            font-weight: 700;
        }

        .stock-indicator {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
            font-size: 0.75rem;
            text-align: center;
            z-index: 1;
        }

        .product-card.stock-ok .stock-indicator {
            background-color: rgba(31, 157, 97, 0.92);
        }

        .product-card.stock-low .stock-indicator {
            background-color: rgba(245, 158, 11, 0.92);
            color: #111827;
        }

        .product-card.stock-empty .stock-indicator {
            cursor: not-allowed;
            background-color: rgba(220, 53, 69, 0.92);
        }

        .product-card.stock-empty {
            cursor: not-allowed;
            background-color: #e5e7eb;
            color: #4b5563;
            border-color: #d1d5db;
        }

        .product-card.stock-empty:hover {
            transform: none;
            box-shadow: none;
            border-color: #d1d5db;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eef2fb;
            padding: 4px 4px 10px;
        }

        .cart-header h2 {
            margin: 0;
            font-size: 1.05rem;
        }

        .cart-header small {
            color: var(--muted);
            font-weight: 600;
        }

        #cart-items {
            flex-grow: 1;
            overflow-y: auto;
            padding: 6px 2px;
            display: grid;
            gap: 4px;
        }

        .cart-item {
            display: flex;
            justify-content: flex-start;
            gap: 8px;
            padding: 8px;
            align-items: center;
            border-radius: 10px;
            background-color: #f8faff;
            border: 1px solid #eef2fb;
        }

        .cart-item-name {
            flex-grow: 1;
            font-size: 0.92rem;
            font-weight: 600;
        }

        .cart-item-qty {
            margin: 0 6px;
            color: var(--muted);
            font-weight: 700;
            font-size: 0.86rem;
        }

        .cart-item-price {
            font-weight: 700;
            font-size: 0.9rem;
        }

        .remove-item-btn {
            cursor: pointer;
            color: var(--danger-color);
            background: none;
            border: none;
            font-size: 1.05rem;
            line-height: 1;
        }

        #cart-summary {
            border-top: 1px solid #eef2fb;
            padding-top: 10px;
            margin-top: auto;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

        .summary-row.total {
            font-weight: 800;
            font-size: 1.2rem;
            border-top: 2px solid #cfd8f7;
            padding-top: 8px;
            margin-top: 5px;
            color: var(--primary-dark);
        }

        #cart-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 12px;
        }

        .action-btn {
            padding: 10px 6px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 800;
            text-align: center;
            text-decoration: none;
            transition: transform 0.15s ease, filter 0.15s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            filter: brightness(0.95);
        }

        .action-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            filter: none;
        }

        .action-btn.primary { background-color: var(--primary-color); color: white; }
        .action-btn.secondary { background-color: var(--secondary-color); color: white; }
        .action-btn.success { background-color: var(--success-color); color: white; }
        .action-btn.danger { background-color: var(--danger-color); color: white; }

        .shift-closed #sales-interface {
            filter: blur(4px);
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
            padding: 16px;
        }

        #start-shift-modal {
            background: white;
            padding: 26px;
            border-radius: 16px;
            box-shadow: 0 14px 30px rgba(0,0,0,0.25);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }

        #start-shift-modal h2 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        #start-shift-modal p {
            color: var(--muted);
            margin: 0 0 14px;
            font-size: 0.95rem;
        }

        #start-shift-modal .form-group {
            margin: 12px 0 16px;
            text-align: left;
        }

        #start-shift-modal label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: 700;
        }

        #start-shift-modal input {
            width: 100%;
            padding: 12px;
            font-size: 1.1rem;
            text-align: center;
            border: 1px solid #d1d5db;
            border-radius: 10px;
        }

        #start-shift-modal input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 87, 220, 0.16);
        }

        button {
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 700;
            padding: 11px 15px;
            border: 1px solid transparent;
        }

        button.primary { background-color: var(--primary-color); color: white; }
        button.primary:hover { background-color: var(--primary-dark); }
        button.secondary { background-color: var(--secondary-color); color: white; }

        @media (max-width: 768px) {
            #pos-container {
                padding: 8px;
                gap: 8px;
            }

            .topbar {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .topbar-actions {
                justify-self: stretch;
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }

            #sales-interface {
                flex-direction: column;
                min-height: 0;
            }

            .products-panel {
                flex: 2;
                min-height: 0;
            }

            #cart {
                flex: 1;
                min-height: 265px;
            }

            #product-grid {
                grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
                gap: 8px;
            }

            #cart-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        #toast-notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--success-color);
            color: white;
            padding: 12px 25px;
            border-radius: 12px;
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

        <header class="topbar">
            <div class="brand">
                <img src="img/logo.png" alt="Logo VentasCAF">
                <div>
                    <h1>Punto de Venta VentasCAF</h1>
                    <p>Operación rápida, clara y preparada para móvil.</p>
                </div>
            </div>
            <div class="topbar-stats">
                <span class="pill"><?php echo count($products); ?> productos activos</span>
                <span class="pill"><?php echo $stock_available_count; ?> con stock</span>
                <span class="pill"><?php echo $is_shift_open ? 'Turno activo' : 'Turno cerrado'; ?></span>
            </div>
            <div class="topbar-actions">
                <a href="admin.php" class="topbar-link">Administración</a>
                <a href="return.php" class="topbar-link secondary">Devoluciones</a>
            </div>
        </header>

        <main id="sales-interface">
            <section class="products-panel">
                <div class="panel-head">
                    <h2>Productos disponibles</h2>
                    <span>Toca un producto para agregarlo al carrito</span>
                </div>

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
            </section>

            <aside id="cart">
                <div class="cart-header">
                    <h2>Carrito</h2>
                    <small>Resumen en tiempo real</small>
                </div>
                <div id="cart-items">
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
                    <a href="admin.php" id="main-menu-btn" class="action-btn secondary">MENÚ</a>
                </div>
            </aside>
        </main>

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

    </div>

    <div id="toast-notification"></div>
    <script src="main.js"></script>
</body>
</html>