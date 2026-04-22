<?php
// Script para actualizar la contraseña del admin a 250012

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/db.php';

$new_password = '250012';
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $db = Database::getInstance();
    $db->execute(
        "UPDATE users SET password_hash = ? WHERE username = 'admin'",
        [$password_hash]
    );
    echo "✓ Contraseña del usuario admin actualizada a: 250012<br>";
    echo "✓ Puedes iniciar sesión con:<br>";
    echo "  Usuario: <strong>admin</strong><br>";
    echo "  Contraseña: <strong>250012</strong>";
} catch (Exception $e) {
    echo "✗ Error al actualizar contraseña: " . $e->getMessage();
}
?>
