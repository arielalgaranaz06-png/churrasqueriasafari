<?php
// Configuración de la base de datos
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_USER', 'if0_40123283');
define('DB_PASS', 'R06G6qhvqoj7k4a');
define('DB_NAME', 'if0_40123283_restaurante');

// Iniciar sesión
session_start();

// Conectar a la base de datos
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: No se pudo conectar. " . $e->getMessage());
}
?>