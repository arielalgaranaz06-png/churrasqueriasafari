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
    $mesa_id = $_GET['id'] ?? null;
    
    if (!$mesa_id) {
        echo json_encode(['success' => false, 'message' => 'ID de mesa no especificado']);
        exit();
    }
    
    try {
        $sql = "SELECT * FROM mesas WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mesa_id]);
        $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($mesa) {
            echo json_encode($mesa);
        } else {
            echo json_encode(['success' => false, 'message' => 'Mesa no encontrada']);
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
            // Crear nueva mesa
            $numero = $input['numero'] ?? null;
            $estado = $input['estado'] ?? 'libre';
            
            if (!$numero) {
                echo json_encode(['success' => false, 'message' => 'Número de mesa requerido']);
                exit();
            }
            
            // Verificar si ya existe una mesa con ese número
            $sql_verificar = "SELECT id FROM mesas WHERE numero = ?";
            $stmt_verificar = $pdo->prepare($sql_verificar);
            $stmt_verificar->execute([$numero]);
            
            if ($stmt_verificar->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Ya existe una mesa con ese número']);
                exit();
            }
            
            $sql = "INSERT INTO mesas (numero, estado) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$numero, $estado]);
            
            echo json_encode(['success' => true, 'message' => 'Mesa creada correctamente']);
            
        } elseif ($action === 'editar') {
            // Editar mesa existente
            $id = $input['id'] ?? null;
            $numero = $input['numero'] ?? null;
            $estado = $input['estado'] ?? null;
            
            if (!$id || !$numero || !$estado) {
                echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
                exit();
            }
            
            // Verificar si ya existe otra mesa con ese número
            $sql_verificar = "SELECT id FROM mesas WHERE numero = ? AND id != ?";
            $stmt_verificar = $pdo->prepare($sql_verificar);
            $stmt_verificar->execute([$numero, $id]);
            
            if ($stmt_verificar->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Ya existe otra mesa con ese número']);
                exit();
            }
            
            $sql = "UPDATE mesas SET numero = ?, estado = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$numero, $estado, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Mesa actualizada correctamente']);
            
        } elseif ($action === 'eliminar') {
            // Eliminar mesa
            $id = $input['id'] ?? null;
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID de mesa no especificado']);
                exit();
            }
            
            // Verificar si la mesa tiene pedidos activos
            $sql_verificar = "SELECT id FROM pedidos WHERE mesa_id = ? AND estado != 'pagado' AND estado != 'cancelado'";
            $stmt_verificar = $pdo->prepare($sql_verificar);
            $stmt_verificar->execute([$id]);
            
            if ($stmt_verificar->fetch()) {
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar una mesa con pedidos activos']);
                exit();
            }
            
            $sql = "DELETE FROM mesas WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Mesa eliminada correctamente']);
            
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