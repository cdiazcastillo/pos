<?php
header('Content-Type: application/json');

session_start();
require_once 'config/db.php';
require_once 'includes/notification_helper.php';

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
    $shift = $db->query("SELECT id, initial_cash, start_time FROM shifts WHERE user_id = ? AND status = 'open'", [$user_id]);
    if (!$shift) {
        throw new Exception('No active shift found to close.');
    }
    $shift_id = $shift['id'];
    $initial_cash = floatval($shift['initial_cash']);
    $start_time = $shift['start_time'];

    // 2b. Calculate Total Sales (sum of completed sales for the shift)
    $sales_result = $db->query(
        "SELECT SUM(total_amount) as total_sales FROM sales WHERE shift_id = ? AND status = 'completed'",
        [$shift_id]
    );
    $total_sales = floatval($sales_result['total_sales'] ?? 0);

    $sales_count_result = $db->query(
        "SELECT COUNT(*) as total_count FROM sales WHERE shift_id = ? AND status = 'completed'",
        [$shift_id]
    );
    $completed_sales_count = intval($sales_count_result['total_count'] ?? 0);

    $payment_breakdown = $db->query(
        "SELECT payment_method, COUNT(*) as qty, SUM(total_amount) as amount
         FROM sales
         WHERE shift_id = ? AND status = 'completed'
         GROUP BY payment_method",
        [$shift_id],
        true
    );
    $payment_breakdown = is_array($payment_breakdown) ? $payment_breakdown : [];

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

    $report_data = [
        'shift_id' => $shift_id,
        'user_id' => $user_id,
        'start_time' => $start_time,
        'end_time' => date('Y-m-d H:i:s'),
        'completed_sales_count' => $completed_sales_count,
        'payment_breakdown' => array_map(function ($row) {
            return [
                'payment_method' => $row['payment_method'] ?? 'cash',
                'qty' => intval($row['qty'] ?? 0),
                'amount' => floatval($row['amount'] ?? 0)
            ];
        }, $payment_breakdown),
        'initial_cash' => $initial_cash,
        'total_sales' => $total_sales,
        'total_expenses' => $total_expenses,
        'expected_cash' => $expected_cash,
        'final_cash' => $final_cash,
        'difference' => $difference
    ];

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
        'difference' => $difference,
        'completed_sales_count' => $completed_sales_count,
        'payment_breakdown' => $report_data['payment_breakdown']
    ];

    try {
        $conn = $db->getConnection();
        ensure_notification_logs_table($conn);

        [$email_subject, $email_body] = build_shift_close_email_content($report_data);
        $recipient = $_ENV['SHIFT_REPORT_EMAIL'] ?? 'carlosdiazc@gmail.com';

        $notification_id = create_notification_log($conn, [
            'notification_type' => 'shift_close',
            'reference_id' => $shift_id,
            'recipient' => $recipient,
            'subject' => $email_subject,
            'body' => $email_body
        ]);

        $send_result = send_logged_notification($conn, $notification_id, 3);

        $response['email'] = [
            'sent' => $send_result['success'],
            'notification_id' => $notification_id,
            'attempts' => $send_result['attempts'],
            'error' => $send_result['error']
        ];

        if (!$send_result['success']) {
            $response['message'] = 'Shift closed successfully, but the report email could not be sent. Check monitor panel.';
        }
    } catch (Exception $email_error) {
        $response['email'] = [
            'sent' => false,
            'notification_id' => null,
            'attempts' => 0,
            'error' => $email_error->getMessage()
        ];
        $response['message'] = 'Shift closed successfully, but email logging failed. Check server configuration.';
    }

} catch (Exception $e) {
    // If anything fails, roll back
    if ($db->getConnection()->inTransaction()) {
        $db->rollBack();
    }
    $response['message'] = $e->getMessage();
}

// Final Response
echo json_encode($response);
