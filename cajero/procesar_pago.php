<?php
// FORZAR HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect);
        exit();
    }
}
// FIN FORZAR HTTPS

session_start();
header('Content-Type: application/json');

// Verificar si el usuario está logueado y es cajero
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../config/database.php';

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

$pedido_id = $input['pedido_id'] ?? null;
$metodo_pago_id = $input['metodo_pago_id'] ?? null;
$propina = $input['propina'] ?? 0;
$monto_recibido = $input['monto_recibido'] ?? 0;
$cambio = $input['cambio'] ?? 0;

if (!$pedido_id || !$metodo_pago_id) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Actualizar el pedido con toda la información del pago
    $sql_pedido = "
        UPDATE pedidos 
        SET 
            estado = 'pagado', 
            fecha_pago = NOW(),
            metodo_pago_id = ?,
            propina = ?,
            monto_recibido = ?,
            cambio = ?
        WHERE id = ?
    ";
    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->execute([
        $metodo_pago_id,
        $propina,
        $monto_recibido,
        $cambio,
        $pedido_id
    ]);
    
    // Liberar la mesa
    $sql_mesa = "
        UPDATE mesas m 
        JOIN pedidos p ON m.id = p.mesa_id 
        SET m.estado = 'libre' 
        WHERE p.id = ?
    ";
    $stmt_mesa = $pdo->prepare($sql_mesa);
    $stmt_mesa->execute([$pedido_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pago procesado exitosamente']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>