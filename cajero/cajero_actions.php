<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cajero') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit();
}

include '../config/database.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_mesa':
            getMesa();
            break;
        case 'create_mesa':
            createMesa();
            break;
        case 'update_mesa':
            updateMesa();
            break;
        case 'delete_mesa':
            deleteMesa();
            break;
        case 'get_producto':
            getProducto();
            break;
        case 'create_producto':
            createProducto();
            break;
        case 'update_producto':
            updateProducto();
            break;
        case 'delete_producto':
            deleteProducto();
            break;
        case 'procesar_pago':
            procesarPago();
            break;
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function getMesa() {
    global $pdo;
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('ID de mesa no proporcionado');
    }
    
    $sql = "SELECT * FROM mesas WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mesa) {
        throw new Exception('Mesa no encontrada');
    }
    
    echo json_encode(['success' => true, 'mesa' => $mesa]);
}

function createMesa() {
    global $pdo;
    $numero = $_POST['numero_mesa'] ?? null;
    $estado = $_POST['estado_mesa'] ?? 'libre';
    
    if (!$numero) {
        throw new Exception('Número de mesa requerido');
    }
    
    // Verificar si ya existe una mesa con ese número
    $sql_check = "SELECT id FROM mesas WHERE numero = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$numero]);
    
    if ($stmt_check->fetch()) {
        throw new Exception('Ya existe una mesa con ese número');
    }
    
    $sql = "INSERT INTO mesas (numero, estado) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$numero, $estado]);
    
    echo json_encode(['success' => true, 'message' => 'Mesa creada exitosamente']);
}

function updateMesa() {
    global $pdo;
    $id = $_POST['mesa_id'] ?? null;
    $numero = $_POST['numero_mesa'] ?? null;
    $estado = $_POST['estado_mesa'] ?? null;
    
    if (!$id || !$numero || !$estado) {
        throw new Exception('Datos incompletos');
    }
    
    // Verificar si ya existe otra mesa con ese número
    $sql_check = "SELECT id FROM mesas WHERE numero = ? AND id != ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$numero, $id]);
    
    if ($stmt_check->fetch()) {
        throw new Exception('Ya existe otra mesa con ese número');
    }
    
    $sql = "UPDATE mesas SET numero = ?, estado = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$numero, $estado, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Mesa actualizada exitosamente']);
}

function deleteMesa() {
    global $pdo;
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('ID de mesa no proporcionado');
    }
    
    // Verificar si la mesa está ocupada
    $sql_check = "SELECT estado FROM mesas WHERE id = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$id]);
    $mesa = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$mesa) {
        throw new Exception('Mesa no encontrada');
    }
    
    if ($mesa['estado'] === 'ocupada') {
        throw new Exception('No se puede eliminar una mesa ocupada');
    }
    
    $sql = "DELETE FROM mesas WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Mesa eliminada exitosamente']);
}

function getProducto() {
    global $pdo;
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('ID de producto no proporcionado');
    }
    
    $sql = "SELECT * FROM productos WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }
    
    echo json_encode(['success' => true, 'producto' => $producto]);
}

function createProducto() {
    global $pdo;
    $nombre = $_POST['nombre_producto'] ?? null;
    $precio = $_POST['precio_producto'] ?? null;
    $categoria = $_POST['categoria_producto'] ?? null;
    $activo = isset($_POST['activo_producto']) ? 1 : 0;
    
    if (!$nombre || !$precio || !$categoria) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    $sql = "INSERT INTO productos (nombre, precio, categoria, activo) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nombre, $precio, $categoria, $activo]);
    
    echo json_encode(['success' => true, 'message' => 'Producto creado exitosamente']);
}

function updateProducto() {
    global $pdo;
    $id = $_POST['producto_id'] ?? null;
    $nombre = $_POST['nombre_producto'] ?? null;
    $precio = $_POST['precio_producto'] ?? null;
    $categoria = $_POST['categoria_producto'] ?? null;
    $activo = isset($_POST['activo_producto']) ? 1 : 0;
    
    if (!$id || !$nombre || !$precio || !$categoria) {
        throw new Exception('Todos los campos son requeridos');
    }
    
    $sql = "UPDATE productos SET nombre = ?, precio = ?, categoria = ?, activo = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nombre, $precio, $categoria, $activo, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Producto actualizado exitosamente']);
}

function deleteProducto() {
    global $pdo;
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('ID de producto no proporcionado');
    }
    
    // En lugar de eliminar, marcamos como inactivo
    $sql = "UPDATE productos SET activo = 0 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Producto eliminado exitosamente']);
}

function procesarPago() {
    global $pdo;
    
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
}
?>