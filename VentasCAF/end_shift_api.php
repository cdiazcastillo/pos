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

$final_cash = $_POST['final_cash'] ?? null;

if ($final_cash === null || !is_numeric($final_cash) || floatval($final_cash) < 0) {
    $response['message'] = 'Invalid final cash amount provided. It must be a non-negative number.';
    echo json_encode($response);
    exit;
}

$final_cash = floatval($final_cash);
$user_id = $_SESSION['user_id'];

$db = Database::getInstance();

try {
    // 2. Business Logic in a Transaction
    $db->beginTransaction();

    // 2a. Find Active Shift
    $shift = $db->query("SELECT id, initial_cash FROM shifts WHERE user_id = ? AND status = 'open'", [$user_id]);
    if (!$shift) {
        throw new Exception('No active shift found to close.');
    }
    $shift_id = $shift['id'];
    $initial_cash = floatval($shift['initial_cash']);

    // 2b. Calculate Total Sales (sum of completed sales for the shift)
    $sales_result = $db->query(
        "SELECT SUM(total_amount) as total_sales FROM sales WHERE shift_id = ? AND status = 'completed'",
        [$shift_id]
    );
    $total_sales = floatval($sales_result['total_sales'] ?? 0);

    // 2c. Calculate Total Expenses (sum of all expenses for the shift, including returns)
    $expenses_result = $db->query(
        "SELECT SUM(amount) as total_expenses FROM expenses WHERE shift_id = ?",
        [$shift_id]
    );
    $total_expenses = floatval($expenses_result['total_expenses'] ?? 0);

    // 2d. Calculate Expected Cash
    $expected_cash = $initial_cash + $total_sales - $total_expenses;

    // 2e. Calculate Difference
    $difference = $final_cash - $expected_cash;

    // 2f. Update Shift Record
    $sql = "UPDATE shifts SET end_time = CURRENT_TIMESTAMP, final_cash = ?, status = 'closed' WHERE id = ?";
    $affected_rows = $db->execute($sql, [$final_cash, $shift_id]);

    if ($affected_rows === 0) {
        throw new Exception('Failed to update the shift record in the database.');
    }

    // Commit the transaction
    $db->commit();
    
    // 3. Success Response
    $response['success'] = true;
    $response['message'] = 'Shift closed successfully.';
    $response['data'] = [
        'shift_id' => $shift_id,
        'initial_cash' => $initial_cash,
        'total_sales' => $total_sales,
        'total_expenses' => $total_expenses,
        'expected_cash' => $expected_cash,
        'final_cash' => $final_cash,
        'difference' => $difference
    ];

} catch (Exception $e) {
    // If anything fails, roll back
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    $response['message'] = $e->getMessage();
}

// Final Response
echo json_encode($response);
