<?php
include '../config/database.php';

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Verificar sesión
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'garzon') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    // Obtener datos del POST
    $mesa_id = $_POST['mesa_id'];
    $usuario_id = $_POST['usuario_id'];
    $items = json_decode($_POST['items'], true);

    // Validar datos
    if (empty($mesa_id) || empty($usuario_id) || empty($items)) {
        throw new Exception('Datos incompletos');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // 1. Crear el pedido
    $sql_pedido = "INSERT INTO pedidos (mesa_id, usuario_id, estado, total) VALUES (?, ?, 'listo', 0)";
    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->execute([$mesa_id, $usuario_id]);
    $pedido_id = $pdo->lastInsertId();

    // 2. Insertar items del pedido y calcular total
    $total_pedido = 0;
    $sql_item = "INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
    $stmt_item = $pdo->prepare($sql_item);

    foreach ($items as $item) {
        $subtotal = $item['precio'] * $item['cantidad'];
        $total_pedido += $subtotal;
        
        $stmt_item->execute([
            $pedido_id,
            $item['producto_id'],
            $item['cantidad'],
            $item['precio']
        ]);
    }

    // 3. Actualizar total del pedido
    $sql_update_total = "UPDATE pedidos SET total = ? WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update_total);
    $stmt_update->execute([$total_pedido, $pedido_id]);

    // 4. Actualizar estado de la mesa a "ocupada"
    $sql_update_mesa = "UPDATE mesas SET estado = 'ocupada' WHERE id = ?";
    $stmt_mesa = $pdo->prepare($sql_update_mesa);
    $stmt_mesa->execute([$mesa_id]);

    // Confirmar transacción
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Pedido creado exitosamente',
        'pedido_id' => $pedido_id,
        'total' => $total_pedido
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar pedido: ' . $e->getMessage()
    ]);
}
?>