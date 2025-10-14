<?php
include '../config/database.php';

// Obtener pedidos pendientes de pago
$sql = "SELECT p.id, p.mesa_id, m.numero as mesa_numero, 
               u.nombre as garzon_nombre, p.total, p.fecha_creacion,
               p.estado_pedido, p.estado_pago
        FROM pedidos p 
        JOIN mesas m ON p.mesa_id = m.id 
        JOIN usuarios u ON p.garzon_id = u.id 
        WHERE p.estado_pago = 'pendiente'
        ORDER BY p.fecha_creacion ASC";

$stmt = $pdo->query($sql);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
    <div class="card-header">
        <h2>📋 Pedidos Pendientes de Pago</h2>
        <div class="search-box">
            <input type="text" id="searchPedidos" class="search-input" placeholder="🔍 Buscar por mesa, garzón o total..." onkeyup="buscarPedidos()">
            <button class="btn btn-warning" onclick="limpiarBusquedaPedidos()">Limpiar</button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($pedidos)): ?>
            <div class="empty-state">
                <h3>🎉 ¡Excelente!</h3>
                <p>No hay pedidos pendientes de pago en este momento.</p>
            </div>
        <?php else: ?>
            <div class="grid" id="lista-pedidos">
                <?php foreach ($pedidos as $pedido): 
                    // Obtener items del pedido
                    $sql_items = "SELECT pi.*, pr.nombre as producto_nombre, pr.categoria 
                                  FROM pedido_items pi 
                                  JOIN productos pr ON pi.producto_id = pr.id 
                                  WHERE pi.pedido_id = ?";
                    $stmt_items = $pdo->prepare($sql_items);
                    $stmt_items->execute([$pedido['id']]);
                    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Agrupar items por categoría
                    $items_por_categoria = [];
                    foreach ($items as $item) {
                        $categoria = $item['categoria'];
                        if (!isset($items_por_categoria[$categoria])) {
                            $items_por_categoria[$categoria] = [];
                        }
                        $items_por_categoria[$categoria][] = $item;
                    }
                ?>
                    <div class="pedido-card <?php echo $pedido['estado_pedido']; ?>" 
                         id="pedido-<?php echo $pedido['id']; ?>"
                         data-mesa="<?php echo $pedido['mesa_numero']; ?>"
                         data-garzon="<?php echo $pedido['garzon_nombre']; ?>"
                         data-total="<?php echo $pedido['total']; ?>">
                        
                        <div class="card-header">
                            <h3>🍽️ Mesa <?php echo $pedido['mesa_numero']; ?></h3>
                            <span class="estado-pedido estado-<?php echo $pedido['estado_pedido']; ?>">
                                <?php 
                                $estados = [
                                    'pendiente' => '⏳ Pendiente',
                                    'preparacion' => '👨‍🍳 En Preparación',
                                    'listo' => '✅ Listo para Servir'
                                ];
                                echo $estados[$pedido['estado_pedido']] ?? $pedido['estado_pedido'];
                                ?>
                            </span>
                        </div>
                        
                        <div class="card-body">
                            <p><strong>🧑‍🍳 Garzón:</strong> <?php echo $pedido['garzon_nombre']; ?></p>
                            <p><strong>📅 Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])); ?></p>
                            
                            <div class="items-list">
                                <?php foreach ($items_por_categoria as $categoria => $items_cat): ?>
                                    <div class="item-categoria">
                                        <strong><?php echo ucfirst($categoria); ?>:</strong>
                                        <?php foreach ($items_cat as $item): ?>
                                            <div style="margin-left: 1rem;">
                                                <?php echo $item['cantidad'] . 'x ' . $item['producto_nombre']; ?> 
                                                - Bs/ <?php echo number_format($item['precio_unitario'] * $item['cantidad'], 2); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="pedido-total">
                                💰 Total: Bs/ <?php echo number_format($pedido['total'], 2); ?>
                            </div>
                            
                            <div style="margin-top: 1rem; text-align: center;">
                                <button class="btn btn-success" onclick="mostrarDetallesPago(<?php echo $pedido['id']; ?>)">
                                    💳 Procesar Pago
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>