<?php

function format_clp_amount($amount) {
    return '$' . number_format((float)$amount, 0, ',', '.');
}

function build_shift_close_email_content(array $report_data): array {
    $subject = 'cierre del turno ' . $report_data['shift_id'];

    $payment_lines = [];
    foreach ($report_data['payment_breakdown'] as $row) {
        $method_label = strtoupper($row['payment_method'] ?? 'cash');
        $qty = intval($row['qty'] ?? 0);
        $amount = floatval($row['amount'] ?? 0);
        $payment_lines[] = "- {$method_label}: {$qty} ventas / " . format_clp_amount($amount);
    }

    if (empty($payment_lines)) {
        $payment_lines[] = '- Sin ventas registradas';
    }

    $body = "Reporte de cierre de turno\n"
        . "========================\n"
        . "Turno: #{$report_data['shift_id']}\n"
        . "Usuario: #{$report_data['user_id']}\n"
        . "Inicio turno: {$report_data['start_time']}\n"
        . "Fin turno: {$report_data['end_time']}\n\n"
        . "Resumen de ventas\n"
        . "------------------\n"
        . "Cantidad ventas completadas: {$report_data['completed_sales_count']}\n"
        . "Total ventas: " . format_clp_amount($report_data['total_sales']) . "\n"
        . "Total gastos/devoluciones: " . format_clp_amount($report_data['total_expenses']) . "\n"
        . "Caja inicial: " . format_clp_amount($report_data['initial_cash']) . "\n"
        . "Caja esperada: " . format_clp_amount($report_data['expected_cash']) . "\n"
        . "Caja final declarada: " . format_clp_amount($report_data['final_cash']) . "\n"
        . "Diferencia: " . format_clp_amount($report_data['difference']) . "\n\n"
        . "Ventas por método de pago\n"
        . "-------------------------\n"
        . implode("\n", $payment_lines) . "\n";

    return [$subject, $body];
}

function ensure_notification_logs_table(PDO $conn): void {
    $conn->exec(
        "CREATE TABLE IF NOT EXISTS notification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_type VARCHAR(50) NOT NULL,
            reference_id INT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            last_attempt_at TIMESTAMP NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_notification_status (status),
            INDEX idx_notification_reference (notification_type, reference_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function create_notification_log(PDO $conn, array $payload): int {
    $stmt = $conn->prepare(
        "INSERT INTO notification_logs (notification_type, reference_id, recipient, subject, body, status)
         VALUES (?, ?, ?, ?, ?, 'pending')"
    );

    $stmt->execute([
        $payload['notification_type'],
        $payload['reference_id'],
        $payload['recipient'],
        $payload['subject'],
        $payload['body']
    ]);

    return (int)$conn->lastInsertId();
}

function send_logged_notification(PDO $conn, int $notification_id, int $max_attempts = 3): array {
    $stmt = $conn->prepare("SELECT * FROM notification_logs WHERE id = ?");
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        return [
            'success' => false,
            'attempts' => 0,
            'error' => 'Notificación no encontrada.'
        ];
    }

    if (($notification['status'] ?? '') === 'sent') {
        return [
            'success' => true,
            'attempts' => 0,
            'error' => null
        ];
    }

    $from = $_ENV['SHIFT_REPORT_FROM'] ?? 'no-reply@localhost';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: 4 Básico A <{$from}>\r\n";

    $total_attempts = intval($notification['attempts'] ?? 0);
    $last_error = null;

    for ($i = 0; $i < $max_attempts; $i++) {
        $total_attempts++;
        $sent = @mail($notification['recipient'], $notification['subject'], $notification['body'], $headers);

        if ($sent) {
            $updateStmt = $conn->prepare(
                "UPDATE notification_logs
                 SET status = 'sent', attempts = ?, last_error = NULL, last_attempt_at = CURRENT_TIMESTAMP, sent_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $updateStmt->execute([$total_attempts, $notification_id]);

            return [
                'success' => true,
                'attempts' => $total_attempts,
                'error' => null
            ];
        }

        $last_error = 'mail() devolvió false';
        usleep(300000);
    }

    $updateStmt = $conn->prepare(
        "UPDATE notification_logs
         SET status = 'failed', attempts = ?, last_error = ?, last_attempt_at = CURRENT_TIMESTAMP
         WHERE id = ?"
    );
    $updateStmt->execute([$total_attempts, $last_error, $notification_id]);

    return [
        'success' => false,
        'attempts' => $total_attempts,
        'error' => $last_error
    ];
}
