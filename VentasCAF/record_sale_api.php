<?php
header('Content-Type: application/json');

session_start();
require_once 'config/db.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// 1. Setup and Validation
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

$data = json_decode(file_get_contents('php://input'), true);
$cart = $data['cart'] ?? null;
$payment_method = $data['payment_method'] ?? 'cash'; // Default to cash if not provided

if (empty($cart) || !is_array($cart)) {
    $response['message'] = 'Cart data is empty or invalid.';
    echo json_encode($response);
    exit;
}

if (!in_array($payment_method, ['cash', 'transfer'])) {
    $response['message'] = 'Invalid payment method provided.';
    echo json_encode($response);
    exit;
}

$db = Database::getInstance();

try {
    // 2. Business Logic in a Transaction
    $db->beginTransaction();

    // 2a. Check for active shift (supports selected shared shift for admin)
    $user = $db->query("SELECT id, role FROM users WHERE id = ?", [$_SESSION['user_id']]);
    $isAdmin = (($user['role'] ?? '') === 'admin');
    $selected_shift_id = intval($_SESSION['selected_shift_id'] ?? 0);

    $shift_id_result = null;
    if ($selected_shift_id > 0) {
        $shift_id_result = $db->query("SELECT id, user_id FROM shifts WHERE id = ? AND status = 'open'", [$selected_shift_id]);
        if ($shift_id_result && !$isAdmin && intval($shift_id_result['user_id']) !== intval($_SESSION['user_id'])) {
            $shift_id_result = null;
        }
    }

    if (!$shift_id_result) {
        $shift_id_result = $db->query("SELECT id, user_id FROM shifts WHERE user_id = ? AND status = 'open'", [$_SESSION['user_id']]);
        if ($shift_id_result) {
            $_SESSION['selected_shift_id'] = intval($shift_id_result['id']);
        }
    }

    if (!$shift_id_result) {
        throw new Exception('No active shift found. Please start a shift to record sales.');
    }
    $shift_id = intval($shift_id_result['id']);

    // 2b. Verify product stock and calculate total (Server-side)
    $server_total = 0;
    $product_ids = array_map(fn($item) => $item['id'], $cart);
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    
    $sql = "SELECT id, price, stock_level FROM products WHERE id IN ($placeholders)";
    $db_products = $db->query($sql, $product_ids, true);
    
    // Create a map for easy lookup
    $product_map = [];
    foreach($db_products as $p) {
        $product_map[$p['id']] = $p;
    }

    foreach ($cart as $item) {
        $product_id = $item['id'];
        $quantity = $item['quantity'];

        if (!isset($product_map[$product_id])) {
            throw new Exception("Product with ID {$product_id} not found.");
        }
        
        $db_product = $product_map[$product_id];

        if ($db_product['stock_level'] < $quantity) {
            throw new Exception("Not enough stock for product ID {$product_id}. Available: {$db_product['stock_level']}, Requested: {$quantity}.");
        }
        // Use server-side price for total calculation
        $server_total += $db_product['price'] * $quantity;
    }
    
    // Total is calculated server-side
    $final_total = $server_total;

    // 2c. Insert into sales table
    $sale_sql = "INSERT INTO sales (shift_id, total_amount, payment_method) VALUES (?, ?, ?)";
    $db->execute($sale_sql, [$shift_id, $final_total, $payment_method]);
    $sale_id = $db->lastInsertId();

    // 2d. Insert into sale_items and Update product stock
    $sale_item_sql = "INSERT INTO sale_items (sale_id, product_id, quantity, price_per_unit) VALUES (?, ?, ?, ?)";
    $update_stock_sql = "UPDATE products SET stock_level = stock_level - ? WHERE id = ?";
    
    $updated_stock_levels = [];

    foreach ($cart as $item) {
        $product_id = $item['id'];
        $quantity = $item['quantity'];
        $price_per_unit = $product_map[$product_id]['price']; // Use server-side price

        // Insert sale item
        $db->execute($sale_item_sql, [$sale_id, $product_id, $quantity, $price_per_unit]);
        
        // Update stock
        $db->execute($update_stock_sql, [$quantity, $product_id]);
        
        $updated_stock_levels[] = [
            'id' => $product_id,
            'new_stock' => $product_map[$product_id]['stock_level'] - $quantity
        ];
    }

    // If all went well, commit the transaction
    $db->commit();
    
    $response['success'] = true;
    $response['message'] = 'Sale recorded successfully.';
    $response['sale_id'] = $sale_id;
    $response['updated_stock'] = $updated_stock_levels;

} catch (Exception $e) {
    // If anything fails, roll back the transaction
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    $response['message'] = $e->getMessage();
}

// 3. Final Response
echo json_encode($response);
