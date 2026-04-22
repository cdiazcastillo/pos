<?php
header('Content-Type: application/json');

require_once 'includes/auth.php';
auth_require_api_role(['cashier', 'admin']);

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// 1. Security & Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$sale_id = filter_input(INPUT_POST, 'sale_id', FILTER_VALIDATE_INT);


if (!$sale_id) {
    $response['message'] = 'Invalid Sale ID provided.';
    echo json_encode($response);
    exit;
}

$db = Database::getInstance();

try {
    // 2. Transactional Logic
    $db->beginTransaction();

    // 2a. Fetch and validate the sale
    $sale = $db->query("SELECT id, total_amount, shift_id, status, payment_method FROM sales WHERE id = ?", [$sale_id]);


    if (!$sale) {
        throw new Exception("Sale with ID #{$sale_id} not found.");
    }
    if ($sale['status'] === 'voided') {
        throw new Exception("Sale #{$sale_id} has already been voided.");
    }
    
    // 2b. Fetch sale items to determine remaining amounts and stock to restore
    $sale_items = $db->query(
        "SELECT id, product_id, quantity, quantity_returned, price_per_unit 
         FROM sale_items 
         WHERE sale_id = ?",
        [$sale_id],
        true
    );


    $total_already_returned_across_all_items = 0;
    if ($sale_items) {
        foreach ($sale_items as $item) {
            $total_already_returned_across_all_items += ($item['quantity_returned'] * $item['price_per_unit']);

            $returnable_quantity = $item['quantity'] - $item['quantity_returned'];

            if ($returnable_quantity > 0) {
                // Restore remaining stock
                $db->execute(
                    "UPDATE products SET stock_level = stock_level + ? WHERE id = ?",
                    [$returnable_quantity, $item['product_id']]
                );

                // Update sale_item to mark all as returned
                $db->execute(
                    "UPDATE sale_items SET quantity_returned = ? WHERE id = ?",
                    [$item['quantity'], $item['id']]
                );
            }
        }
    }
    // Correctly calculate net_void_amount by subtracting already returned amount
    $net_void_amount = $sale['total_amount'] - $total_already_returned_across_all_items;


    // 2c. Update the sale status to 'voided'
    $db->execute("UPDATE sales SET status = 'voided' WHERE id = ?", [$sale_id]);

    
    // 2d. Record an expense to balance the cash flow for the net void amount
    // Only record if there's an actual amount to void (i.e., not fully returned already)
    if ($net_void_amount > 0) {
        $expense_description = "Anulación Venta Completa #" . $sale_id;
        $hasExpensePaymentMethod = $db->query("SHOW COLUMNS FROM expenses LIKE 'payment_method'");
        if ($hasExpensePaymentMethod) {
            $db->execute(
                "INSERT INTO expenses (shift_id, sale_id, description, amount, payment_method) VALUES (?, ?, ?, ?, ?)",
                [$sale['shift_id'], $sale_id, $expense_description, $net_void_amount, $sale['payment_method'] ?? 'cash']
            );
        } else {
            $db->execute(
                "INSERT INTO expenses (shift_id, sale_id, description, amount) VALUES (?, ?, ?, ?)",
                [$sale['shift_id'], $sale_id, $expense_description, $net_void_amount]
            );
        }

    }

    // 3. Commit Transaction
    $db->commit();

    $response['success'] = true;
    $response['message'] = "Sale #{$sale_id} voided successfully. Net amount: $net_void_amount. Stock restored and expense recorded.";

} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    $response['message'] = 'A server error occurred: ' . $e->getMessage();
}

echo json_encode($response);