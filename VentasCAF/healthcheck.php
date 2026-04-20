<?php
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/db.php';

function safe_env_value($value) {
    if ($value === null || $value === false || $value === '') {
        return '[vacío]';
    }
    return $value;
}

echo "VentasCAF Healthcheck\n";
echo "====================\n\n";

echo "Variables cargadas:\n";
echo 'DB_HOST: ' . safe_env_value($_ENV['DB_HOST'] ?? getenv('DB_HOST')) . "\n";
echo 'DB_NAME: ' . safe_env_value($_ENV['DB_NAME'] ?? getenv('DB_NAME')) . "\n";
echo 'DB_USER: ' . safe_env_value($_ENV['DB_USER'] ?? getenv('DB_USER')) . "\n";
echo 'DB_PORT: ' . safe_env_value($_ENV['DB_PORT'] ?? getenv('DB_PORT')) . "\n";

$db_pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS');
$pass_info = ($db_pass === null || $db_pass === false || $db_pass === '')
    ? '[vacío]'
    : '[definido: ' . strlen((string)$db_pass) . ' caracteres]';
echo 'DB_PASS: ' . $pass_info . "\n\n";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $query = $conn->query('SELECT NOW() AS server_time');
    $server_time = $query ? ($query->fetch()['server_time'] ?? null) : null;

    echo "Conexión a base de datos: OK\n";
    echo 'Hora del servidor MySQL: ' . safe_env_value($server_time) . "\n";
    echo "\nEstado general: TODO OK\n";
} catch (Exception $e) {
    echo "Conexión a base de datos: ERROR\n";
    echo 'Detalle: ' . $e->getMessage() . "\n";
    echo "\nEstado general: REVISAR CREDENCIALES\n";
}
