<?php
session_start();

// Verificar si el usuario está logueado y es garzón
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'garzon') {
    header('Location: ../login.php');
    exit();
}

include '../config/database.php';

// Obtener productos del menú
$sql_productos = "SELECT * FROM productos WHERE activo = 1 ORDER BY categoria, nombre";
$stmt_productos = $pdo->query($sql_productos);
$productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

// Obtener mesas disponibles
$sql_mesas = "SELECT * FROM mesas WHERE estado = 'libre' ORDER BY numero";
$stmt_mesas = $pdo->query($sql_mesas);
$mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);

// Variable para controlar el reset
$reset_pedido = false;

// Procesar pedido si se envía
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_pedido'])) {
    $mesa_id = $_POST['mesa_id'];
    $usuario_id = $_SESSION['user_id'];
    $items = json_decode($_POST['items'], true);
    $total = $_POST['total'];

    try {
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
        
        $mensaje = "✅ Pedido registrado exitosamente. N° de pedido: " . $pedido_id;
        $tipo_mensaje = "success";
        
        // Recargar mesas disponibles
        $stmt_mesas = $pdo->query($sql_mesas);
        $mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);
        
        // Preparar datos para impresión
        $items_impresion = [];
        foreach ($items as $producto_id => $item) {
            // Obtener información completa del producto
            $sql_producto = "SELECT nombre, categoria FROM productos WHERE id = ?";
            $stmt_producto = $pdo->prepare($sql_producto);
            $stmt_producto->execute([$producto_id]);
            $producto_info = $stmt_producto->fetch(PDO::FETCH_ASSOC);
            
            $items_impresion[] = [
                'producto_nombre' => $producto_info['nombre'],
                'categoria' => $producto_info['categoria'],
                'cantidad' => $item['cantidad'],
                'precio' => $item['precio']
            ];
        }
        
        // Guardar datos de impresión en sesión para usar en JavaScript
        $_SESSION['ultimo_pedido_impresion'] = [
            'pedido_id' => $pedido_id,
            'mesa_numero' => $_POST['mesa_numero'],
            'items' => $items_impresion
        ];
        
        // Marcar para resetear el pedido en el frontend
        $reset_pedido = true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "❌ Error al registrar el pedido: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Garzón</title>
    <style>
        /* (Mantener todos los estilos CSS anteriores igual) */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .header {
            background: linear-gradient(135deg, #ffffffff, #79a8eeff 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 1.5rem;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .logout-btn {
            background-color: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .logout-btn:hover {
            background-color: #c82333;
        }
        .container {
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            height: calc(100vh - 80px);
        }
        .left-panel {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        .right-panel {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            background: #f8f9fa;
            padding: 1.25rem;
            border-bottom: 2px solid #e9ecef;
        }
        .card-body {
            padding: 1.25rem;
        }
        .search-box {
            margin-bottom: 1rem;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .search-input:focus {
            outline: none;
            border-color: #007bff;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }
        .btn-warning {
            background: #ffc107;
            color: black;
        }
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .categoria-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
        }
        .categoria-tab {
            padding: 0.75rem 1.5rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .categoria-tab.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .categoria-tab:hover {
            background: #e9ecef;
        }
        .categoria-tab.active:hover {
            background: #0056b3;
        }
        .productos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
            padding: 0.5rem;
        }
        .producto-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.25rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .producto-card:hover {
            border-color: #007bff;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.1);
        }
        .producto-card.selected {
            border-color: #28a745;
            background: #f0fff4;
        }
        .producto-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        .producto-nombre {
            font-size: 1rem;
            font-weight: bold;
            color: #333;
            flex: 1;
        }
        .producto-precio {
            font-size: 1.1rem;
            font-weight: bold;
            color: #28a745;
        }
        .cantidad-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        .btn-cantidad {
            width: 32px;
            height: 32px;
            border: 2px solid #007bff;
            background: white;
            color: #007bff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-cantidad:hover {
            background: #007bff;
            color: white;
        }
        .cantidad-display {
            font-size: 1rem;
            font-weight: bold;
            min-width: 35px;
            text-align: center;
        }
        .mesas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 0.5rem;
        }
        .mesa-card {
            background: white;
            border: 3px solid #28a745;
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .mesa-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .mesa-card.selected {
            background: #28a745;
            color: white;
        }
        .mesa-numero {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .pedido-actual {
            background: #e7f3ff;
            border: 2px solid #007bff;
            border-radius: 12px;
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .pedido-items {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 1rem;
        }
        .pedido-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #dee2e6;
            background: white;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        .pedido-total {
            font-size: 1.3rem;
            font-weight: bold;
            color: #28a745;
            text-align: right;
            padding-top: 1rem;
            border-top: 2px solid #28a745;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        .empty-state h3 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .hidden {
            display: none !important;
        }
        .no-results {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            font-style: italic;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .categoria-content {
            display: none;
        }
        .categoria-content.active {
            display: block;
        }
        .pedido-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sistema Restaurante - Garzón</h1>
        <div class="user-info">
            <span>Bienvenido, <?php echo $_SESSION['user_name']; ?> (Garzón)</span>
            <a href="../logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <?php if (isset($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje === 'success' ? 'success' : 'error'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <div class="container">
        <!-- Panel Izquierdo: Mesas y Pedido Actual -->
        <div class="left-panel">
            <!-- Sección de Mesas -->
            <div class="card">
                <div class="card-header">
                    <h2>Mesas Disponibles</h2>
                </div>
                <div class="card-body">
                    <div class="mesas-grid" id="mesas-grid">
                        <?php if (empty($mesas)): ?>
                            <div class="empty-state">
                                <p>No hay mesas disponibles</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($mesas as $mesa): ?>
                                <div class="mesa-card" 
                                     onclick="seleccionarMesa(<?php echo $mesa['id']; ?>, <?php echo $mesa['numero']; ?>)"
                                     id="mesa-<?php echo $mesa['id']; ?>">
                                    <div class="mesa-numero"><?php echo $mesa['numero']; ?></div>
                                    <small>Disponible</small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sección de Pedido Actual -->
            <div class="card pedido-actual" id="pedido-actual" style="display: none;">
                <div class="card-header">
                    <h2>Pedido Actual - Mesa <span id="mesa-seleccionada-numero"></span></h2>
                </div>
                <div class="card-body">
                    <div class="pedido-items" id="pedido-items">
                        <!-- Los items se agregarán aquí dinámicamente -->
                    </div>
                    <div class="pedido-total" id="pedido-total">
                        Total: Bs/ 0.00
                    </div>
                    <div class="pedido-actions">
                        <button class="btn btn-warning" onclick="limpiarPedido()">Limpiar</button>
                        <button class="btn btn-success" onclick="enviarPedido()">Enviar Pedido</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Menú con Categorías -->
        <div class="right-panel">
            <div class="card">
                <div class="card-header">
                    <h2>Menú del Restaurante</h2>
                    <div class="search-box">
                        <input type="text" id="searchMenu" class="search-input" placeholder="Buscar productos...">
                        <button class="btn btn-primary" onclick="limpiarBusqueda()">Limpiar</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Tabs de Categorías -->
                    <div class="categoria-tabs">
                        <div class="categoria-tab active" onclick="mostrarCategoria('plato_principal')">
                            Plato Principal
                        </div>
                        <div class="categoria-tab" onclick="mostrarCategoria('acompanamiento')">
                            Acompañamiento
                        </div>
                        <div class="categoria-tab" onclick="mostrarCategoria('bebida')">
                            Bebidas
                        </div>
                    </div>

                    <!-- Contenido de Categorías -->
                    <div id="menu-categorias">
                        <?php 
                        $categorias = [];
                        foreach ($productos as $producto) {
                            $categorias[$producto['categoria']][] = $producto;
                        }
                        
                        if (empty($productos)): ?>
                            <div class="empty-state">
                                <h3>No hay productos en el menú</h3>
                                <p>El menú está vacío en este momento.</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $categorias_orden = ['plato_principal', 'acompanamiento', 'bebida'];
                            foreach ($categorias_orden as $categoria): ?>
                                <?php if (isset($categorias[$categoria])): ?>
                                    <div class="categoria-content <?php echo $categoria === 'plato_principal' ? 'active' : ''; ?>" 
                                         id="categoria-<?php echo $categoria; ?>"
                                         data-categoria="<?php echo $categoria; ?>">
                                        <div class="productos-grid">
                                            <?php foreach ($categorias[$categoria] as $producto): ?>
                                                <div class="producto-card" 
                                                     data-nombre="<?php echo strtolower($producto['nombre']); ?>"
                                                     data-categoria="<?php echo $producto['categoria']; ?>"
                                                     data-id="<?php echo $producto['id']; ?>"
                                                     data-precio="<?php echo $producto['precio']; ?>">
                                                    <div class="producto-header">
                                                        <div class="producto-nombre"><?php echo $producto['nombre']; ?></div>
                                                        <div class="producto-precio">Bs/ <?php echo $producto['precio']; ?></div>
                                                    </div>
                                                    <div class="cantidad-controls">
                                                        <button class="btn-cantidad" onclick="disminuirCantidad(<?php echo $producto['id']; ?>)">-</button>
                                                        <span class="cantidad-display" id="cantidad-<?php echo $producto['id']; ?>">0</span>
                                                        <button class="btn-cantidad" onclick="aumentarCantidad(<?php echo $producto['id']; ?>)">+</button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para enviar pedido -->
    <form id="formPedido" method="POST" style="display: none;">
        <input type="hidden" name="enviar_pedido" value="1">
        <input type="hidden" id="inputMesaId" name="mesa_id">
        <input type="hidden" id="inputMesaNumero" name="mesa_numero">
        <input type="hidden" id="inputItems" name="items">
        <input type="hidden" id="inputTotal" name="total">
    </form>

    <script>
        let pedidoActual = {
            mesaId: null,
            mesaNumero: null,
            items: {},
            total: 0
        };

        // Resetear el pedido después de un envío exitoso
        function resetearPedido() {
            pedidoActual = {
                mesaId: null,
                mesaNumero: null,
                items: {},
                total: 0
            };
            
            // Resetear cantidades visuales
            document.querySelectorAll('.cantidad-display').forEach(element => {
                element.textContent = '0';
            });
            
            // Remover selección de productos
            document.querySelectorAll('.producto-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Remover selección de mesa
            document.querySelectorAll('.mesa-card').forEach(mesa => {
                mesa.classList.remove('selected');
            });
            
            // Ocultar panel de pedido actual
            document.getElementById('pedido-actual').style.display = 'none';
            
            // Limpiar vista del pedido
            document.getElementById('pedido-items').innerHTML = '';
            document.getElementById('pedido-total').textContent = 'Total: Bs/ 0.00';
        }

        // Mostrar categoría
        function mostrarCategoria(categoria) {
            // Remover active de todos los tabs
            document.querySelectorAll('.categoria-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remover active de todos los contenidos
            document.querySelectorAll('.categoria-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Activar tab y contenido seleccionado
            event.target.classList.add('active');
            document.getElementById(`categoria-${categoria}`).classList.add('active');
        }

        // Búsqueda en tiempo real
        document.getElementById('searchMenu').addEventListener('input', function(e) {
            buscarProductos(e.target.value.toLowerCase());
        });

        function buscarProductos(searchTerm) {
            const productos = document.querySelectorAll('.producto-card');
            let categoriaActiva = document.querySelector('.categoria-content.active');
            let resultadosEnCategoriaActiva = false;

            productos.forEach(producto => {
                const nombre = producto.getAttribute('data-nombre');
                const categoria = producto.getAttribute('data-categoria');
                
                if (nombre.includes(searchTerm) || categoria.includes(searchTerm)) {
                    producto.style.display = 'block';
                    // Si el producto está en la categoría activa, mostrar resultados
                    if (producto.closest('.categoria-content').classList.contains('active')) {
                        resultadosEnCategoriaActiva = true;
                    }
                } else {
                    producto.style.display = 'none';
                }
            });

            // Mostrar mensaje si no hay resultados en categoría activa
            if (!resultadosEnCategoriaActiva && searchTerm !== '') {
                const categoriaContent = document.querySelector('.categoria-content.active');
                if (!categoriaContent.querySelector('.no-results')) {
                    categoriaContent.innerHTML = `
                        <div class="no-results">
                            <h3>No se encontraron productos</h3>
                            <p>No hay productos que coincidan con "${searchTerm}" en esta categoría</p>
                        </div>
                    `;
                }
            } else if (searchTerm === '') {
                limpiarBusqueda();
            }
        }

        function limpiarBusqueda() {
            document.getElementById('searchMenu').value = '';
            // Recargar la página para restaurar el estado original
            location.reload();
        }

        // Funciones de cantidad
        function aumentarCantidad(productoId) {
            const cantidadElement = document.getElementById(`cantidad-${productoId}`);
            let cantidad = parseInt(cantidadElement.textContent) || 0;
            cantidad++;
            cantidadElement.textContent = cantidad;
            
            actualizarPedido(productoId, cantidad);
        }

        function disminuirCantidad(productoId) {
            const cantidadElement = document.getElementById(`cantidad-${productoId}`);
            let cantidad = parseInt(cantidadElement.textContent) || 0;
            if (cantidad > 0) {
                cantidad--;
                cantidadElement.textContent = cantidad;
                actualizarPedido(productoId, cantidad);
            }
        }

        function actualizarPedido(productoId, cantidad) {
            const productoCard = document.querySelector(`[data-id="${productoId}"]`);
            const nombre = productoCard.querySelector('.producto-nombre').textContent;
            const precio = parseFloat(productoCard.getAttribute('data-precio'));
            const categoria = productoCard.getAttribute('data-categoria');

            if (cantidad > 0) {
                pedidoActual.items[productoId] = {
                    nombre: nombre,
                    precio: precio,
                    cantidad: cantidad,
                    subtotal: precio * cantidad,
                    categoria: categoria
                };
                productoCard.classList.add('selected');
            } else {
                delete pedidoActual.items[productoId];
                productoCard.classList.remove('selected');
            }

            actualizarVistaPedido();
        }

        function seleccionarMesa(mesaId, mesaNumero) {
            // Remover selección anterior
            document.querySelectorAll('.mesa-card').forEach(mesa => {
                mesa.classList.remove('selected');
            });
            
            // Seleccionar nueva mesa
            document.getElementById(`mesa-${mesaId}`).classList.add('selected');
            
            pedidoActual.mesaId = mesaId;
            pedidoActual.mesaNumero = mesaNumero;
            
            document.getElementById('mesa-seleccionada-numero').textContent = mesaNumero;
            document.getElementById('pedido-actual').style.display = 'flex';
        }

        function actualizarVistaPedido() {
            const pedidoItems = document.getElementById('pedido-items');
            let total = 0;
            
            pedidoItems.innerHTML = '';
            
            if (Object.keys(pedidoActual.items).length === 0) {
                pedidoItems.innerHTML = '<div class="empty-state"><p>No hay productos en el pedido</p></div>';
            } else {
                Object.values(pedidoActual.items).forEach(item => {
                    total += item.subtotal;
                    
                    const itemHTML = `
                        <div class="pedido-item">
                            <div>
                                <strong>${item.nombre}</strong>
                                <br>
                                <small>${item.cantidad} x Bs/ ${item.precio.toFixed(2)}</small>
                            </div>
                            <div>
                                <strong>Bs/ ${item.subtotal.toFixed(2)}</strong>
                            </div>
                        </div>
                    `;
                    pedidoItems.innerHTML += itemHTML;
                });
            }
            
            pedidoActual.total = total;
            document.getElementById('pedido-total').textContent = `Total: Bs/ ${total.toFixed(2)}`;
        }

        function limpiarPedido() {
            if (confirm('¿Está seguro de limpiar el pedido actual?')) {
                resetearPedido();
            }
        }

        function enviarPedido() {
            if (!pedidoActual.mesaId) {
                alert('Por favor seleccione una mesa');
                return;
            }
            
            if (Object.keys(pedidoActual.items).length === 0) {
                alert('Por favor agregue productos al pedido');
                return;
            }

            if (confirm(`¿Enviar pedido a la Mesa ${pedidoActual.mesaNumero} por Bs/ ${pedidoActual.total.toFixed(2)}?`)) {
                // Preparar datos para enviar
                document.getElementById('inputMesaId').value = pedidoActual.mesaId;
                document.getElementById('inputMesaNumero').value = pedidoActual.mesaNumero;
                document.getElementById('inputItems').value = JSON.stringify(pedidoActual.items);
                document.getElementById('inputTotal').value = pedidoActual.total;
                
                // Enviar formulario
                document.getElementById('formPedido').submit();
            }
        }

        // FUNCIÓN DE IMPRESIÓN MINIMALISTA
        function imprimirTicketMinimalista(mesaNumero, items) {
            // Agrupar items por categoría
            const itemsPorCategoria = {};
            items.forEach(item => {
                if (!itemsPorCategoria[item.categoria]) {
                    itemsPorCategoria[item.categoria] = [];
                }
                itemsPorCategoria[item.categoria].push(item);
            });
            
            // Crear ventana de impresión
            const ventanaImpresion = window.open('', '_blank');
            
            // Nombres de categorías en español
            const nombresCategorias = {
                'plato_principal': 'PLATO PRINCIPAL',
                'acompanamiento': 'ACOMPAÑAMIENTO', 
                'bebida': 'BEBIDA'
            };
            
            const contenidoTicket = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Mesa ${mesaNumero}</title>
                    <style>
                        body { 
                            font-family: 'Courier New', monospace; 
                            margin: 0; 
                            padding: 5px;
                            font-size: 16px;
                            background: white;
                            line-height: 1.2;
                        }
                        .ticket { 
                            width: 72mm; 
                            margin: 0 auto;
                        }
                        .mesa { 
                            font-size: 20px; 
                            font-weight: bold; 
                            text-align: center;
                            margin: 5px 0;
                            padding: 5px 0;
                            border-bottom: 2px solid #000;
                        }
                        .categoria-section {
                            margin: 8px 0;
                        }
                        .categoria-header {
                            font-weight: bold;
                            font-size: 14px;
                            margin: 5px 0;
                            text-transform: uppercase;
                        }
                        .item { 
                            margin: 4px 0;
                            padding-left: 2px;
                        }
                        .cantidad { 
                            font-weight: bold;
                            font-size: 14px;
                            margin-right: 8px;
                        }
                        .producto { 
                            font-weight: bold;
                            font-size: 14px;
                        }
                        .separator {
                            border-top: 1px dashed #000;
                            margin: 8px 0;
                            padding-top: 8px;
                        }
                        .timestamp {
                            font-size: 12px;
                            color: #666;
                            text-align: center;
                            margin: 5px 0;
                        }
                        @media print {
                            body { margin: 0; padding: 0; }
                            .ticket { width: 72mm; }
                        }
                    </style>
                </head>
                <body>
                    <div class="ticket">
                        <div class="mesa">MESA ${mesaNumero}</div>
                        
                        <div class="timestamp">
                            ${new Date().toLocaleString('es-ES')}
                        </div>

                        ${Object.keys(itemsPorCategoria).map(categoria => `
                            <div class="categoria-section">
                                <div class="categoria-header">
                                    ${nombresCategorias[categoria]}
                                </div>
                                ${itemsPorCategoria[categoria].map(item => `
                                    <div class="item">
                                        <span class="cantidad">${item.cantidad}x</span>
                                        <span class="producto">${item.producto_nombre}</span>
                                    </div>
                                `).join('')}
                            </div>
                        `).join('')}
                        
                        <div class="separator"></div>
                    </div>
                </body>
                </html>
            `;
            
            ventanaImpresion.document.write(contenidoTicket);
            ventanaImpresion.document.close();
            
            ventanaImpresion.onload = function() {
                setTimeout(() => {
                    ventanaImpresion.print();
                    setTimeout(() => {
                        ventanaImpresion.close();
                    }, 500);
                }, 300);
            };
        }

        // Resetear automáticamente después de un envío exitoso
        <?php if ($reset_pedido): ?>
        window.onload = function() {
            // Primero resetear el pedido actual
            resetearPedido();
            
            // Luego imprimir (si hay datos)
            <?php if (isset($_SESSION['ultimo_pedido_impresion'])): ?>
            setTimeout(() => {
                const datosImpresion = <?php echo json_encode($_SESSION['ultimo_pedido_impresion']); ?>;
                imprimirTicketMinimalista(
                    datosImpresion.mesa_numero,
                    datosImpresion.items
                );
                
                // Limpiar datos de impresión
                <?php unset($_SESSION['ultimo_pedido_impresion']); ?>
            }, 800);
            <?php endif; ?>
        };
        <?php endif; ?>
    </script>
</body>
</html>