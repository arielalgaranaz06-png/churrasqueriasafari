<?php
session_start();
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
    $total = $_POST['total'];

    // Validar datos
    if (empty($mesa_id) || empty($usuario_id) || empty($items)) {
        throw new Exception('Datos incompletos');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // 1. Crear el pedido
    $sql_pedido = "INSERT INTO pedidos (mesa_id, usuario_id, total, estado) VALUES (?, ?, ?, 'pendiente')";
    $stmt_pedido = $pdo->prepare($sql_pedido);
    $stmt_pedido->execute([$mesa_id, $usuario_id, $total]);
    $pedido_id = $pdo->lastInsertId();

    // 2. Insertar items del pedido
    $sql_item = "INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
    $stmt_item = $pdo->prepare($sql_item);

    foreach ($items as $producto_id => $item) {
        $stmt_item->execute([$pedido_id, $producto_id, $item['cantidad'], $item['precio']]);
    }

    // 3. Actualizar estado de la mesa
    $sql_mesa = "UPDATE mesas SET estado = 'ocupada' WHERE id = ?";
    $stmt_mesa = $pdo->prepare($sql_mesa);
    $stmt_mesa->execute([$mesa_id]);

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido creado exitosamente',
        'pedido_id' => $pedido_id,
        'total' => $total
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar pedido: ' . $e->getMessage()
    ]);
}
?>