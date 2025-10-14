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

// Construir consulta
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
        GROUP_CONCAT(CONCAT(pi.cantidad, 'x ', prod.nombre, ' (Bs/', pi.precio_unitario, ')') SEPARATOR ', ') AS productos
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
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configurar headers para descarga Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="historial_pedidos_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <table border="1">
        <tr>
            <th>ID Pedido</th>
            <th>Mesa</th>
            <th>Garzón</th>
            <th>Fecha Pago</th>
            <th>Método Pago</th>
            <th>Productos</th>
            <th>Total</th>
            <th>Propina</th>
            <th>Monto Recibido</th>
            <th>Cambio</th>
        </tr>
        <?php foreach ($pedidos as $pedido): ?>
        <tr>
            <td><?php echo $pedido['pedido_id']; ?></td>
            <td>Mesa <?php echo $pedido['mesa_numero']; ?></td>
            <td><?php echo $pedido['garzon_nombre']; ?></td>
            <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pago'])); ?></td>
            <td><?php echo $pedido['metodo_pago'] ?? 'No especificado'; ?></td>
            <td><?php echo $pedido['productos']; ?></td>
            <td>Bs/ <?php echo $pedido['total']; ?></td>
            <td>Bs/ <?php echo $pedido['propina']; ?></td>
            <td>Bs/ <?php echo $pedido['monto_recibido']; ?></td>
            <td>Bs/ <?php echo $pedido['cambio']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>