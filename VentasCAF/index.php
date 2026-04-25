<?php
require_once 'includes/auth.php';
$currentUser = auth_require_role(['cashier', 'admin'], 'vendedor_login.php', 'vendedor_login.php');

$db = Database::getInstance();
$conn = $db->getConnection();

// Check for selected active shift (shared for admin) or fallback to own shift
$active_shift = null;
$selectedShiftId = intval($_SESSION['selected_shift_id'] ?? 0);
if ($selectedShiftId > 0) {
    $active_shift = $db->query("SELECT id, user_id FROM shifts WHERE id = ? AND status = 'open'", [$selectedShiftId]);
    if ($active_shift && (($currentUser['role'] ?? '') !== 'admin') && intval($active_shift['user_id']) !== intval($_SESSION['user_id'])) {
        $active_shift = null;
        unset($_SESSION['selected_shift_id']);
    }
}

if (!$active_shift) {
    if (($currentUser['role'] ?? '') === 'admin') {
        $active_shift = $db->query("SELECT id, user_id FROM shifts WHERE status = 'open' ORDER BY start_time ASC LIMIT 1");
    } else {
        $active_shift = $db->query("SELECT id, user_id FROM shifts WHERE user_id = ? AND status = 'open'", [$_SESSION['user_id']]);
    }
    if ($active_shift) {
        $_SESSION['selected_shift_id'] = intval($active_shift['id']);
    }
}

$is_shift_open = is_array($active_shift) && isset($active_shift['id']);
$requires_shift_overlay = (!$is_shift_open) && (($currentUser['role'] ?? '') !== 'admin');

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
    <title>4 Básico A - Punto de Venta</title>
    <base href="<?php echo htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="styles.css">
    <link rel="apple-touch-icon" href="img/logo.png">
    <style>
        :root {
            --primary: #7c3aed;
            --primary-light: #8b5cf6;
            --bg-app: #f3f0ff;
            --bg-card: #ffffff;
            --bg-surface: #faf8ff;
            --text-dark: #1e1b4b;
            --text-muted: #6b7280;
            --success: #14b8a6;
            --danger: #fb7185;
            --warning: #f97316;
            --shadow-card: 0 0.5rem 1.5rem rgba(30,27,75,0.10);
            --border-subtle: 0.06rem solid rgba(124,58,237,0.12);
            --radius-card: 1.4rem;
            --cart-row-height: 28px;
            --cart-row-gap: 2px;
            --success-color: var(--success);
            --danger-color: var(--danger);
            --font-family: -apple-system, BlinkMacSystemFont,
                "Poppins", "Inter", "Segoe UI", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            overflow: hidden;
            background: var(--bg-app);
            font-family: var(--font-family);
            color: var(--text-dark);
        }

        #pos-container {
            height: calc(100vh - (var(--nav-height, 3.8rem) + env(safe-area-inset-top, 0)));
            height: calc(100dvh - (var(--nav-height, 3.8rem) + env(safe-area-inset-top, 0)));
            display: flex;
            flex-direction: column;
            padding: 8px;
            gap: 8px;
            box-sizing: border-box;
            padding-top: 8px;
            min-height: 0;
        }


        .products-panel::before {
            content: '';
            position: absolute;
            width: 210px;
            height: 210px;
            right: -36px;
            top: -34px;
            background: url('img/logo.png') center/contain no-repeat;
            opacity: 0.08;
            pointer-events: none;
            filter: grayscale(0.1);
            z-index: 0;
        }

        .products-panel {
            position: relative;
        }

        #product-grid {
            position: relative;
            z-index: 1;
        }

        #sales-interface {
            flex: 1;
            display: flex;
            gap: 8px;
            min-height: 0;
        }

        .products-panel {
            flex: 2.9;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: var(--border-subtle);
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }

        #product-grid {
            flex: 1;
            padding: 10px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            align-content: start;
            overflow-y: auto;
            min-height: 0;
            -webkit-overflow-scrolling: touch;
            touch-action: pan-y;
            overscroll-behavior-y: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(124,58,237,0.3) transparent;
        }

        @media (min-width: 768px) {
            #product-grid {
                grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
            }
        }

        #cart {
            flex: 1.1;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: var(--border-subtle);
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            padding: 12px;
            gap: 6px;
            min-height: 0;
            overflow: hidden;
        }

        #cart-items {
            flex: 0 0 auto;
            height: calc((var(--cart-row-height) * 4) +
                         (var(--cart-row-gap)    * 3));
            max-height: calc((var(--cart-row-height) * 4) +
                             (var(--cart-row-gap)    * 3));
            overflow-y: auto;
            min-height: 0;
            display: grid;
            gap: var(--cart-row-gap);
            align-content: start;
            -webkit-overflow-scrolling: touch;
            touch-action: pan-y;
            overscroll-behavior-y: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(124,58,237,0.3) transparent;
        }

        #cart-summary {
            flex-shrink: 0;
            border-top: var(--border-subtle);
            padding-top: 6px;
            background: var(--bg-surface);
            border: var(--border-subtle);
            border-radius: 0.9rem;
            padding: 6px 8px;
        }

        .summary-row.total {
            font-weight: 800;
            font-size: 1.22rem;
            color: var(--primary);
            border-top: 2px solid rgba(124,58,237,0.2);
            border-radius: 0.7rem;
            padding: 6px 8px 4px;
            background: rgba(124,58,237,0.08);
            box-shadow: inset 0 0 0 0.06rem rgba(124,58,237,0.12);
        }

        #cart-actions {
            flex-shrink: 0;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
            padding-top: 6px;
            border-top: var(--border-subtle);
            background: var(--bg-card);
            margin-top: 0;
            position: relative;
            z-index: 2;
            visibility: visible;
        }

        .action-btn {
            padding: 0.65rem 0.5rem;
            border: none;
            border-radius: 999rem;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 800;
            text-align: center;
            min-height: 2.75rem;
            min-width: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: normal;
            line-height: 1.05;
            visibility: visible;
            opacity: 1;
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .action-btn:active { transform: scale(0.97); opacity: 0.88; }
        .action-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: #fff;
        }

        .action-btn.success {
            background: var(--success);
            color: #fff;
        }

        .action-btn.danger {
            background: var(--danger-bg, rgba(251,113,133,0.15));
            color: #9f1239;
        }

        .action-btn.secondary {
            background: var(--text-muted);
            color: #fff;
        }

        .product-card {
            border: var(--border-subtle);
            border-radius: 1rem;
            background: var(--bg-surface);
            box-shadow: 0 0.25rem 0.75rem rgba(30,27,75,0.07);
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            min-height: 100px;
            padding: 10px 8px 28px;
            position: relative;
            overflow: hidden;
            color: var(--text-dark);
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card);
            border-color: rgba(124,58,237,0.3);
        }

        .product-name {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--text-dark);
        }

        .product-price {
            font-weight: 800;
            font-size: 0.9rem;
            color: var(--primary);
        }

        .product-info {
            z-index: 2;
            display: grid;
            gap: 6px;
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
            background-color: rgba(124, 58, 237, 0.72);
            color: #ffffff;
        }

        .product-card.stock-low .stock-indicator {
            background-color: rgba(249, 115, 22, 0.76);
            color: #111827;
        }

        .product-card.stock-empty .stock-indicator {
            cursor: not-allowed;
            background-color: rgba(251, 113, 133, 0.78);
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
            display: none;
        }

        #cart-items:empty {
            display: none;
            height: 0;
            max-height: 0;
            overflow: hidden;
        }

        #product-grid::-webkit-scrollbar,
        #cart-items::-webkit-scrollbar {
            width: 8px;
        }

        #product-grid::-webkit-scrollbar-track,
        #cart-items::-webkit-scrollbar-track {
            background: transparent;
            border-radius: 999px;
        }

        #product-grid::-webkit-scrollbar-thumb,
        #cart-items::-webkit-scrollbar-thumb {
            background: rgba(124,58,237,0.3);
            border-radius: 999px;
        }

        #product-grid::-webkit-scrollbar-thumb:hover,
        #cart-items::-webkit-scrollbar-thumb:hover {
            background: rgba(124,58,237,0.5);
        }

        .cart-item {
            display: flex;
            justify-content: flex-start;
            gap: 3px;
            padding: 2px 5px;
            align-items: center;
            border-radius: 6px;
            background-color: var(--bg-surface);
            border: var(--border-subtle);
            min-height: var(--cart-row-height);
            font-size: 13px;
        }

        .cart-item-name {
            flex-grow: 1;
            font-size: 0.925rem;
            font-weight: 600;
            line-height: 1.12;
        }

        .cart-item-qty {
            margin: 0 2px;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.925rem;
        }

        .cart-item-price {
            font-weight: 700;
            font-size: 0.925rem;
        }

        .remove-item-btn {
            cursor: pointer;
            color: var(--danger);
            background: none;
            border: none;
            font-size: 0.8rem;
            line-height: 1;
            width: 16px;
            height: 16px;
            padding: 0;
        }

        .cart-sticky-summary {
            position: static;
            background: var(--bg-card);
            padding-top: 0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

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
            background: var(--bg-card);
            padding: 26px;
            border-radius: var(--radius-card);
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
            color: var(--text-muted);
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
            color: var(--text-muted);
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
            border-color: var(--primary);
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

        button.primary { background-color: var(--primary); color: white; }
        button.primary:hover { background-color: var(--primary-light); }
        button.secondary { background-color: var(--text-muted); color: white; }

        @media (max-width: 768px) {
            #pos-container {
                padding: 4px;
                padding-top: 4px;
            }

            #sales-interface {
                flex-direction: column;
                gap: 6px;
            }

            .products-panel {
                flex: 1;
                min-height: 0;
            }

            #product-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            #cart {
                flex: 0 0 auto;
                max-height: none;
                height: auto;
            }

            #cart-actions {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 4px;
                margin-top: 0;
                flex-shrink: 0;
            }

            .action-btn {
                font-size: 0.66rem;
                padding: 0.55rem 0.3rem;
            }
        }

        #toast-notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.96);
            background-color: rgba(30, 27, 75, 0.92);
            color: white;
            padding: 0.8rem 1.1rem;
            border-radius: 12px;
            box-shadow: 0 0.75rem 2rem rgba(15, 23, 42, 0.28);
            z-index: 3000;
            min-width: 14rem;
            max-width: min(90vw, 26rem);
            text-align: center;
            font-weight: 700;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.22s ease, visibility 0.22s ease, transform 0.22s ease;
        }

        #toast-notification.show {
            visibility: visible;
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
    </style>
</head>
<body ontouchstart="">
    <?php $activePage = 'pos'; include 'top-nav.php'; ?>
    <div id="pos-container" class="<?php echo $requires_shift_overlay ? 'shift-closed' : ''; ?>">
        <main id="sales-interface">
            <section class="products-panel">
                <div id="product-grid">
                    <?php foreach ($products as $product): ?>
                        <?php $stock_class = get_stock_semaphore_class($product); ?>
                        <div class="product-card <?php echo $stock_class; ?>"
                             data-id="<?php echo $product['id']; ?>"
                             data-name="<?php echo htmlspecialchars($product['name']); ?>"
                             data-price="<?php echo $product['price']; ?>"
                                data-stock="<?php echo intval($product['stock_level']); ?>"
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
                </div>
                <div id="cart-items"></div>
                <div id="cart-summary" class="cart-sticky-summary">
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="cart-total">$0</span>
                    </div>
                </div>
                <div id="cart-actions">
                    <button id="cash-payment-btn" class="action-btn primary">EFECTIVO</button>
                    <button id="transfer-payment-btn" class="action-btn success">TRANSFERENCIA</button>
                    <button id="clear-cart-btn" class="action-btn danger">LIMPIAR</button>
                </div>
            </aside>
        </main>

        <?php if ($requires_shift_overlay): ?>
        <div id="shift-overlay">
            <div id="start-shift-modal">
                <h2>Iniciar Turno</h2>
                <p>No hay un turno activo. Ingresa el monto inicial en caja para comenzar a vender.</p>
                <form id="start-shift-form">
                    <div class="form-group">
                        <label for="initial_cash">Efectivo Inicial:</label>
                        <input type="text" id="initial_cash" name="initial_cash" inputmode="numeric" pattern="[0-9]*" required>
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