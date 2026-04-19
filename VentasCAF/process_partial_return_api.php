<?php
header('Content-Type: application/json');

session_start();
require_once 'config/db.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// 1. Security & Validation
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$sale_id = filter_input(INPUT_POST, 'sale_id', FILTER_VALIDATE_INT);
$items_to_return = $_POST['items'] ?? [];

if (!$sale_id || empty($items_to_return) || !is_array($items_to_return)) {
    $response['message'] = 'Invalid input. A sale ID and items to return are required.';
    echo json_encode($response);
    exit;
}

$db = Database::getInstance();

try {
    // 2. Transactional Logic
    $db->beginTransaction();

    // 2a. Fetch original sale to get the shift_id
    $sale = $db->query("SELECT shift_id FROM sales WHERE id = ?", [$sale_id]);
    if (!$sale) {
        throw new Exception("Sale #{$sale_id} not found.");
    }
    $shift_id = $sale['shift_id'];
    
    $total_refund_amount = 0;
    $returned_items_log = [];

    // 2b. Loop through submitted items
    foreach ($items_to_return as $sale_item_id => $quantity_to_return) {
        $sale_item_id = intval($sale_item_id);
        $quantity_to_return = intval($quantity_to_return);

        if ($quantity_to_return <= 0) {
            continue; // Skip items with no return quantity
        }

        // 2c. Fetch the original sale item for validation
        $original_item = $db->query(
            "SELECT product_id, quantity, quantity_returned, price_per_unit FROM sale_items WHERE id = ? AND sale_id = ?",
            [$sale_item_id, $sale_id]
        );

        if (!$original_item) {
            throw new Exception("Item ID #{$sale_item_id} does not belong to Sale #{$sale_id}.");
        }

        $max_returnable = $original_item['quantity'] - $original_item['quantity_returned'];
        if ($quantity_to_return > $max_returnable) {
            throw new Exception("Cannot return {$quantity_to_return} units of product ID {$original_item['product_id']}. Only {$max_returnable} are returnable.");
        }

        // 2d. Update product stock
        $db->execute(
            "UPDATE products SET stock_level = stock_level + ? WHERE id = ?",
            [$quantity_to_return, $original_item['product_id']]
        );

        // 2e. Update the sale_item to reflect the return
        $db->execute(
            "UPDATE sale_items SET quantity_returned = quantity_returned + ? WHERE id = ?",
            [$quantity_to_return, $sale_item_id]
        );

        // 2f. Add to total refund amount
        $total_refund_amount += $quantity_to_return * $original_item['price_per_unit'];
        $returned_items_log[] = "Product ID {$original_item['product_id']}, Qty: {$quantity_to_return}";
    }

    if ($total_refund_amount <= 0) {
        throw new Exception("No items were selected for return.");
    }

    // 2g. Record an expense for the refund
    $expense_description = "Devolución Parcial Venta #" . $sale_id;
    $db->execute(
        "INSERT INTO expenses (shift_id, sale_id, description, amount) VALUES (?, ?, ?, ?)",
        [$shift_id, $sale_id, $expense_description, $total_refund_amount]
    );

    // 2h. Check if all items for this sale are now returned, and if so, void the sale
    $remaining_items = $db->query(
        "SELECT COUNT(*) as count FROM sale_items WHERE sale_id = ? AND quantity > quantity_returned",
        [$sale_id]
    );

    if ($remaining_items && $remaining_items['count'] == 0) {
        $db->execute("UPDATE sales SET status = 'voided' WHERE id = ?", [$sale_id]);
    }

    // 3. Commit Transaction
    $db->commit();

    $response['success'] = true;
    $response['message'] = 'Return processed successfully.';

} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
