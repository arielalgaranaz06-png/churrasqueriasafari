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
    
    // Actualizar estado del pedido a "pagado"
    $sql_pedido = "UPDATE pedidos SET estado = 'pagado', fecha_pago = NOW() WHERE id = ?";
    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->execute([$pedido_id]);
    
    // Registrar el pago
    $sql_pago = "
        INSERT INTO pagos (pedido_id, metodo_pago_id, monto_total, propina, monto_recibido, cambio, fecha_pago) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ";
    $stmt_pago = $pdo->prepare($sql_pago);
    
    // Obtener el total del pedido
    $sql_total = "SELECT total FROM pedidos WHERE id = ?";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute([$pedido_id]);
    $pedido = $stmt_total->fetch(PDO::FETCH_ASSOC);
    
    $stmt_pago->execute([
        $pedido_id,
        $metodo_pago_id,
        $pedido['total'],
        $propina,
        $monto_recibido,
        $cambio
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