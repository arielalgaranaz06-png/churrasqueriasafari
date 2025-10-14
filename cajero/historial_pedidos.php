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

// Verificar si el usuario está logueado y es cajero
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cajero') {
    header('Location: ../login.php');
    exit();
}

include '../config/database.php';

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$mesa_id = $_GET['mesa_id'] ?? '';
$garzon_id = $_GET['garzon_id'] ?? '';

// Construir consulta base
$sql = "
    SELECT 
        p.id AS pedido_id,
        p.fecha_pedido,
        p.fecha_pago,
        p.total,
        p.propina,
        p.monto_recibido,
        p.cambio,
        m.numero AS mesa_numero,
        u.nombre AS garzon_nombre,
        mp.nombre AS metodo_pago,
        GROUP_CONCAT(CONCAT(pi.cantidad, ' ', prod.nombre, ' (Bs/', pi.precio_unitario, ')') SEPARATOR '; ') AS productos_detalle
    FROM pedidos p
    JOIN mesas m ON p.mesa_id = m.id
    JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN metodos_pago mp ON p.metodo_pago_id = mp.id
    JOIN pedido_items pi ON p.id = pi.pedido_id
    JOIN productos prod ON pi.producto_id = prod.id
    WHERE p.estado = 'pagado'
    AND DATE(p.fecha_pago) BETWEEN ? AND ?
";

$params = [$fecha_inicio, $fecha_fin];

// Agregar filtros opcionales
if (!empty($mesa_id)) {
    $sql .= " AND m.id = ?";
    $params[] = $mesa_id;
}

if (!empty($garzon_id)) {
    $sql .= " AND u.id = ?";
    $params[] = $garzon_id;
}

$sql .= " GROUP BY p.id ORDER BY p.fecha_pago DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos_pagados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener mesas para el filtro
$sql_mesas = "SELECT * FROM mesas ORDER BY numero";
$mesas = $pdo->query($sql_mesas)->fetchAll(PDO::FETCH_ASSOC);

// Obtener garzones para el filtro
$sql_garzones = "SELECT id, nombre FROM usuarios WHERE rol = 'garzon' AND activo = true ORDER BY nombre";
$garzones = $pdo->query($sql_garzones)->fetchAll(PDO::FETCH_ASSOC);

// Si es una petición AJAX, devolver solo los resultados
if (isset($_GET['ajax'])) {
    ob_start();
    ?>
    <?php if (empty($pedidos_pagados)): ?>
        <div class="empty-state">
            <h3>No hay pedidos pagados en el período seleccionado</h3>
            <p>Intente ajustar los filtros de fecha</p>
        </div>
    <?php else: ?>
        <?php foreach ($pedidos_pagados as $pedido): ?>
            <div class="pedido-card historial">
                <div class="grid grid-2">
                    <div>
                        <h3>🍽️ Mesa <?php echo $pedido['mesa_numero']; ?> - Pedido #<?php echo $pedido['pedido_id']; ?></h3>
                        <p><strong>Garzón:</strong> <?php echo $pedido['garzon_nombre']; ?></p>
                        <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pago'])); ?></p>
                        <p><strong>Método de Pago:</strong> <?php echo $pedido['metodo_pago'] ?? 'No especificado'; ?></p>
                    </div>
                    <div style="text-align: right;">
                        <h3 class="text-success">Total: Bs/ <?php echo $pedido['total']; ?></h3>
                        <?php if ($pedido['propina'] > 0): ?>
                            <p><strong>Propina:</strong> Bs/ <?php echo $pedido['propina']; ?></p>
                        <?php endif; ?>
                        <?php if ($pedido['monto_recibido'] > 0): ?>
                            <p><strong>Recibió:</strong> Bs/ <?php echo $pedido['monto_recibido']; ?></p>
                            <p><strong>Cambio:</strong> Bs/ <?php echo $pedido['cambio']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="items-list">
                    <h4>📦 Productos Consumidos:</h4>
                    <?php 
                    $productos = explode('; ', $pedido['productos_detalle']);
                    foreach ($productos as $producto): 
                    ?>
                        <div style="font-size: 0.9rem; margin-bottom: 0.25rem;">
                            • <?php echo $producto; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    echo ob_get_clean();
    exit();
}
?>