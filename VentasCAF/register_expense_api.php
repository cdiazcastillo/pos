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

$mode = trim((string)($_POST['mode'] ?? 'create'));
$user_id = intval($_SESSION['user_id']);

try {
    $db = Database::getInstance();

    $userRow = $db->query("SELECT role FROM users WHERE id = ?", [$user_id]);
    $isAdmin = (($userRow['role'] ?? '') === 'admin');

    $hasExpensePaymentMethod = $db->query("SHOW COLUMNS FROM expenses LIKE 'payment_method'");
    if (!$hasExpensePaymentMethod) {
        $db->execute("ALTER TABLE expenses ADD COLUMN payment_method ENUM('cash', 'transfer') NOT NULL DEFAULT 'cash' AFTER amount");
        $hasExpensePaymentMethod = $db->query("SHOW COLUMNS FROM expenses LIKE 'payment_method'");
    }

    if ($mode === 'delete') {
        $expenseId = intval($_POST['expense_id'] ?? 0);
        if ($expenseId <= 0) {
            $response['message'] = 'Invalid expense id.';
            echo json_encode($response);
            exit;
        }

        $expense = $db->query(
            "SELECT e.id
             FROM expenses e
             JOIN shifts s ON s.id = e.shift_id
             WHERE e.id = ? AND e.sale_id IS NULL AND (? = 1 OR s.user_id = ?)",
            [$expenseId, $isAdmin ? 1 : 0, $user_id]
        );

        if (!$expense) {
            $response['message'] = 'Expense not found or access denied.';
            echo json_encode($response);
            exit;
        }

        $affectedRows = $db->execute("DELETE FROM expenses WHERE id = ?", [$expenseId]);
        if ($affectedRows > 0) {
            $response['success'] = true;
            $response['message'] = 'Expense deleted successfully.';
        } else {
            $response['message'] = 'Expense could not be deleted.';
        }

        echo json_encode($response);
        exit;
    }

    $description = trim((string)($_POST['description'] ?? ''));
    $amountRaw = trim((string)($_POST['amount'] ?? ''));
    $paymentMethod = trim((string)($_POST['payment_method'] ?? 'cash'));

    if ($description === '') {
        $response['message'] = 'Expense description cannot be empty.';
        echo json_encode($response);
        exit;
    }

    if ($amountRaw === '' || !preg_match('/^\d+$/', $amountRaw) || intval($amountRaw) <= 0) {
        $response['message'] = 'Invalid expense amount. It must be a positive integer.';
        echo json_encode($response);
        exit;
    }

    if (!in_array($paymentMethod, ['cash', 'transfer'], true)) {
        $response['message'] = 'Invalid payment method. Use cash or transfer.';
        echo json_encode($response);
        exit;
    }

    $amount = intval($amountRaw);

    if ($mode === 'update') {
        $expenseId = intval($_POST['expense_id'] ?? 0);
        if ($expenseId <= 0) {
            $response['message'] = 'Invalid expense id.';
            echo json_encode($response);
            exit;
        }

        $expense = $db->query(
            "SELECT e.id
             FROM expenses e
             JOIN shifts s ON s.id = e.shift_id
             WHERE e.id = ? AND e.sale_id IS NULL AND (? = 1 OR s.user_id = ?)",
            [$expenseId, $isAdmin ? 1 : 0, $user_id]
        );

        if (!$expense) {
            $response['message'] = 'Expense not found or access denied.';
            echo json_encode($response);
            exit;
        }

        if ($hasExpensePaymentMethod) {
            $affectedRows = $db->execute(
                "UPDATE expenses SET description = ?, amount = ?, payment_method = ? WHERE id = ?",
                [$description, $amount, $paymentMethod, $expenseId]
            );
        } else {
            $descriptionWithMethod = '[' . strtoupper($paymentMethod === 'cash' ? 'EFECTIVO' : 'TRANSFERENCIA') . '] ' . $description;
            $affectedRows = $db->execute(
                "UPDATE expenses SET description = ?, amount = ? WHERE id = ?",
                [$descriptionWithMethod, $amount, $expenseId]
            );
        }

        $response['success'] = ($affectedRows >= 0);
        $response['message'] = $response['success'] ? 'Expense updated successfully.' : 'Failed to update expense.';
        echo json_encode($response);
        exit;
    }

    // create
    $shift_result = $db->query("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'", [$user_id]);

    if (!$shift_result) {
        $response['message'] = 'No active shift found. Cannot register an expense.';
        echo json_encode($response);
        exit;
    }
    $shift_id = $shift_result['id'];

    if ($hasExpensePaymentMethod) {
        $sql = "INSERT INTO expenses (shift_id, description, amount, payment_method) VALUES (?, ?, ?, ?)";
        $affected_rows = $db->execute($sql, [$shift_id, $description, $amount, $paymentMethod]);
    } else {
        $sql = "INSERT INTO expenses (shift_id, description, amount) VALUES (?, ?, ?)";
        $descriptionWithMethod = '[' . strtoupper($paymentMethod === 'cash' ? 'EFECTIVO' : 'TRANSFERENCIA') . '] ' . $description;
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
