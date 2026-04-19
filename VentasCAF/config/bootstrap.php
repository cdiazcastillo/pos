<?php

function load_env($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Cargar variables de entorno para la aplicación principal
load_env(__DIR__ . '/../.env');

// Puedes añadir una bandera para entornos de testing si es necesario más adelante
// if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') {
//     load_env(__DIR__ . '/../.env.testing');
// }
