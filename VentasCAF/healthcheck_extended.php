<?php
header('Content-Type: text/plain; charset=UTF-8');

require_once __DIR__ . '/config/bootstrap.php';

function env_value(string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || trim((string)$value) === '') {
        return $default;
    }
    return trim((string)$value);
}

function safe_env_value($value): string {
    if ($value === null || $value === false || trim((string)$value) === '') {
        return '[vacío]';
    }
    return trim((string)$value);
}

function parse_hosts(string $raw): array {
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/[;,\s]+/', $raw);
    $hosts = [];
    foreach ($parts as $part) {
        $host = trim((string)$part);
        if ($host !== '') {
            $hosts[] = $host;
        }
    }

    return array_values(array_unique($hosts));
}

function connect_test(string $host, string $port, string $dbname, string $user, string $pass): array {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]);

        $serverTime = $pdo->query('SELECT NOW() AS server_time')->fetch()['server_time'] ?? null;

        return [
            'ok' => true,
            'error' => null,
            'server_time' => $serverTime
        ];
    } catch (PDOException $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
            'server_time' => null
        ];
    }
}

$dbHost = env_value('DB_HOST', 'localhost');
$dbPort = env_value('DB_PORT', '3306');
$dbName = env_value('DB_NAME', '');
$dbUser = env_value('DB_USER', '');
$dbPass = env_value('DB_PASS', '');
$dbHostCandidatesRaw = env_value('DB_HOST_CANDIDATES', '');

$hostsToTest = [];
$hostsToTest[] = $dbHost;
$hostsToTest[] = 'localhost';
$hostsToTest[] = '127.0.0.1';

foreach (parse_hosts($dbHostCandidatesRaw) as $candidate) {
    $hostsToTest[] = $candidate;
}

$hostsToTest = array_values(array_unique(array_filter($hostsToTest, function ($host) {
    return trim((string)$host) !== '';
})));

echo "VentasCAF Healthcheck Extendido\n";
echo "=============================\n\n";

echo "Configuración activa:\n";
echo 'DB_HOST: ' . safe_env_value($dbHost) . "\n";
echo 'DB_PORT: ' . safe_env_value($dbPort) . "\n";
echo 'DB_NAME: ' . safe_env_value($dbName) . "\n";
echo 'DB_USER: ' . safe_env_value($dbUser) . "\n";
echo 'DB_PASS: ' . ($dbPass === '' ? '[vacío]' : '[definido: ' . strlen($dbPass) . ' caracteres]') . "\n";
echo 'DB_HOST_CANDIDATES: ' . safe_env_value($dbHostCandidatesRaw) . "\n\n";

echo "Hosts a probar (en orden):\n";
foreach ($hostsToTest as $index => $host) {
    $pos = $index + 1;
    echo "{$pos}. {$host}\n";
}
echo "\n";

$firstWorkingHost = null;
$results = [];

foreach ($hostsToTest as $host) {
    $result = connect_test($host, $dbPort, $dbName, $dbUser, $dbPass);
    $results[] = ['host' => $host, 'result' => $result];

    if ($firstWorkingHost === null && $result['ok']) {
        $firstWorkingHost = [
            'host' => $host,
            'server_time' => $result['server_time']
        ];
    }
}

echo "Resultados:\n";
echo "-----------\n";
foreach ($results as $row) {
    $host = $row['host'];
    $res = $row['result'];
    if ($res['ok']) {
        echo "[OK] {$host} | server_time={$res['server_time']}\n";
    } else {
        echo "[FAIL] {$host} | {$res['error']}\n";
    }
}

echo "\n";
if ($firstWorkingHost !== null) {
    echo "Host recomendado: {$firstWorkingHost['host']}\n";
    echo "Estado general: TODO OK\n";
} else {
    echo "Host recomendado: ninguno (sin respuesta)\n";
    echo "Estado general: REVISAR HOST/PUERTO/CREDENCIALES\n";
}
