<?php
session_start();

// Verificar si el usuario está logueado y es cajero
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cajero') {
    header('Location: ../login.php');
    exit();
}

include '../config/database.php';

// Obtener datos para las diferentes secciones
$mesas = obtenerMesas();
$pedidos = obtenerPedidosPendientes();
$productos_por_categoria = obtenerProductosPorCategoria();
$metodos_pago = obtenerMetodosPago();

// Funciones de obtención de datos
function obtenerMesas() {
    global $pdo;
    $sql = "SELECT * FROM mesas ORDER BY numero";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerPedidosPendientes() {
    global $pdo;
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
    
    // Obtener items para cada pedido
    foreach ($pedidos as &$pedido) {
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
        $pedido['items'] = $items_por_categoria;
    }
    
    return $pedidos;
}

function obtenerProductosPorCategoria() {
    global $pdo;
    $sql = "SELECT * FROM productos WHERE activo = true ORDER BY categoria, nombre";
    $stmt = $pdo->query($sql);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar por categoría
    $productos_por_categoria = [];
    foreach ($productos as $producto) {
        $categoria = $producto['categoria'];
        if (!isset($productos_por_categoria[$categoria])) {
            $productos_por_categoria[$categoria] = [];
        }
        $productos_por_categoria[$categoria][] = $producto;
    }
    
    return $productos_por_categoria;
}

function obtenerMetodosPago() {
    global $pdo;
    $sql_metodos = "SELECT * FROM metodos_pago WHERE activo = true";
    $stmt_metodos = $pdo->query($sql_metodos);
    return $stmt_metodos->fetchAll(PDO::FETCH_ASSOC);
}

$categorias_nombres = [
    'plato_principal' => '🍽️ Platos Principales',
    'acompanamiento' => '🥗 Acompañamientos',
    'bebida' => '🥤 Bebidas'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cajero</title>
    <style>
        <?php include 'cajero.css'; ?>
    </style>
</head>
<body>
    <div class="header">
        <h1>💳 Sistema Restaurante - Cajero</h1>
        <div class="user-info">
            <span>Bienvenido, <?php echo $_SESSION['user_name']; ?></span>
            <a href="../logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <div class="nav-tabs">
        <button class="nav-tab active" onclick="mostrarTab('pedidos')">📋 Pedidos Pendientes</button>
        <button class="nav-tab" onclick="mostrarTab('mesas')">🍽️ Gestión de Mesas</button>
        <button class="nav-tab" onclick="mostrarTab('menu')">📖 Gestión de Menú</button>
    </div>

    <div class="container">
        <!-- Tab: Pedidos Pendientes -->
        <div id="pedidos" class="tab-content active">
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
                            <?php foreach ($pedidos as $pedido): ?>
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
                                            <?php foreach ($pedido['items'] as $categoria => $items_cat): ?>
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
        </div>

        <!-- Tab: Gestión de Mesas -->
        <div id="mesas" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>🍽️ Gestión de Mesas</h2>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div class="search-box">
                            <input type="text" id="searchMesas" class="search-input" placeholder="🔍 Buscar por número o estado..." onkeyup="buscarMesas()">
                        </div>
                        <button class="btn btn-success" onclick="mostrarModalMesa()">
                            ➕ Nueva Mesa
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($mesas)): ?>
                        <div class="empty-state">
                            <h3>📝 No hay mesas registradas</h3>
                            <p>Comienza agregando tu primera mesa al sistema.</p>
                            <button class="btn btn-success" onclick="mostrarModalMesa()" style="margin-top: 1rem;">
                                ➕ Agregar Primera Mesa
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-4" id="lista-mesas">
                            <?php foreach ($mesas as $mesa): ?>
                                <div class="mesa-card <?php echo $mesa['estado']; ?>" 
                                     data-numero="<?php echo $mesa['numero']; ?>"
                                     data-estado="<?php echo $mesa['estado']; ?>"
                                     onclick="editarMesa(<?php echo $mesa['id']; ?>)">
                                    
                                    <h3>Mesa <?php echo $mesa['numero']; ?></h3>
                                    <p>
                                        <?php if ($mesa['estado'] == 'libre'): ?>
                                            <span class="text-success">🟢 Libre</span>
                                        <?php else: ?>
                                            <span class="text-danger">🔴 Ocupada</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: center;">
                                        <button class="btn btn-warning btn-sm" onclick="event.stopPropagation(); editarMesa(<?php echo $mesa['id']; ?>)">
                                            ✏️ Editar
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarMesa(<?php echo $mesa['id']; ?>, event)">
                                            🗑️ Eliminar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                            <h4>📊 Resumen de Mesas</h4>
                            <p><strong>Total de mesas:</strong> <?php echo count($mesas); ?></p>
                            <p><strong>Mesas libres:</strong> 
                                <?php echo count(array_filter($mesas, function($m) { return $m['estado'] == 'libre'; })); ?>
                            </p>
                            <p><strong>Mesas ocupadas:</strong> 
                                <?php echo count(array_filter($mesas, function($m) { return $m['estado'] == 'ocupada'; })); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Gestión de Menú -->
        <div id="menu" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>📖 Gestión de Menú</h2>
                    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                        <div class="search-box">
                            <input type="text" id="searchMenu" class="search-input" placeholder="🔍 Buscar productos..." onkeyup="buscarMenu()">
                            <button class="btn btn-warning" onclick="limpiarBusquedaMenu()">Limpiar</button>
                        </div>
                        <button class="btn btn-success" onclick="mostrarModalProducto()">
                            ➕ Nuevo Producto
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Filtros por categoría -->
                    <div class="categoria-filters">
                        <div class="categoria-filter active" onclick="filtrarPorCategoria('todas')">
                            Todas
                        </div>
                        <?php foreach ($categorias_nombres as $key => $nombre): ?>
                            <div class="categoria-filter" onclick="filtrarPorCategoria('<?php echo $key; ?>')">
                                <?php echo $nombre; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (empty($productos_por_categoria)): ?>
                        <div class="empty-state">
                            <h3>📝 No hay productos en el menú</h3>
                            <p>Comienza agregando tu primer producto al menú.</p>
                            <button class="btn btn-success" onclick="mostrarModalProducto()" style="margin-top: 1rem;">
                                ➕ Agregar Primer Producto
                            </button>
                        </div>
                    <?php else: ?>
                        <div id="lista-productos">
                            <?php foreach ($productos_por_categoria as $categoria => $productos_cat): ?>
                                <div class="categoria-section" data-categoria="<?php echo $categoria; ?>">
                                    <div class="categoria-header">
                                        <?php echo $categorias_nombres[$categoria] ?? ucfirst($categoria); ?>
                                        <small>(<?php echo count($productos_cat); ?> productos)</small>
                                    </div>
                                    
                                    <div class="grid grid-3">
                                        <?php foreach ($productos_cat as $producto): ?>
                                            <div class="producto-card" 
                                                 data-nombre="<?php echo strtolower($producto['nombre']); ?>"
                                                 data-precio="<?php echo $producto['precio']; ?>"
                                                 data-categoria="<?php echo $categoria; ?>">
                                                
                                                <h4><?php echo $producto['nombre']; ?></h4>
                                                <p class="text-success" style="font-size: 1.2rem; font-weight: bold;">
                                                    Bs/ <?php echo number_format($producto['precio'], 2); ?>
                                                </p>
                                                <p>
                                                    <small>
                                                        Categoría: 
                                                        <span class="text-info">
                                                            <?php echo $categorias_nombres[$categoria] ?? ucfirst($categoria); ?>
                                                        </span>
                                                    </small>
                                                </p>
                                                
                                                <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                                                    <button class="btn btn-warning btn-sm" onclick="editarProducto(<?php echo $producto['id']; ?>)">
                                                        ✏️ Editar
                                                    </button>
                                                    <button class="btn btn-danger btn-sm" onclick="eliminarProducto(<?php echo $producto['id']; ?>)">
                                                        🗑️ Eliminar
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                            <h4>📊 Resumen del Menú</h4>
                            <p><strong>Total de productos activos:</strong> <?php 
                                $total_productos = 0;
                                foreach ($productos_por_categoria as $productos_cat) {
                                    $total_productos += count($productos_cat);
                                }
                                echo $total_productos;
                            ?></p>
                            <?php foreach ($productos_por_categoria as $categoria => $productos_cat): ?>
                                <p><strong><?php echo $categorias_nombres[$categoria] ?? ucfirst($categoria); ?>:</strong> 
                                    <?php echo count($productos_cat); ?> productos
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Mesas -->
    <div id="modalMesa" class="modal">
        <div class="modal-content">
            <h3 id="tituloModalMesa">Nueva Mesa</h3>
            <form id="formMesa">
                <input type="hidden" id="mesa_id" name="mesa_id">
                <div class="form-group">
                    <label>Número de Mesa:</label>
                    <input type="number" id="numero_mesa" name="numero_mesa" class="form-control" required min="1">
                </div>
                <div class="form-group">
                    <label>Estado:</label>
                    <select id="estado_mesa" name="estado_mesa" class="form-control" required>
                        <option value="libre">Libre</option>
                        <option value="ocupada">Ocupada</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="cerrarModalMesa()">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Productos -->
    <div id="modalProducto" class="modal">
        <div class="modal-content">
            <h3 id="tituloModalProducto">Nuevo Producto</h3>
            <form id="formProducto">
                <input type="hidden" id="producto_id" name="producto_id">
                
                <div class="form-group">
                    <label for="nombre_producto">Nombre del Producto:</label>
                    <input type="text" id="nombre_producto" name="nombre_producto" class="form-control" required placeholder="Ej: Lomo Saltado">
                </div>
                
                <div class="form-group">
                    <label for="precio_producto">Precio (Bs/):</label>
                    <input type="number" id="precio_producto" name="precio_producto" class="form-control" required min="0" step="0.01" placeholder="Ej: 25.00">
                </div>
                
                <div class="form-group">
                    <label for="categoria_producto">Categoría:</label>
                    <select id="categoria_producto" name="categoria_producto" class="form-control" required>
                        <option value="">Seleccionar categoría</option>
                        <option value="plato_principal">Plato Principal</option>
                        <option value="acompanamiento">Acompañamiento</option>
                        <option value="bebida">Bebida</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="activo_producto" name="activo_producto" value="1" checked>
                        <label for="activo_producto">Producto Activo</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="cerrarModalProducto()">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Pago -->
    <div id="modalPago" class="modal">
        <div class="modal-content">
            <h3 id="tituloModalPago">💳 Procesar Pago</h3>
            <div id="detalles-pedido" class="card-body" style="max-height: 300px; overflow-y: auto; margin-bottom: 1rem;"></div>
            <form id="formPago">
                <input type="hidden" id="pedido_id_pago" name="pedido_id">
                <input type="hidden" id="total_pedido" name="total_pedido">
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Método de Pago:</label>
                        <select class="form-control" id="metodo_pago" name="metodo_pago" required onchange="toggleEfectivoSection()">
                            <option value="">Seleccionar método</option>
                            <?php foreach ($metodos_pago as $metodo): ?>
                                <option value="<?php echo $metodo['id']; ?>" data-tipo="<?php echo strtolower($metodo['nombre']); ?>">
                                    <?php echo $metodo['nombre']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Propina (opcional):</label>
                        <input type="number" class="form-control" id="propina" name="propina" min="0" step="0.01" value="0" oninput="calcularCambio()">
                    </div>
                </div>
                
                <!-- Efectivo -->
                <div class="efectivo-section hidden" id="seccion-efectivo">
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label>Monto que dio el cliente:</label>
                            <input type="number" class="form-control" id="monto_recibido" name="monto_recibido" min="0" step="0.01" placeholder="Ej: 200.00" oninput="calcularCambio()">
                        </div>
                        <div class="form-group">
                            <label>Cambio a devolver:</label>
                            <div class="cambio-info" id="cambio">Bs/ 0.00</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="cerrarModalPago()">Cancelar</button>
                    <button type="submit" class="btn btn-success" id="btnProcesarPago">✅ Procesar Pago</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        <?php include 'cajero.js'; ?>
    </script>
</body>
</html>