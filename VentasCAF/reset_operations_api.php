<?php
header('Content-Type: application/json');

session_start();
require_once 'config/db.php';

$response = ['success' => false, 'message' => 'No se pudo ejecutar el reinicio operativo.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Autenticación requerida.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método de solicitud inválido.';
    echo json_encode($response);
    exit;
}

$security_key = trim($_POST['security_key'] ?? '');
$initial_cash_input = $_POST['initial_cash'] ?? null;
$clear_products = isset($_POST['clear_products']) && $_POST['clear_products'] === '1';

if ($security_key !== '250012') {
    $response['message'] = 'Clave de seguridad inválida.';
    echo json_encode($response);
    exit;
}

$initial_cash = null;
if ($initial_cash_input !== null && $initial_cash_input !== '') {
    $initial_cash_raw = trim((string)$initial_cash_input);
    if (!preg_match('/^\d+$/', $initial_cash_raw)) {
        $response['message'] = 'El efectivo inicial del nuevo turno es inválido.';
        echo json_encode($response);
        exit;
    }
    $initial_cash = intval($initial_cash_raw);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $db->beginTransaction();

    $user = $db->query('SELECT id, role FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        throw new Exception('Solo usuarios administradores pueden ejecutar este reinicio.');
    }

    $open_shifts_result = $conn->query("SELECT COUNT(*) as total FROM shifts WHERE status = 'open'")->fetch(PDO::FETCH_ASSOC);
    $open_shifts_count = intval($open_shifts_result['total'] ?? 0);

    $restoreStockStmt = $conn->prepare(
        "UPDATE products p
         JOIN (
             SELECT si.product_id,
                    SUM(CASE WHEN s.status = 'completed' THEN (si.quantity - si.quantity_returned) ELSE 0 END) AS qty_to_restore
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             GROUP BY si.product_id
         ) r ON p.id = r.product_id
         SET p.stock_level = p.stock_level + r.qty_to_restore"
    );
    $restoreStockStmt->execute();

    $closeShiftsStmt = $conn->prepare(
        "UPDATE shifts
         SET status = 'closed',
             end_time = COALESCE(end_time, CURRENT_TIMESTAMP),
             final_cash = COALESCE(final_cash, initial_cash)
         WHERE status = 'open'"
    );
    $closeShiftsStmt->execute();

    $saleItemsCount = $conn->query('SELECT COUNT(*) as total FROM sale_items')->fetch(PDO::FETCH_ASSOC);
    $salesCount = $conn->query('SELECT COUNT(*) as total FROM sales')->fetch(PDO::FETCH_ASSOC);
    $expensesCount = $conn->query('SELECT COUNT(*) as total FROM expenses')->fetch(PDO::FETCH_ASSOC);

    $conn->exec('DELETE FROM expenses');
    $conn->exec('DELETE FROM sale_items');
    $conn->exec('DELETE FROM sales');

    $conn->exec('ALTER TABLE expenses AUTO_INCREMENT = 1');
    $conn->exec('ALTER TABLE sale_items AUTO_INCREMENT = 1');
    $conn->exec('ALTER TABLE sales AUTO_INCREMENT = 1');

    $deleted_products = 0;
    if ($clear_products) {
        $productsCount = $conn->query('SELECT COUNT(*) as total FROM products')->fetch(PDO::FETCH_ASSOC);
        $deleted_products = intval($productsCount['total'] ?? 0);
        $conn->exec('DELETE FROM products');
        $conn->exec('ALTER TABLE products AUTO_INCREMENT = 1');
    }

    $new_shift_id = null;
    if ($initial_cash !== null) {
        $newShiftStmt = $conn->prepare("INSERT INTO shifts (user_id, initial_cash, status) VALUES (?, ?, 'open')");
        $newShiftStmt->execute([$_SESSION['user_id'], $initial_cash]);
        $new_shift_id = $conn->lastInsertId();
    }

    $db->commit();

    $response['success'] = true;
    $response['message'] = 'Reinicio operativo ejecutado correctamente.';
    $response['data'] = [
        'closed_open_shifts' => $open_shifts_count,
        'deleted_sale_items' => intval($saleItemsCount['total'] ?? 0),
        'deleted_sales' => intval($salesCount['total'] ?? 0),
        'deleted_expenses' => intval($expensesCount['total'] ?? 0),
        'deleted_products' => $deleted_products,
        'new_shift_id' => $new_shift_id
    ];

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $db->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
