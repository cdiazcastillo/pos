<?php
header('Content-Type: application/json');
session_start();
require_once 'config/db.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

$shift_id = $_GET['shift_id'] ?? null;

if (!$shift_id) {
    $response['message'] = 'Shift ID is required.';
    echo json_encode($response);
    exit;
}

$db = Database::getInstance();

try {
    // 1. Fetch Shift Details
    $shift = $db->query("SELECT s.*, u.username FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.id = ?", [$shift_id]);
    if (!$shift) {
        throw new Exception('Shift not found.');
    }

    $initial_cash = floatval($shift['initial_cash']);
    $final_cash = floatval($shift['final_cash']);

    // 2. Calculate Total Sales
    $sales_result = $db->query(
        "SELECT SUM(total_amount) as total_sales FROM sales WHERE shift_id = ? AND status = 'completed'",
        [$shift_id]
    );
    $total_sales = floatval($sales_result['total_sales'] ?? 0);

    // 3. Calculate Total Expenses
    $expenses_result = $db->query(
        "SELECT SUM(amount) as total_expenses FROM expenses WHERE shift_id = ?",
        [$shift_id]
    );
    $total_expenses = floatval($expenses_result['total_expenses'] ?? 0);

    // 4. Calculate Expected Cash
    $expected_cash = $initial_cash + $total_sales - $total_expenses;

    // 5. Calculate Difference
    $difference = $final_cash - $expected_cash;

    // 6. Success Response
    $response['success'] = true;
    $response['data'] = [
        'shift_id' => $shift['id'],
        'username' => $shift['username'],
        'start_time' => $shift['start_time'],
        'end_time' => $shift['end_time'],
        'status' => $shift['status'],
        'initial_cash' => $initial_cash,
        'total_sales' => $total_sales,
        'total_expenses' => $total_expenses,
        'expected_cash' => $expected_cash,
        'final_cash' => $final_cash,
        'difference' => $difference
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
