<?php
header('Content-Type: application/json');

require_once 'includes/auth.php';
auth_require_api_role(['cashier', 'admin']);

// Define the master key for overrides. In a real app, this should be in a config file.
define('MASTER_KEY', '1234');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// 1. Authentication & Input Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$sale_id = filter_input(INPUT_POST, 'sale_id', FILTER_VALIDATE_INT);
$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
$master_key = $_POST['master_key'] ?? null;

if (!$sale_id || !$product_id || !$quantity || $quantity <= 0) {
    $response['message'] = 'Invalid input: sale_id, product_id, and quantity are required and must be positive integers.';
    echo json_encode($response);
    exit;
}

$db = Database::getInstance();

try {
    // 2. Business Logic in a Transaction
    $db->beginTransaction();

    // 2a. Find Active Shift
    $shift = $db->query("SELECT id, initial_cash FROM shifts WHERE user_id = ? AND status = 'open'", [$_SESSION['user_id']]);
    if (!$shift) {
        throw new Exception('No active shift found. Returns must be processed during an active shift.');
    }
    $shift_id = $shift['id'];
    $initial_cash = floatval($shift['initial_cash']);

    $sale = $db->query("SELECT payment_method FROM sales WHERE id = ?", [$sale_id]);
    if (!$sale) {
        throw new Exception("Sale #{$sale_id} not found.");
    }

    // 2b. Verify Original Sale Item
    $sale_item = $db->query(
        "SELECT quantity, price_per_unit FROM sale_items WHERE sale_id = ? AND product_id = ?",
        [$sale_id, $product_id]
    );

    if (!$sale_item || $sale_item['quantity'] < $quantity) {
        throw new Exception("Original sale record not found or return quantity ({$quantity}) exceeds purchased quantity ({$sale_item['quantity']}).");
    }
    $price_per_unit = floatval($sale_item['price_per_unit']);
    
    // The total amount to be refunded
    $total_refund_amount = $price_per_unit * $quantity;

    // 2c. Check Cash Balance
    $sales_result = $db->query("SELECT SUM(total_amount) as total FROM sales WHERE shift_id = ?", [$shift_id]);
    $expenses_result = $db->query("SELECT SUM(amount) as total FROM expenses WHERE shift_id = ?", [$shift_id]);
    
    $total_sales = floatval($sales_result['total'] ?? 0);
    $total_expenses = floatval($expenses_result['total'] ?? 0);
    
    $current_cash_balance = $initial_cash + $total_sales - $total_expenses;
    
    if ($current_cash_balance < $total_refund_amount) {
        if ($master_key === null) {
            throw new Exception("Insufficient cash in drawer for refund ({$total_refund_amount}). Master key required.");
        }
        if ($master_key !== MASTER_KEY) {
            throw new Exception("Invalid master key provided.");
        }
        // If key is valid, we allow the balance to go negative, logging this event implicitly.
    }
    
    // 2d. Create a negative expense (a refund)
    $product_name = $db->query("SELECT name FROM products WHERE id = ?", [$product_id])['name'] ?? 'Unknown Product';
    $refund_description = "Return: {$quantity}x {$product_name} (from Sale #{$sale_id})";
    
    // We register the refund as a positive expense to simplify accounting.
    // This correctly subtracts from the cash balance.
    $hasExpensePaymentMethod = $db->query("SHOW COLUMNS FROM expenses LIKE 'payment_method'");
    if ($hasExpensePaymentMethod) {
        $expense_sql = "INSERT INTO expenses (shift_id, description, amount, payment_method) VALUES (?, ?, ?, ?)";
        $db->execute($expense_sql, [$shift_id, $refund_description, $total_refund_amount, $sale['payment_method'] ?? 'cash']);
    } else {
        $expense_sql = "INSERT INTO expenses (shift_id, description, amount) VALUES (?, ?, ?)";
        $db->execute($expense_sql, [$shift_id, $refund_description, $total_refund_amount]);
    }
    
    // 2e. Update Stock Level
    $stock_sql = "UPDATE products SET stock_level = stock_level + ? WHERE id = ?";
    $db->execute($stock_sql, [$quantity, $product_id]);

    // Commit the transaction
    $db->commit();
    
    $response['success'] = true;
    $response['message'] = "Return processed successfully. Amount: \${$total_refund_amount}.";

} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    $response['message'] = $e->getMessage();
}

// Final Response
echo json_encode($response);
