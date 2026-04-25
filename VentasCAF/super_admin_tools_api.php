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

function ensure_super_admin_audit_table(PDO $conn): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS super_admin_audit_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT NOT NULL,
            admin_username VARCHAR(100) NOT NULL,
            action_type VARCHAR(80) NOT NULL,
            target_shift_id INT NULL,
            details TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action_type (action_type),
            INDEX idx_target_shift (target_shift_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->exec($sql);
}

function insert_super_admin_audit(PDO $conn, int $adminUserId, string $adminUsername, string $actionType, ?int $targetShiftId, string $details = ''): void {
    $stmt = $conn->prepare(
        "INSERT INTO super_admin_audit_logs (admin_user_id, admin_username, action_type, target_shift_id, details)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $adminUserId,
        $adminUsername,
        $actionType,
        $targetShiftId,
        $details,
    ]);
}

if ($action === '') {
    echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
    exit;
}

try {
    $conn = $db->getConnection();
    ensure_super_admin_audit_table($conn);

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

    if ($action === 'list_force_close_history') {
        $rows = $db->query(
            "SELECT id, admin_username, action_type, target_shift_id, details, created_at
             FROM super_admin_audit_logs
             WHERE action_type IN ('force_close_shift', 'terminate_incomplete_processes')
             ORDER BY created_at DESC
             LIMIT 50",
            [],
            true
        ) ?: [];

        $history = array_map(static function ($row) {
            return [
                'id' => intval($row['id'] ?? 0),
                'admin_username' => (string)($row['admin_username'] ?? 'admin'),
                'action_type' => (string)($row['action_type'] ?? ''),
                'target_shift_id' => isset($row['target_shift_id']) ? intval($row['target_shift_id']) : null,
                'details' => (string)($row['details'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }, $rows);

        echo json_encode(['success' => true, 'data' => ['history' => $history]]);
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

        insert_super_admin_audit(
            $conn,
            intval($user['id'] ?? 0),
            (string)($user['username'] ?? 'admin'),
            'force_close_shift',
            intval($shiftId),
            'Cierre forzado de turno abierto.'
        );

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

        insert_super_admin_audit(
            $conn,
            intval($user['id'] ?? 0),
            (string)($user['username'] ?? 'admin'),
            'terminate_incomplete_processes',
            null,
            'Turnos colgados cerrados: ' . $closedHungShifts . '; notificaciones finalizadas: ' . $finalizedNotifications
        );

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
