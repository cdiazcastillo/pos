<?php
header('Content-Type: application/json');

require_once 'includes/auth.php';
require_once 'config/db.php';

$user = auth_require_api_role(['admin']);
if (!auth_is_super_admin()) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para esta acción.']);
    exit;
}

$db = Database::getInstance();
$action = trim((string)($_REQUEST['action'] ?? ''));

if ($action === '') {
    echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
    exit;
}

try {
    if ($action === 'list_shifts') {
        $rows = $db->query(
            "SELECT s.id, s.user_id, s.initial_cash, s.start_time, u.username,
                    TIMESTAMPDIFF(MINUTE, s.start_time, NOW()) AS open_minutes
             FROM shifts s
             JOIN users u ON u.id = s.user_id
             WHERE s.status = 'open'
             ORDER BY s.start_time ASC",
            [],
            true
        ) ?: [];

        $shifts = array_map(static function ($row) {
            $minutes = intval($row['open_minutes'] ?? 0);
            $startRaw = (string)($row['start_time'] ?? '');
            $startTs = strtotime($startRaw);
            return [
                'id' => intval($row['id'] ?? 0),
                'user_id' => intval($row['user_id'] ?? 0),
                'username' => (string)($row['username'] ?? 'usuario'),
                'initial_cash' => intval($row['initial_cash'] ?? 0),
                'open_minutes' => $minutes,
                'is_hung' => $minutes >= 480,
                'start_label' => $startTs ? date('d-m-Y H:i', $startTs) : $startRaw,
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => ['shifts' => $shifts]]);
        exit;
    }

    if ($action === 'force_close_shift') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método inválido.']);
            exit;
        }

        $shiftId = filter_input(INPUT_POST, 'shift_id', FILTER_VALIDATE_INT);
        if (!$shiftId) {
            echo json_encode(['success' => false, 'message' => 'Turno inválido.']);
            exit;
        }

        $shift = $db->query(
            "SELECT id, initial_cash FROM shifts WHERE id = ? AND status = 'open'",
            [$shiftId]
        );

        if (!$shift) {
            echo json_encode(['success' => false, 'message' => 'El turno ya está cerrado o no existe.']);
            exit;
        }

        $finalCash = intval($shift['initial_cash'] ?? 0);
        $db->execute(
            "UPDATE shifts
             SET status = 'closed',
                 end_time = COALESCE(end_time, CURRENT_TIMESTAMP),
                 final_cash = COALESCE(final_cash, ?)
             WHERE id = ? AND status = 'open'",
            [$finalCash, $shiftId]
        );

        if (intval($_SESSION['selected_shift_id'] ?? 0) === intval($shiftId)) {
            unset($_SESSION['selected_shift_id']);
        }

        echo json_encode(['success' => true, 'message' => 'Turno cerrado de forma forzada.']);
        exit;
    }

    if ($action === 'terminate_incomplete_processes') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método inválido.']);
            exit;
        }

        $db->beginTransaction();

        $closeHungStmt = $db->getConnection()->prepare(
            "UPDATE shifts
             SET status = 'closed',
                 end_time = COALESCE(end_time, CURRENT_TIMESTAMP),
                 final_cash = COALESCE(final_cash, initial_cash)
             WHERE status = 'open' AND start_time < (NOW() - INTERVAL 8 HOUR)"
        );
        $closeHungStmt->execute();
        $closedHungShifts = $closeHungStmt->rowCount();

        $finalizedNotifications = 0;
        $hasNotificationLogs = $db->query("SHOW TABLES LIKE 'notification_logs'");
        if ($hasNotificationLogs) {
            $notifyStmt = $db->getConnection()->prepare(
                "UPDATE notification_logs
                 SET status = 'failed',
                     last_error = COALESCE(last_error, 'Finalizado por super admin')
                 WHERE status = 'pending'"
            );
            $notifyStmt->execute();
            $finalizedNotifications = $notifyStmt->rowCount();
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Procesos incompletos finalizados correctamente.',
            'data' => [
                'closed_hung_shifts' => $closedHungShifts,
                'finalized_notifications' => $finalizedNotifications,
            ]
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no soportada.']);
} catch (Throwable $error) {
    $conn = $db->getConnection();
    if ($conn->inTransaction()) {
        $db->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Error interno al ejecutar la acción.',
    ]);
}
