<?php
header('Content-Type: application/json');

session_start();
require_once 'config/db.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// 1. Authentication & Input Validation
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

$description = $_POST['description'] ?? '';
$amount = $_POST['amount'] ?? '';

if (empty(trim($description))) {
    $response['message'] = 'Expense description cannot be empty.';
    echo json_encode($response);
    exit;
}

if (!is_numeric($amount) || floatval($amount) <= 0) {
    $response['message'] = 'Invalid expense amount. It must be a positive number.';
    echo json_encode($response);
    exit;
}

$amount = floatval($amount);
$user_id = $_SESSION['user_id'];

try {
    $db = Database::getInstance();

    // 2. Business Logic: Find active shift
    $shift_result = $db->query("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'", [$user_id]);

    if (!$shift_result) {
        $response['message'] = 'No active shift found. Cannot register an expense.';
        echo json_encode($response);
        exit;
    }
    $shift_id = $shift_result['id'];

    // 3. Business Logic: Insert the expense
    $sql = "INSERT INTO expenses (shift_id, description, amount) VALUES (?, ?, ?)";
    $affected_rows = $db->execute($sql, [$shift_id, trim($description), $amount]);

    if ($affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Expense registered successfully.';
    } else {
        $response['message'] = 'Failed to register the expense in the database.';
    }

} catch (Exception $e) {
    error_log("Expense registration error: " . $e->getMessage());
    $response['message'] = 'A server error occurred. Please try again later.';
}

// 4. Final Response
echo json_encode($response);
