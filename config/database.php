<?php
// Configuración de la base de datos
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_USER', 'if0_40123283');
define('DB_PASS', 'R06G6qhvqoj7k4a');
define('DB_NAME', 'if0_40123283_restaurante');

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conectar a la base de datos
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error de base de datos: " . $e->getMessage());
    die("Error de conexión con la base de datos. Por favor, intente más tarde.");
}
?>