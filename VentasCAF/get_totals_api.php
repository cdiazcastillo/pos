<?php
header('Content-Type: application/json');
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    $response = ['success' => false, 'message' => 'Authentication required.'];
    echo json_encode($response);
    exit;
}

$db = Database::getInstance();
$response = ['success' => false, 'message' => 'Could not retrieve totals.'];

try {
    // Gross Sales Data (all completed sales)
    $sales_summary_query = "SELECT SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) AS cash_sales,
                                   SUM(CASE WHEN payment_method = 'transfer' THEN total_amount ELSE 0 END) AS transfer_sales
                            FROM sales
                            WHERE status = 'completed'";
    $sales_summary = $db->query($sales_summary_query);
    $gross_cash_sales_completed = $sales_summary['cash_sales'] ?? 0;
    $gross_transfer_sales_completed = $sales_summary['transfer_sales'] ?? 0;

    // Returns Data (from expenses linked to sales that are STILL completed)
    $returns_summary_query = "SELECT SUM(CASE WHEN s.payment_method = 'cash' THEN e.amount ELSE 0 END) AS cash_returns,
                                     SUM(CASE WHEN s.payment_method = 'transfer' THEN e.amount ELSE 0 END) AS transfer_returns
                              FROM expenses e
                              JOIN sales s ON e.sale_id = s.id
                              WHERE e.sale_id IS NOT NULL AND s.status = 'completed'";
    $returns_summary = $db->query($returns_summary_query);
    $net_returns_on_completed_sales_cash = $returns_summary['cash_returns'] ?? 0;
    $net_returns_on_completed_sales_transfer = $returns_summary['transfer_returns'] ?? 0;

    // Other Expenses (not linked to sales)
    $other_expenses_query_sql = "SELECT SUM(amount) AS total_other_expenses
                                 FROM expenses
                                 WHERE sale_id IS NULL";
    $other_expenses_query = $db->query($other_expenses_query_sql);
    $other_expenses_amount = $other_expenses_query['total_other_expenses'] ?? 0;
    
    // Sum of all initial cash from all shifts
    $initial_cash_query = $db->query("SELECT SUM(initial_cash) as total_initial FROM shifts");
    $total_initial_cash = $initial_cash_query['total_initial'] ?? 0;


    // --- Final KPI Calculations ---
    $net_cash_sales = $gross_cash_sales_completed - $net_returns_on_completed_sales_cash;
    $net_transfer_sales = $gross_transfer_sales_completed - $net_returns_on_completed_sales_transfer;
    $total_sales_current_net = $net_cash_sales + $net_transfer_sales;
    $total_returns_amount = $net_returns_on_completed_sales_cash + $net_returns_on_completed_sales_transfer;
    
    // This KPI is tricky without the context of a single shift.
    // Let's define "Expected Cash" as the sum of all initial cash + all net cash sales - all other expenses.
    $expected_cash_in_drawer = $total_initial_cash + $net_cash_sales - $other_expenses_amount;

    $response['success'] = true;
    $response['message'] = 'Totals retrieved successfully.';
    $response['data'] = [
        'net_cash_sales' => $net_cash_sales,
        'net_transfer_sales' => $net_transfer_sales,
        'total_sales_current_net' => $total_sales_current_net,
        'total_returns_amount' => $total_returns_amount,
        'expected_cash_in_drawer' => $expected_cash_in_drawer,
    ];

} catch (Exception $e) {
    $response['message'] = 'A server error occurred while calculating totals.';
}

echo json_encode($response);
