<?php
header('Content-Type: application/json');
require_once 'includes/auth.php';
auth_require_api_role(['admin']);

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

$db = Database::getInstance();

$sql = "
    SELECT 
        s.id, 
        u.username as user, 
        s.start_time, 
        s.end_time, 
        s.final_cash,
        s.status,
        COALESCE(sales_summary.cash_sales, 0) as cash_sales,
        COALESCE(sales_summary.transfer_sales, 0) as transfer_sales
    FROM shifts s 
    JOIN users u ON s.user_id = u.id
    LEFT JOIN (
        SELECT 
            shift_id,
            SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash_sales,
            SUM(CASE WHEN payment_method = 'transfer' THEN total_amount ELSE 0 END) as transfer_sales
        FROM sales
        WHERE status = 'completed'
        GROUP BY shift_id
    ) as sales_summary ON s.id = sales_summary.shift_id
    ORDER BY s.start_time DESC
";

$shifts = $db->query($sql, [], true);

if ($shifts) {
    $response['success'] = true;
    $response['data'] = $shifts;
} else {
    $response['message'] = 'No shifts found.';
    $response['data'] = [];
}

echo json_encode($response);
