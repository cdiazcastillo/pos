<?php
header('Content-Type: application/json');

session_start();
require_once 'config/db.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// --- Security & Validation ---
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}
// In a real app, you would also check if the user has an 'admin' role.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$name = trim($_POST['name'] ?? '');
$price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_INT);
$stock_level = filter_input(INPUT_POST, 'stock_level', FILTER_VALIDATE_INT);
$min_stock_warning = filter_input(INPUT_POST, 'min_stock_warning', FILTER_VALIDATE_INT);

if (!$id || empty($name) || $price === false || $stock_level === false || $min_stock_warning === false) {
    $response['message'] = 'Invalid input. Please provide all fields correctly, including a valid product ID.';
    echo json_encode($response);
    exit;
}

// --- Database Operation ---
try {
    $db = Database::getInstance();
    $sql = "UPDATE products SET name = ?, price = ?, stock_level = ?, min_stock_warning = ? WHERE id = ?";
    
    $affected_rows = $db->execute($sql, [$name, $price, $stock_level, $min_stock_warning, $id]);

    // rowCount() can return 0 if no data was changed, which is not an error.
    // So, we don't strictly check for > 0. The absence of an exception is success.
    $response['success'] = true;
    $response['message'] = 'Product updated successfully.';

} catch (Exception $e) {
    if ($e->getCode() == 23000) {
        $response['message'] = "Error: A product with the name '{$name}' already exists.";
    } else {
        error_log("Update product error: " . $e->getMessage());
        $response['message'] = 'A server error occurred.';
    }
}

echo json_encode($response);
