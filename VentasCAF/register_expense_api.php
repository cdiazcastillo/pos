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
$amount_raw = trim((string)($_POST['amount'] ?? ''));
$payment_method = trim((string)($_POST['payment_method'] ?? 'cash'));

if (empty(trim($description))) {
    $response['message'] = 'Expense description cannot be empty.';
    echo json_encode($response);
    exit;
}

if ($amount_raw === '' || !preg_match('/^\d+$/', $amount_raw) || intval($amount_raw) <= 0) {
    $response['message'] = 'Invalid expense amount. It must be a positive number.';
    echo json_encode($response);
    exit;
}

if (!in_array($payment_method, ['cash', 'transfer'], true)) {
    $response['message'] = 'Invalid payment method. Use cash or transfer.';
    echo json_encode($response);
    exit;
}

$amount = intval($amount_raw);
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
    $hasExpensePaymentMethod = $db->query("SHOW COLUMNS FROM expenses LIKE 'payment_method'");
    if (!$hasExpensePaymentMethod) {
        $db->execute("ALTER TABLE expenses ADD COLUMN payment_method ENUM('cash', 'transfer') NOT NULL DEFAULT 'cash' AFTER amount");
        $hasExpensePaymentMethod = $db->query("SHOW COLUMNS FROM expenses LIKE 'payment_method'");
    }

    if ($hasExpensePaymentMethod) {
        $sql = "INSERT INTO expenses (shift_id, description, amount, payment_method) VALUES (?, ?, ?, ?)";
        $affected_rows = $db->execute($sql, [$shift_id, trim($description), $amount, $payment_method]);
    } else {
        $sql = "INSERT INTO expenses (shift_id, description, amount) VALUES (?, ?, ?)";
        $descriptionWithMethod = '[' . strtoupper($payment_method === 'cash' ? 'EFECTIVO' : 'TRANSFERENCIA') . '] ' . trim($description);
        $affected_rows = $db->execute($sql, [$shift_id, $descriptionWithMethod, $amount]);
    }

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
