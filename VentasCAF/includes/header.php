<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    // Forcing a user for development purposes.
    // In a real implementation, you would have a full login system.
    $_SESSION['user_id'] = 1; 
}

$db = Database::getInstance();
$active_shift = $db->query("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'", [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'VentasCAF'; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="img/logo.png" alt="Logo" style="max-width: 100px;">
            <h1><?php echo $page_title ?? 'VentasCAF'; ?></h1>
            <div>
                <a href="admin.php" class="btn btn-secondary">Menú Principal</a>
            </div>
        </div>
        <div class="content">