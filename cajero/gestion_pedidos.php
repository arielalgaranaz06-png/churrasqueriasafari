<?php
session_start();
header('Content-Type: application/json');

// Verificar si el usuario está logueado y es cajero
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'obtener') {
    // Obtener datos de un pedido específico
    $pedido_id = $_GET['id'] ?? null;
    
    if (!$pedido_id) {
        echo json_encode(['success' => false, 'message' => 'ID de pedido no especificado']);
        exit();
    }
    
    try {
        // Obtener información del pedido
        $sql_pedido = "
            SELECT p.*, m.numero as mesa_numero, u.nombre as garzon_nombre 
            FROM pedidos p 
            JOIN mesas m ON p.mesa_id = m.id 
            JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.id = ? AND p.estado != 'pagado' AND p.estado != 'cancelado'
        ";
        $stmt_pedido = $pdo->prepare($sql_pedido);
        $stmt_pedido->execute([$pedido_id]);
        $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);
        
        if (!$pedido) {
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
            exit();
        }
        
        // Obtener items del pedido
        $sql_items = "
            SELECT pi.*, p.nombre as producto_nombre, p.categoria as producto_categoria, p.precio as precio_actual
            FROM pedido_items pi 
            JOIN productos p ON pi.producto_id = p.id 
            WHERE pi.pedido_id = ?
        ";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([$pedido_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener productos disponibles
        $sql_productos = "SELECT * FROM productos WHERE activo = true ORDER BY categoria, nombre";
        $productos = $pdo->query($sql_productos)->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'pedido' => $pedido,
            'items' => $items,
            'productos' => $productos
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'eliminar') {
        // Eliminar pedido
        $pedido_id = $input['id'] ?? null;
        
        if (!$pedido_id) {
            echo json_encode(['success' => false, 'message' => 'ID de pedido no especificado']);
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            // Verificar que el pedido existe y no está pagado
            $sql_verificar = "SELECT estado FROM pedidos WHERE id = ?";
            $stmt_verificar = $pdo->prepare($sql_verificar);
            $stmt_verificar->execute([$pedido_id]);
            $pedido = $stmt_verificar->fetch();
            
            if (!$pedido) {
                throw new Exception('Pedido no encontrado');
            }
            
            if ($pedido['estado'] === 'pagado') {
                throw new Exception('No se puede eliminar un pedido ya pagado');
            }
            
            // Eliminar items del pedido primero (por las restricciones de clave foránea)
            $sql_eliminar_items = "DELETE FROM pedido_items WHERE pedido_id = ?";
            $stmt_eliminar_items = $pdo->prepare($sql_eliminar_items);
            $stmt_eliminar_items->execute([$pedido_id]);
            
            // Eliminar el pedido
            $sql_eliminar_pedido = "DELETE FROM pedidos WHERE id = ?";
            $stmt_eliminar_pedido = $pdo->prepare($sql_eliminar_pedido);
            $stmt_eliminar_pedido->execute([$pedido_id]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Pedido eliminado correctamente']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($action === 'actualizar') {
        // Actualizar pedido
        $pedido_id = $input['pedido_id'] ?? null;
        $items = $input['items'] ?? [];
        
        if (!$pedido_id || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            // Verificar que el pedido existe y no está pagado
            $sql_verificar = "SELECT estado FROM pedidos WHERE id = ?";
            $stmt_verificar = $pdo->prepare($sql_verificar);
            $stmt_verificar->execute([$pedido_id]);
            $pedido = $stmt_verificar->fetch();
            
            if (!$pedido) {
                throw new Exception('Pedido no encontrado');
            }
            
            if ($pedido['estado'] === 'pagado') {
                throw new Exception('No se puede modificar un pedido ya pagado');
            }
            
            // Eliminar items actuales
            $sql_eliminar_items = "DELETE FROM pedido_items WHERE pedido_id = ?";
            $stmt_eliminar_items = $pdo->prepare($sql_eliminar_items);
            $stmt_eliminar_items->execute([$pedido_id]);
            
            // Insertar nuevos items
            $total = 0;
            $sql_insert_item = "INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
            $stmt_insert_item = $pdo->prepare($sql_insert_item);
            
            foreach ($items as $item) {
                $stmt_insert_item->execute([
                    $pedido_id,
                    $item['producto_id'],
                    $item['cantidad'],
                    $item['precio_unitario']
                ]);
                $total += $item['cantidad'] * $item['precio_unitario'];
            }
            
            // Actualizar total del pedido
            $sql_actualizar_total = "UPDATE pedidos SET total = ? WHERE id = ?";
            $stmt_actualizar_total = $pdo->prepare($sql_actualizar_total);
            $stmt_actualizar_total->execute([$total, $pedido_id]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Pedido actualizado correctamente', 'nuevo_total' => $total]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
        }
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>