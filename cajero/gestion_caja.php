<?php
session_start();
header('Content-Type: application/json');

// Verificar si el usuario está logueado y es cajero
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cajero') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

include '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'estado-actual') {
        // Obtener estado actual de caja
        try {
            $sql = "SELECT * FROM caja_control WHERE usuario_id = ? AND estado = 'abierta' ORDER BY fecha_apertura DESC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_SESSION['user_id']]);
            $caja = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($caja) {
                // Calcular resumen del turno actual
                $sql_resumen = "
                    SELECT 
                        COALESCE(SUM(CASE WHEN mp.nombre = 'Efectivo' THEN p.total ELSE 0 END), 0) as ventas_efectivo,
                        COALESCE(SUM(CASE WHEN mp.nombre != 'Efectivo' THEN p.total ELSE 0 END), 0) as ventas_otros,
                        COALESCE(SUM(p.total), 0) as total_ventas,
                        COALESCE(SUM(p.propina), 0) as total_propinas
                    FROM pedidos p 
                    LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id 
                    WHERE p.estado = 'pagado' 
                    AND p.turno = ? 
                    AND DATE(p.fecha_pago) = DATE(?)
                ";
                $stmt_resumen = $pdo->prepare($sql_resumen);
                $stmt_resumen->execute([$caja['turno'], $caja['fecha_apertura']]);
                $resumen = $stmt_resumen->fetch(PDO::FETCH_ASSOC);
                
                // Calcular efectivo en caja
                $efectivo_caja = $caja['monto_inicial'] + $resumen['ventas_efectivo'];
                
                echo json_encode([
                    'success' => true,
                    'cajaAbierta' => true,
                    'caja' => $caja,
                    'resumen' => [
                        'ventas_efectivo' => $resumen['ventas_efectivo'],
                        'ventas_otros' => $resumen['ventas_otros'],
                        'total_ventas' => $resumen['total_ventas'],
                        'total_propinas' => $resumen['total_propinas'],
                        'efectivo_caja' => $efectivo_caja
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'cajaAbierta' => false
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($action === 'preparar-cierre') {
        // Preparar datos para cierre de caja
        try {
            $sql_caja = "SELECT * FROM caja_control WHERE usuario_id = ? AND estado = 'abierta' ORDER BY fecha_apertura DESC LIMIT 1";
            $stmt_caja = $pdo->prepare($sql_caja);
            $stmt_caja->execute([$_SESSION['user_id']]);
            $caja = $stmt_caja->fetch(PDO::FETCH_ASSOC);
            
            if (!$caja) {
                echo json_encode(['success' => false, 'message' => 'No hay caja abierta']);
                exit();
            }
            
            // Obtener resumen detallado
            $sql_resumen = "
                SELECT 
                    mp.nombre as metodo_pago,
                    COUNT(p.id) as cantidad_pedidos,
                    COALESCE(SUM(p.total), 0) as total_ventas,
                    COALESCE(SUM(p.propina), 0) as total_propinas
                FROM pedidos p 
                LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id 
                WHERE p.estado = 'pagado' 
                AND p.turno = ? 
                AND DATE(p.fecha_pago) = DATE(?)
                GROUP BY mp.nombre
            ";
            $stmt_resumen = $pdo->prepare($sql_resumen);
            $stmt_resumen->execute([$caja['turno'], $caja['fecha_apertura']]);
            $resumen_metodos = $stmt_resumen->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totales
            $ventas_efectivo = 0;
            $ventas_otros = 0;
            $total_propinas = 0;
            
            foreach ($resumen_metodos as $metodo) {
                if ($metodo['metodo_pago'] === 'Efectivo') {
                    $ventas_efectivo = $metodo['total_ventas'];
                } else {
                    $ventas_otros += $metodo['total_ventas'];
                }
                $total_propinas += $metodo['total_propinas'];
            }
            
            $total_ventas = $ventas_efectivo + $ventas_otros;
            $efectivo_esperado = $caja['monto_inicial'] + $ventas_efectivo;
            
            echo json_encode([
                'success' => true,
                'caja' => $caja,
                'resumen' => [
                    'ventas_efectivo' => $ventas_efectivo,
                    'ventas_otros' => $ventas_otros,
                    'total_ventas' => $total_ventas,
                    'total_propinas' => $total_propinas,
                    'efectivo_esperado' => $efectivo_esperado,
                    'detalle_metodos' => $resumen_metodos
                ]
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($action === 'reporte-turno') {
        // Generar reporte por turno
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $turno = $_GET['turno'] ?? 'todos';
        
        try {
            $sql = "
                SELECT 
                    p.*,
                    m.numero as mesa_numero,
                    u.nombre as garzon_nombre,
                    mp.nombre as metodo_pago,
                    GROUP_CONCAT(CONCAT(pi.cantidad, 'x ', prod.nombre) SEPARATOR ', ') as productos
                FROM pedidos p
                JOIN mesas m ON p.mesa_id = m.id
                JOIN usuarios u ON p.usuario_id = u.id
                LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
                JOIN pedido_items pi ON p.id = pi.pedido_id
                JOIN productos prod ON pi.producto_id = prod.id
                WHERE p.estado = 'pagado'
                AND DATE(p.fecha_pago) = ?
            ";
            
            $params = [$fecha];
            
            if ($turno !== 'todos') {
                $sql .= " AND p.turno = ?";
                $params[] = $turno;
            }
            
            $sql .= " GROUP BY p.id ORDER BY p.fecha_pago";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular totales
            $sql_totales = "
                SELECT 
                    COALESCE(SUM(p.total), 0) as total_ventas,
                    COALESCE(SUM(p.propina), 0) as total_propinas,
                    COUNT(p.id) as cantidad_pedidos
                FROM pedidos p
                WHERE p.estado = 'pagado'
                AND DATE(p.fecha_pago) = ?
            ";
            
            $params_totales = [$fecha];
            
            if ($turno !== 'todos') {
                $sql_totales .= " AND p.turno = ?";
                $params_totales[] = $turno;
            }
            
            $stmt_totales = $pdo->prepare($sql_totales);
            $stmt_totales->execute($params_totales);
            $totales = $stmt_totales->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'pedidos' => $pedidos,
                'totales' => $totales,
                'fecha' => $fecha,
                'turno' => $turno
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'apertura') {
        // Abrir caja
        $monto_inicial = $input['monto_inicial'] ?? null;
        $turno = $input['turno'] ?? null;
        
        if (!$monto_inicial || !$turno) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit();
        }
        
        try {
            // Verificar si ya hay una caja abierta para este turno
            $sql_verificar = "
                SELECT id FROM caja_control 
                WHERE usuario_id = ? AND estado = 'abierta' AND turno = ? AND DATE(fecha_apertura) = CURDATE()
            ";
            $stmt_verificar = $pdo->prepare($sql_verificar);
            $stmt_verificar->execute([$_SESSION['user_id'], $turno]);
            
            if ($stmt_verificar->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Ya hay una caja abierta para este turno']);
                exit();
            }
            
            // Insertar nueva caja
            $sql = "INSERT INTO caja_control (usuario_id, monto_inicial, turno) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_SESSION['user_id'], $monto_inicial, $turno]);
            
            // Registrar movimiento de apertura
            $caja_id = $pdo->lastInsertId();
            $sql_movimiento = "
                INSERT INTO caja_movimientos (caja_control_id, tipo, monto, descripcion) 
                VALUES (?, 'apertura', ?, 'Apertura de caja - Turno " . ucfirst($turno) . "')
            ";
            $stmt_movimiento = $pdo->prepare($sql_movimiento);
            $stmt_movimiento->execute([$caja_id, $monto_inicial]);
            
            echo json_encode(['success' => true, 'message' => 'Caja abierta correctamente']);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($action === 'cierre') {
        // Cerrar caja
        $monto_final = $input['monto_final'] ?? null;
        $observaciones = $input['observaciones'] ?? '';
        
        if (!$monto_final) {
            echo json_encode(['success' => false, 'message' => 'Monto final requerido']);
            exit();
        }
        
        try {
            // Obtener caja actual
            $sql_caja = "SELECT * FROM caja_control WHERE usuario_id = ? AND estado = 'abierta' ORDER BY fecha_apertura DESC LIMIT 1";
            $stmt_caja = $pdo->prepare($sql_caja);
            $stmt_caja->execute([$_SESSION['user_id']]);
            $caja = $stmt_caja->fetch(PDO::FETCH_ASSOC);
            
            if (!$caja) {
                echo json_encode(['success' => false, 'message' => 'No hay caja abierta']);
                exit();
            }
            
            // Calcular diferencia
            $sql_ventas = "
                SELECT COALESCE(SUM(p.total), 0) as ventas_efectivo
                FROM pedidos p 
                LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id 
                WHERE p.estado = 'pagado' 
                AND p.turno = ? 
                AND DATE(p.fecha_pago) = DATE(?)
                AND mp.nombre = 'Efectivo'
            ";
            $stmt_ventas = $pdo->prepare($sql_ventas);
            $stmt_ventas->execute([$caja['turno'], $caja['fecha_apertura']]);
            $ventas = $stmt_ventas->fetch(PDO::FETCH_ASSOC);
            
            $efectivo_esperado = $caja['monto_inicial'] + $ventas['ventas_efectivo'];
            $diferencia = $monto_final - $efectivo_esperado;
            
            // Actualizar caja
            $sql_actualizar = "
                UPDATE caja_control 
                SET estado = 'cerrada', 
                    fecha_cierre = NOW(),
                    monto_final = ?,
                    observaciones = ?
                WHERE id = ?
            ";
            $stmt_actualizar = $pdo->prepare($sql_actualizar);
            $stmt_actualizar->execute([$monto_final, $observaciones, $caja['id']]);
            
            // Registrar movimiento de cierre
            $sql_movimiento = "
                INSERT INTO caja_movimientos (caja_control_id, tipo, monto, descripcion) 
                VALUES (?, 'cierre', ?, 'Cierre de caja - Diferencia: " . number_format($diferencia, 2) . "')
            ";
            $stmt_movimiento = $pdo->prepare($sql_movimiento);
            $stmt_movimiento->execute([$caja['id'], $monto_final]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Caja cerrada correctamente',
                'diferencia' => $diferencia
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
?>