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
    $producto_id = $_GET['id'] ?? null;
    
    if (!$producto_id) {
        echo json_encode(['success' => false, 'message' => 'ID de producto no especificado']);
        exit();
    }
    
    try {
        $sql = "SELECT * FROM productos WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$producto_id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($producto) {
            echo json_encode($producto);
        } else {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    try {
        if ($action === 'crear') {
            // Crear nuevo producto
            $nombre = $input['nombre'] ?? null;
            $precio = $input['precio'] ?? null;
            $categoria = $input['categoria'] ?? null;
            $activo = $input['activo'] ?? 1;
            
            if (!$nombre || !$precio || !$categoria) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
                exit();
            }
            
            // Verificar si ya existe un producto con ese nombre
            $sql_verificar = "SELECT id FROM productos WHERE nombre = ?";
            $stmt_verificar = $pdo->prepare($sql_verificar);
            $stmt_verificar->execute([$nombre]);
            
            if ($stmt_verificar->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Ya existe un producto con ese nombre']);
                exit();
            }
            
            $sql = "INSERT INTO productos (nombre, precio, categoria, activo) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $precio, $categoria, $activo]);
            
            echo json_encode(['success' => true, 'message' => 'Producto creado correctamente']);
            
        } elseif ($action === 'editar') {
            // Editar producto existente
            $id = $input['id'] ?? null;
            $nombre = $input['nombre'] ?? null;
            $precio = $input['precio'] ?? null;
            $categoria = $input['categoria'] ?? null;
            $activo = $input['activo'] ?? 1;
            
            if (!$id || !$nombre || !$precio || !$categoria) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
                exit();
            }
            
            // Verificar si ya existe otro producto con ese nombre
            $sql_verificar = "SELECT id FROM productos WHERE nombre = ? AND id != ?";
            $stmt_verificar = $pdo->prepare($sql_verificar);
            $stmt_verificar->execute([$nombre, $id]);
            
            if ($stmt_verificar->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otro producto con ese nombre']);
                exit();
            }
            
            $sql = "UPDATE productos SET nombre = ?, precio = ?, categoria = ?, activo = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $precio, $categoria, $activo, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Producto actualizado correctamente']);
            
        } elseif ($action === 'eliminar') {
            // Eliminar producto
            $id = $input['id'] ?? null;
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID de producto no especificado']);
                exit();
            }
            
            // Verificar si el producto está en pedidos activos
            $sql_verificar = "
                SELECT pi.id 
                FROM pedido_items pi 
                JOIN pedidos p ON pi.pedido_id = p.id 
                WHERE pi.producto_id = ? AND p.estado != 'pagado' AND p.estado != 'cancelado'
            ";
            $stmt_verificar = $pdo->prepare($sql_verificar);
            $stmt_verificar->execute([$id]);
            
            if ($stmt_verificar->fetch()) {
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar un producto que está en pedidos activos']);
                exit();
            }
            
            $sql = "DELETE FROM productos WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Producto eliminado correctamente']);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>