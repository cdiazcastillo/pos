<?php
header('Content-Type: application/json');

session_start();
require_once 'config/db.php';
require_once 'includes/notification_helper.php';

$response = ['success' => false, 'message' => 'No se pudo ejecutar el reintento de notificaciones.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Autenticación requerida.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método de solicitud inválido.';
    echo json_encode($response);
    exit;
}

$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
if ($limit < 1 || $limit > 100) {
    $limit = 20;
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $user = $db->query('SELECT id, role FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        throw new Exception('Solo administradores pueden reintentar notificaciones.');
    }

    ensure_notification_logs_table($conn);

    $stmt = $conn->prepare(
        "SELECT id
         FROM notification_logs
         WHERE status IN ('failed', 'pending')
         ORDER BY id ASC
         LIMIT {$limit}"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $sent = 0;
    $failed = 0;

    foreach ($rows as $row) {
        $processed++;
        $result = send_logged_notification($conn, intval($row['id']), 2);
        if ($result['success']) {
            $sent++;
        } else {
            $failed++;
        }
    }

    $response['success'] = true;
    $response['message'] = 'Reintento ejecutado.';
    $response['data'] = [
        'processed' => $processed,
        'sent' => $sent,
        'failed' => $failed
    ];
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
