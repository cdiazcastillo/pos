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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$is_active = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT);

if ($id === false || ($is_active !== 0 && $is_active !== 1)) {
    $response['message'] = 'Invalid input. A valid product ID and status (0 or 1) are required.';
    echo json_encode($response);
    exit;
}

// --- Database Operation ---
try {
    $db = Database::getInstance();
    $sql = "UPDATE products SET is_active = ? WHERE id = ?";
    
    $db->execute($sql, [$is_active, $id]);

    $response['success'] = true;
    $response['message'] = 'Product status updated successfully.';

} catch (Exception $e) {
    error_log("Toggle product status error: " . $e->getMessage());
    $response['message'] = 'A server error occurred while updating the product status.';
}

echo json_encode($response);
