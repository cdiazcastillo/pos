<?php
require_once 'includes/auth.php';
require_once 'config/db.php';

$user = auth_current_user();
if (!$user || (($user['role'] ?? '') !== 'admin') || !auth_is_super_admin()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Acceso denegado.';
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $tablesRaw = $conn->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
    $tables = array_map(static fn($row) => (string)$row[0], $tablesRaw ?: []);

    $filename = 'backup_ventas_' . date('Ymd_His') . '.sql';

    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "-- Respaldo generado el " . date('Y-m-d H:i:s') . "\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $createRow = $conn->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        if (!$createRow) {
            continue;
        }

        $createSql = '';
        foreach ($createRow as $key => $value) {
            if (stripos((string)$key, 'Create Table') !== false) {
                $createSql = (string)$value;
                break;
            }
        }

        if ($createSql === '') {
            continue;
        }

        echo "-- ----------------------------\n";
        echo "-- Estructura de tabla `{$table}`\n";
        echo "-- ----------------------------\n";
        echo "DROP TABLE IF EXISTS `{$table}`;\n";
        echo $createSql . ";\n\n";

        $rows = $conn->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            continue;
        }

        echo "-- ----------------------------\n";
        echo "-- Datos de `{$table}`\n";
        echo "-- ----------------------------\n";

        foreach ($rows as $row) {
            $columns = array_map(static fn($col) => "`{$col}`", array_keys($row));
            $values = array_map(static function ($value) use ($conn) {
                if ($value === null) {
                    return 'NULL';
                }
                return $conn->quote((string)$value);
            }, array_values($row));

            echo "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
        }

        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
} catch (Throwable $error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No se pudo generar el respaldo.';
    exit;
}
