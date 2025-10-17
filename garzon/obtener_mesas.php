<?php
session_start();
include '../config/database.php';

// Verificar sesión
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'garzon') {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

try {
    $sql_mesas = "SELECT * FROM mesas WHERE estado = 'libre' ORDER BY numero";
    $stmt_mesas = $pdo->query($sql_mesas);
    $mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($mesas);
} catch (Exception $e) {
    echo json_encode([]);
}
?>