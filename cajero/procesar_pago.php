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
    
    // Obtener el total del pedido primero
    $sql_total = "SELECT total FROM pedidos WHERE id = ?";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute([$pedido_id]);
    $pedido_data = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_pedido = $pedido_data['total'];
    
    // Determinar turno basado en la hora actual
    $hora_actual = date('H');
    $turno = ($hora_actual >= 12 && $hora_actual < 18) ? 'mañana' : 'tarde';
    
    // Actualizar el pedido con toda la información del pago
    $sql_pedido = "
        UPDATE pedidos 
        SET 
            estado = 'pagado', 
            fecha_pago = NOW(),
            metodo_pago_id = ?,
            propina = ?,
            monto_recibido = ?,
            cambio = ?,
            turno = ?
        WHERE id = ?
    ";
    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->execute([
        $metodo_pago_id,
        $propina,
        $monto_recibido,
        $cambio,
        $turno,
        $pedido_id
    ]);
    
    // Registrar movimiento en caja si hay caja abierta
    $sql_caja = "SELECT id FROM caja_control WHERE usuario_id = ? AND estado = 'abierta' ORDER BY fecha_apertura DESC LIMIT 1";
    $stmt_caja = $pdo->prepare($sql_caja);
    $stmt_caja->execute([$_SESSION['user_id']]);
    $caja = $stmt_caja->fetch();
    
    if ($caja) {
        // Obtener nombre del método de pago para la descripción
        $sql_metodo = "SELECT nombre FROM metodos_pago WHERE id = ?";
        $stmt_metodo = $pdo->prepare($sql_metodo);
        $stmt_metodo->execute([$metodo_pago_id]);
        $metodo_pago = $stmt_metodo->fetch(PDO::FETCH_ASSOC);
        
        $descripcion = "Venta - Pedido #" . $pedido_id . " - " . ($metodo_pago['nombre'] ?? 'Método no especificado');
        
        $sql_movimiento = "
            INSERT INTO caja_movimientos (caja_control_id, pedido_id, tipo, monto, descripcion, metodo_pago_id) 
            VALUES (?, ?, 'ingreso', ?, ?, ?)
        ";
        $stmt_movimiento = $pdo->prepare($sql_movimiento);
        $stmt_movimiento->execute([$caja['id'], $pedido_id, $total_pedido, $descripcion, $metodo_pago_id]);
    }
    
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