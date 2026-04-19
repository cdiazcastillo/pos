<?php
header('Content-Type: application/json');

session_start();
require_once 'config/db.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required. Please log in.';
    echo json_encode($response);
    exit;
}

// 2. Input Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['initial_cash']) || !is_numeric($_POST['initial_cash']) || floatval($_POST['initial_cash']) < 0) {
    $response['message'] = 'Invalid initial cash amount provided.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$initial_cash = floatval($_POST['initial_cash']);

try {
    $db = Database::getInstance();

    // 3. Business Logic: Check for an existing open shift for this user
    $existing_shift = $db->query("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'", [$user_id]);

    if ($existing_shift) {
        $response['message'] = 'You already have an open shift. Please close it before starting a new one.';
        echo json_encode($response);
        exit;
    }

    // 4. Business Logic: Insert the new shift
    $sql = "INSERT INTO shifts (user_id, initial_cash, status) VALUES (?, ?, 'open')";
    $affected_rows = $db->execute($sql, [$user_id, $initial_cash]);

    if ($affected_rows > 0) {
        $new_shift_id = $db->lastInsertId();
        $response['success'] = true;
        $response['message'] = 'Shift started successfully.';
    } else {
        $response['message'] = 'Failed to start the shift in the database.';
    }

} catch (Exception $e) {
    $response['message'] = 'A server error occurred. Please try again later.';
}

// 5. Final Response
echo json_encode($response);
