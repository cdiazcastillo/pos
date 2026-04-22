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

$user_id = $_SESSION['user_id'];
$mode = trim((string)($_POST['mode'] ?? 'own'));

try {
    $db = Database::getInstance();
    $user = $db->query("SELECT id, role FROM users WHERE id = ?", [$user_id]);
    $role = (string)($user['role'] ?? 'cashier');

    if ($mode === 'join') {
        if ($role !== 'admin') {
            $response['message'] = 'Solo equipo de trabajo puede unirse a turnos activos.';
            echo json_encode($response);
            exit;
        }

        $target_shift_id = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
        if (!$target_shift_id) {
            $response['message'] = 'Selecciona un turno activo válido.';
            echo json_encode($response);
            exit;
        }

        $target_shift = $db->query("SELECT id FROM shifts WHERE id = ? AND status = 'open'", [$target_shift_id]);
        if (!$target_shift) {
            $response['message'] = 'El turno seleccionado no está activo.';
            echo json_encode($response);
            exit;
        }

        $_SESSION['selected_shift_id'] = intval($target_shift_id);
        $response['success'] = true;
        $response['message'] = 'Turno activo compartido seleccionado correctamente.';
        $response['shift_id'] = intval($target_shift_id);
        echo json_encode($response);
        exit;
    }

    if (!isset($_POST['initial_cash'])) {
        $response['message'] = 'Invalid initial cash amount provided.';
        echo json_encode($response);
        exit;
    }

    $initial_cash_raw = trim((string)$_POST['initial_cash']);
    if (!preg_match('/^\d+$/', $initial_cash_raw)) {
        $response['message'] = 'Invalid initial cash amount provided.';
        echo json_encode($response);
        exit;
    }
    $initial_cash = intval($initial_cash_raw);

    // 3. Business Logic: Check for an existing open shift for this user
    $existing_shift = $db->query("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'", [$user_id]);

    if ($existing_shift) {
        if ($role !== 'admin') {
            $response['message'] = 'Ya tienes un turno activo. Debes cerrarlo antes de iniciar otro.';
            echo json_encode($response);
            exit;
        }
        $_SESSION['selected_shift_id'] = intval($existing_shift['id']);
        $response['success'] = true;
        $response['message'] = 'Ya tienes un turno propio activo. Se mantendrá ese turno.';
        $response['shift_id'] = intval($existing_shift['id']);
        echo json_encode($response);
        exit;
    }

    // 4. Business Logic: Insert the new shift
    $sql = "INSERT INTO shifts (user_id, initial_cash, status) VALUES (?, ?, 'open')";
    $affected_rows = $db->execute($sql, [$user_id, $initial_cash]);

    if ($affected_rows > 0) {
        $new_shift_id = $db->lastInsertId();
        $_SESSION['selected_shift_id'] = intval($new_shift_id);
        $response['success'] = true;
        $response['message'] = 'Shift started successfully.';
        $response['shift_id'] = intval($new_shift_id);
    } else {
        $response['message'] = 'Failed to start the shift in the database.';
    }

} catch (Exception $e) {
    $response['message'] = 'A server error occurred. Please try again later.';
}

// 5. Final Response
echo json_encode($response);
