<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cajero') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit();
}

include '../config/database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $pedido_id = $input['pedido_id'] ?? null;
    $metodo_pago_id = $input['metodo_pago_id'] ?? null;
    $propina = $input['propina'] ?? 0;
    $monto_recibido = $input['monto_recibido'] ?? 0;
    $cambio = $input['cambio'] ?? 0;
    
    if (!$pedido_id || !$metodo_pago_id) {
        throw new Exception('Datos incompletos');
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // 1. Verificar que el pedido existe y está pendiente de pago
    $sql_pedido = "SELECT p.*, m.numero as mesa_numero 
                   FROM pedidos p 
                   JOIN mesas m ON p.mesa_id = m.id 
                   WHERE p.id = ? AND p.estado_pago = 'pendiente'";
    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->execute([$pedido_id]);
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        throw new Exception('Pedido no encontrado o ya pagado');
    }
    
    // 2. Calcular total
    $sql_items = "SELECT SUM(pi.cantidad * pi.precio_unitario) as total 
                  FROM pedido_items pi 
                  WHERE pi.pedido_id = ?";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$pedido_id]);
    $total = $stmt_items->fetch(PDO::FETCH_ASSOC)['total'];
    
    $total_final = $total + $propina;
    
    // 3. Insertar registro de pago
    $sql_pago = "INSERT INTO pagos (pedido_id, metodo_pago_id, monto, propina, monto_recibido, cambio, fecha_pago, estado) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), 'completado')";
    $stmt_pago = $pdo->prepare($sql_pago);
    $stmt_pago->execute([$pedido_id, $metodo_pago_id, $total, $propina, $monto_recibido, $cambio]);
    
    // 4. Actualizar estado del pedido
    $sql_update_pedido = "UPDATE pedidos SET estado_pago = 'pagado', fecha_pago = NOW() WHERE id = ?";
    $stmt_update_pedido = $pdo->prepare($sql_update_pedido);
    $stmt_update_pedido->execute([$pedido_id]);
    
    // 5. Liberar la mesa
    $sql_update_mesa = "UPDATE mesas SET estado = 'libre' WHERE id = ?";
    $stmt_update_mesa = $pdo->prepare($sql_update_mesa);
    $stmt_update_mesa->execute([$pedido['mesa_id']]);
    
    // Confirmar transacción
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pago procesado exitosamente',
        'data' => [
            'pedido_id' => $pedido_id,
            'total' => $total,
            'propina' => $propina,
            'total_final' => $total_final,
            'mesa_liberada' => $pedido['mesa_numero']
        ]
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error procesando pago: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar el pago: ' . $e->getMessage()
    ]);
}
?>