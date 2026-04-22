<?php
header('Content-Type: application/json');

require_once 'includes/auth.php';
auth_require_api_role(['cashier', 'admin']);

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// --- Security & Validation ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$name = trim($_POST['name'] ?? '');
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_INT);
$stock_level = filter_input(INPUT_POST, 'stock_level', FILTER_VALIDATE_INT);
$min_stock_warning = filter_input(INPUT_POST, 'min_stock_warning', FILTER_VALIDATE_INT);

if (empty($name) || $price === false || $stock_level === false || $min_stock_warning === false) {
    $response['message'] = 'Invalid input. Please provide all fields correctly.';
    echo json_encode($response);
    exit;
}

// --- Database Operation ---
try {
    $db = Database::getInstance();
    $sql = "INSERT INTO products (name, price, stock_level, min_stock_warning) VALUES (?, ?, ?, ?)";
    
    $affected_rows = $db->execute($sql, [$name, $price, $stock_level, $min_stock_warning]);

    if ($affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Product added successfully.';
        $response['product_id'] = $db->lastInsertId();
    } else {
        $response['message'] = 'Failed to add product to the database.';
    }

} catch (Exception $e) {
    // Check for duplicate entry
    if ($e->getCode() == 23000) { // Integrity constraint violation
        $response['message'] = "Error: A product with the name '{$name}' already exists.";
    } else {
        error_log("Add product error: " . $e->getMessage());
        $response['message'] = 'A server error occurred.';
    }
}

echo json_encode($response);
