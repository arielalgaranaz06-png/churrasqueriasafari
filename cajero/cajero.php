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

// Obtener TODOS los pedidos pendientes (no pagados)
$sql_pedidos = "
    SELECT p.*, m.numero as mesa_numero, u.nombre as garzon_nombre 
    FROM pedidos p 
    JOIN mesas m ON p.mesa_id = m.id 
    JOIN usuarios u ON p.usuario_id = u.id 
    WHERE p.estado != 'pagado' AND p.estado != 'cancelado'
    ORDER BY p.fecha_pedido ASC
";
$stmt_pedidos = $pdo->query($sql_pedidos);
$pedidos_pendientes = $stmt_pedidos->fetchAll(PDO::FETCH_ASSOC);

// Obtener métodos de pago
$sql_metodos = "SELECT * FROM metodos_pago WHERE activo = true";
$stmt_metodos = $pdo->query($sql_metodos);
$metodos_pago = $stmt_metodos->fetchAll(PDO::FETCH_ASSOC);

// Obtener mesas para la sección de gestión
$sql_mesas = "SELECT * FROM mesas ORDER BY numero";
$stmt_mesas = $pdo->query($sql_mesas);
$mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos para la sección de menú
$sql_productos = "SELECT * FROM productos ORDER BY categoria, nombre";
$stmt_productos = $pdo->query($sql_productos);
$productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

// Obtener garzones para el filtro del historial
$sql_garzones = "SELECT id, nombre FROM usuarios WHERE rol = 'garzon' AND activo = true ORDER BY nombre";
$garzones = $pdo->query($sql_garzones)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cajero</title>
    <style>
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
            background: linear-gradient(135deg, #28a745, #1e7e34);
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
        .nav-tabs {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 2px solid #dee2e6;
            display: flex;
            gap: 1rem;
        }
        .nav-tab {
            padding: 0.5rem 1rem;
            background: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }
        .nav-tab.active {
            background: #28a745;
            color: white;
        }
        .nav-tab:hover {
            background: #e9ecef;
        }
        .nav-tab.active:hover {
            background: #218838;
        }
        .container {
            padding: 2rem;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .card-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-body {
            padding: 1rem;
        }
        .search-box {
            margin-bottom: 1rem;
            display: flex;
            gap: 10px;
        }
        .search-input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: background-color 0.3s;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
        .btn-warning {
            background: #ffc107;
            color: black;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background: #138496;
        }
        .pedido-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .pedido-total {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            text-align: right;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #28a745;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
        }
        .pago-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }
        .grid {
            display: grid;
            gap: 1rem;
        }
        .grid-2 {
            grid-template-columns: 1fr 1fr;
        }
        .grid-3 {
            grid-template-columns: 1fr 1fr 1fr;
        }
        .grid-4 {
            grid-template-columns: 1fr 1fr 1fr 1fr;
        }
        .hidden {
            display: none !important;
        }
        .cambio-info {
            background: #d4edda;
            color: #155724;
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            font-weight: bold;
        }
        .cambio-negativo {
            background: #f8d7da;
            color: #721c24;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        .empty-state h3 {
            margin-bottom: 1rem;
        }
        .mesa-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .mesa-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        .mesa-card.ocupada {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .mesa-card.libre {
            border-color: #28a745;
            background: #f8fff9;
        }
        .pedido-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        .pedido-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        .pedido-card.pendiente {
            border-color: #ffc107;
            background: #fffbf0;
        }
        .pedido-card.preparacion {
            border-color: #fd7e14;
            background: #fff4e6;
        }
        .pedido-card.listo {
            border-color: #28a745;
            background: #f0fff4;
        }
        .pedido-card.historial {
            border-color: #17a2b8;
            background: #f0f9ff;
        }
        .producto-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .categoria-section {
            margin-bottom: 2rem;
        }
        .categoria-header {
            background: #e9ecef;
            padding: 0.75rem 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .categoria-filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .categoria-filter {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .categoria-filter.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .categoria-filter:hover {
            background: #e9ecef;
        }
        .categoria-filter.active:hover {
            background: #0056b3;
        }
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-warning { color: #ffc107; }
        .text-info { color: #17a2b8; }
        small { font-size: 0.875rem; color: #6c757d; }
        .estado-pedido {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            display: inline-block;
            margin-bottom: 0.5rem;
        }
        .estado-pendiente { background: #fff3cd; color: #856404; }
        .estado-preparacion { background: #ffeaa7; color: #856404; }
        .estado-listo { background: #d1ecf1; color: #0c5460; }
        .items-list {
            max-height: 200px;
            overflow-y: auto;
            margin: 1rem 0;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .item-categoria {
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #dee2e6;
        }
        .item-categoria:last-child {
            border-bottom: none;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        .item-pedido {
            transition: background-color 0.3s;
        }
        .item-pedido:hover {
            background-color: #f8f9fa;
        }
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
        <button class="nav-tab" onclick="mostrarTab('historial')">📊 Historial de Pagos</button>        
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
                        <input type="text" id="searchPedidos" class="search-input" placeholder="Buscar por mesa, garzón o total..." oninput="buscarPedidos()">
                        <button class="btn" onclick="limpiarBusquedaPedidos()">🔄 Limpiar</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="lista-pedidos" class="grid grid-3">
                        <?php if (empty($pedidos_pendientes)): ?>
                            <div class="empty-state" style="grid-column: 1 / -1;">
                                <h3>🎉 No hay pedidos pendientes</h3>
                                <p>Todos los pedidos han sido pagados o no hay pedidos activos.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pedidos_pendientes as $pedido): ?>
                                <?php
                                // Obtener items del pedido
                                $sql_items = "
                                    SELECT pi.*, p.nombre as producto_nombre, p.categoria as producto_categoria
                                    FROM pedido_items pi 
                                    JOIN productos p ON pi.producto_id = p.id 
                                    WHERE pi.pedido_id = ?
                                ";
                                $stmt_items = $pdo->prepare($sql_items);
                                $stmt_items->execute([$pedido['id']]);
                                $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Determinar clase del estado
                                $estado_clase = '';
                                $estado_texto = '';
                                $card_clase = '';
                                switch($pedido['estado']) {
                                    case 'pendiente':
                                        $estado_clase = 'estado-pendiente';
                                        $estado_texto = '⏳ Pendiente';
                                        $card_clase = 'pendiente';
                                        break;
                                    case 'preparacion':
                                        $estado_clase = 'estado-preparacion';
                                        $estado_texto = '👨‍🍳 En Preparación';
                                        $card_clase = 'preparacion';
                                        break;
                                    case 'listo':
                                        $estado_clase = 'estado-listo';
                                        $estado_texto = '✅ Listo para Pagar';
                                        $card_clase = 'listo';
                                        break;
                                    default:
                                        $estado_clase = 'estado-pendiente';
                                        $estado_texto = $pedido['estado'];
                                        $card_clase = 'pendiente';
                                }
                                
                                // Agrupar items por categoría
                                $items_por_categoria = [];
                                foreach ($items as $item) {
                                    $categoria = $item['producto_categoria'];
                                    if (!isset($items_por_categoria[$categoria])) {
                                        $items_por_categoria[$categoria] = [];
                                    }
                                    $items_por_categoria[$categoria][] = $item;
                                }
                                ?>
                                
                                <div class="pedido-card <?php echo $card_clase; ?>" id="pedido-<?php echo $pedido['id']; ?>" 
                                     data-mesa="<?php echo $pedido['mesa_numero']; ?>"
                                     data-garzon="<?php echo $pedido['garzon_nombre']; ?>"
                                     data-total="<?php echo $pedido['total']; ?>">
                                    
                                    <div class="estado-pedido <?php echo $estado_clase; ?>">
                                        <?php echo $estado_texto; ?>
                                    </div>
                                    
                                    <h3>🍽️ Mesa <?php echo $pedido['mesa_numero']; ?></h3>
                                    <p><strong>Pedido #<?php echo $pedido['id']; ?></strong></p>
                                    <p><strong>Garzón:</strong> <?php echo $pedido['garzon_nombre']; ?></p>
                                    
                                    <div class="items-list">
                                        <h4>📦 Consumió:</h4>
                                        <?php foreach ($items_por_categoria as $categoria => $items_categoria): 
                                            $iconos = [
                                                'plato_principal' => '🍽️',
                                                'acompanamiento' => '🥗',
                                                'bebida' => '🥤',
                                                'postre' => '🍰'
                                            ];
                                            $icono = $iconos[$categoria] ?? '📋';
                                        ?>
                                            <div class="item-categoria">
                                                <small><strong><?php echo $icono . ' ' . ucfirst(str_replace('_', ' ', $categoria)); ?></strong></small>
                                                <?php foreach ($items_categoria as $item): ?>
                                                    <div style="font-size: 0.8rem; margin-left: 0.5rem;">
                                                        • <?php echo $item['producto_nombre']; ?> 
                                                        (<?php echo $item['cantidad']; ?> x Bs/ <?php echo $item['precio_unitario']; ?>)
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div style="text-align: center; margin: 1rem 0;">
                                        <h3 class="text-success">Total: Bs/ <?php echo $pedido['total']; ?></h3>
                                    </div>

                                    <!-- Botones para editar, eliminar y pagar -->
                                    <div style="display: flex; gap: 0.5rem; justify-content: center; margin-top: 1rem;">
                                        <button class="btn btn-warning" onclick="editarPedido(<?php echo $pedido['id']; ?>)">
                                            ✏️ Editar
                                        </button>
                                        <button class="btn btn-danger" onclick="eliminarPedido(<?php echo $pedido['id']; ?>)">
                                            🗑️ Eliminar
                                        </button>
                                        <button class="btn btn-success" onclick="mostrarDetallesPago(<?php echo $pedido['id']; ?>)">
                                            💳 Pagar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Historial de Pagos -->
        <div id="historial" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>📊 Historial de Pedidos Pagados</h2>
                </div>
                <div class="card-body">
                    <!-- Filtros -->
                    <div class="grid grid-4" style="margin-bottom: 1rem; gap: 1rem;">
                        <div class="form-group">
                            <label>Fecha Inicio:</label>
                            <input type="date" id="fecha_inicio" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Fecha Fin:</label>
                            <input type="date" id="fecha_fin" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Mesa:</label>
                            <select id="filtro_mesa" class="form-control">
                                <option value="">Todas las mesas</option>
                                <?php foreach ($mesas as $mesa): ?>
                                    <option value="<?php echo $mesa['id']; ?>">Mesa <?php echo $mesa['numero']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Garzón:</label>
                            <select id="filtro_garzon" class="form-control">
                                <option value="">Todos los garzones</option>
                                <?php foreach ($garzones as $garzon): ?>
                                    <option value="<?php echo $garzon['id']; ?>"><?php echo $garzon['nombre']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <button class="btn btn-primary" onclick="filtrarHistorial()">🔍 Filtrar</button>
                        <button class="btn" onclick="limpiarFiltros()">🔄 Limpiar</button>
                        <button class="btn btn-success" onclick="exportarHistorial()">📊 Exportar a Excel</button>
                    </div>
                    
                    <!-- Resultados -->
                    <div id="resultados-historial">
                        <div class="empty-state">
                            <h3>Seleccione filtros y haga clic en "Filtrar"</h3>
                            <p>Los resultados aparecerán aquí</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Gestión de Mesas -->
        <div id="mesas" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>🍽️ Gestión de Mesas</h2>
                    <div style="display: flex; gap: 1rem;">
                        <div class="search-box">
                            <input type="text" id="searchMesas" class="search-input" placeholder="Buscar por número de mesa..." oninput="buscarMesas()">
                        </div>
                        <button class="btn btn-success" onclick="mostrarModalMesa()">➕ Nueva Mesa</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="lista-mesas" class="grid grid-4">
                        <?php if (empty($mesas)): ?>
                            <div class="empty-state">
                                <h3>No hay mesas registradas</h3>
                                <p>Crea la primera mesa usando el botón "Nueva Mesa"</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($mesas as $mesa): ?>
                                <div class="mesa-card <?php echo $mesa['estado']; ?>" 
                                     data-numero="<?php echo $mesa['numero']; ?>"
                                     data-estado="<?php echo $mesa['estado']; ?>"
                                     onclick="editarMesa(<?php echo $mesa['id']; ?>)">
                                    <h3>🍽️ Mesa <?php echo $mesa['numero']; ?></h3>
                                    <p>Estado: 
                                        <strong class="<?php echo $mesa['estado'] === 'ocupada' ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo $mesa['estado']; ?>
                                        </strong>
                                    </p>
                                    <button class="btn btn-danger" onclick="eliminarMesa(<?php echo $mesa['id']; ?>, event)">🗑️ Eliminar</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Gestión de Menú -->
        <div id="menu" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2>📖 Gestión de Menú</h2>
                    <div style="display: flex; gap: 1rem;">
                        <div class="search-box">
                            <input type="text" id="searchMenu" class="search-input" placeholder="Buscar productos..." oninput="buscarMenu()">
                            <button class="btn" onclick="limpiarBusquedaMenu()">🔄 Limpiar</button>
                        </div>
                        <button class="btn btn-success" onclick="mostrarModalProducto()">➕ Nuevo Producto</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filtros de categorías -->
                    <div class="categoria-filters">
                        <button class="categoria-filter active" data-categoria="todas" onclick="filtrarPorCategoria('todas')">📋 Todas</button>
                        <button class="categoria-filter" data-categoria="plato_principal" onclick="filtrarPorCategoria('plato_principal')">🍽️ Plato Principal</button>
                        <button class="categoria-filter" data-categoria="acompanamiento" onclick="filtrarPorCategoria('acompanamiento')">🥗 Acompañamiento</button>
                        <button class="categoria-filter" data-categoria="bebida" onclick="filtrarPorCategoria('bebida')">🥤 Bebida</button>
                        <button class="categoria-filter" data-categoria="postre" onclick="filtrarPorCategoria('postre')">🍰 Postre</button>
                    </div>
                    
                    <div id="lista-productos">
                        <?php 
                        $categorias = [];
                        foreach ($productos as $producto) {
                            $categorias[$producto['categoria']][] = $producto;
                        }
                        
                        if (empty($productos)): ?>
                            <div class="empty-state">
                                <h3>No hay productos en el menú</h3>
                                <p>Crea el primer producto usando el botón "Nuevo Producto"</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($categorias as $categoria => $productos_categoria): ?>
                                <div class="categoria-section" data-categoria="<?php echo $categoria; ?>">
                                    <div class="categoria-header">
                                        <?php 
                                        $iconos = [
                                            'plato_principal' => '🍽️',
                                            'acompanamiento' => '🥗',
                                            'bebida' => '🥤',
                                            'postre' => '🍰'
                                        ];
                                        echo ($iconos[$categoria] ?? '📋') . ' ' . ucfirst(str_replace('_', ' ', $categoria));
                                        ?>
                                    </div>
                                    <div class="grid grid-2">
                                        <?php foreach ($productos_categoria as $producto): ?>
                                            <div class="producto-card" 
                                                 data-nombre="<?php echo strtolower($producto['nombre']); ?>"
                                                 data-precio="<?php echo $producto['precio']; ?>"
                                                 data-categoria="<?php echo $producto['categoria']; ?>">
                                                <h3><?php echo $producto['nombre']; ?></h3>
                                                <p><strong>Precio:</strong> Bs/ <?php echo $producto['precio']; ?></p>
                                                <p><strong>Categoría:</strong> <?php echo ucfirst(str_replace('_', ' ', $producto['categoria'])); ?></p>
                                                <p><strong>Estado:</strong> <?php echo $producto['activo'] ? '✅ Activo' : '❌ Inactivo'; ?></p>
                                                <div>
                                                    <button class="btn btn-primary" onclick="editarProducto(<?php echo $producto['id']; ?>)">✏️ Editar</button>
                                                    <button class="btn btn-danger" onclick="eliminarProducto(<?php echo $producto['id']; ?>)">🗑️ Eliminar</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
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
                        <option value="postre">Postre</option>
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
                    <button type="submit" class="btn btn-success">✅ Procesar Pago</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Editar Pedido -->
    <div id="modalEditarPedido" class="modal">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
            <h3 id="tituloModalEditarPedido">✏️ Editar Pedido</h3>
            <div id="detalles-editar-pedido" class="card-body"></div>
            
            <form id="formEditarPedido">
                <input type="hidden" id="pedido_id_editar" name="pedido_id">
                
                <div class="form-group">
                    <label>Productos del Pedido:</label>
                    <div id="lista-productos-pedido" class="items-list" style="max-height: 300px;">
                        <!-- Los productos se cargarán aquí dinámicamente -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Agregar Producto:</label>
                    <div class="grid grid-3" style="gap: 0.5rem; margin-bottom: 1rem;">
                        <select id="nuevo_producto" class="form-control">
                            <option value="">Seleccionar producto</option>
                        </select>
                        <input type="number" id="nueva_cantidad" class="form-control" min="1" value="1" placeholder="Cantidad">
                        <button type="button" class="btn btn-primary" onclick="agregarProductoAlPedido()">➕ Agregar</button>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="cerrarModalEditarPedido()">Cancelar</button>
                    <button type="submit" class="btn btn-success">💾 Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script src="cajero.js"></script>
    <script>
        // Navegación entre tabs
        function mostrarTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.nav-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
            
            // Si es la pestaña de historial, cargar los datos
            if (tabName === 'historial') {
                filtrarHistorial();
            }
        }

        // Modal functions
        function mostrarModalMesa() {
            document.getElementById('modalMesa').classList.add('active');
            document.getElementById('tituloModalMesa').textContent = 'Nueva Mesa';
            document.getElementById('formMesa').reset();
            document.getElementById('mesa_id').value = '';
        }

        function cerrarModalMesa() {
            document.getElementById('modalMesa').classList.remove('active');
        }

        function mostrarModalProducto() {
            document.getElementById('modalProducto').classList.add('active');
            document.getElementById('tituloModalProducto').textContent = 'Nuevo Producto';
            document.getElementById('formProducto').reset();
            document.getElementById('producto_id').value = '';
            document.getElementById('activo_producto').checked = true;
        }

        function cerrarModalProducto() {
            document.getElementById('modalProducto').classList.remove('active');
        }

        function mostrarModalPago() {
            document.getElementById('modalPago').classList.add('active');
        }

        function cerrarModalPago() {
            document.getElementById('modalPago').classList.remove('active');
        }

        function mostrarModalEditarPedido(data) {
            const pedido = data.pedido;
            const items = data.items;
            const productos = data.productos;
            
            document.getElementById('tituloModalEditarPedido').textContent = `✏️ Editar Pedido - Mesa ${pedido.mesa_numero}`;
            document.getElementById('pedido_id_editar').value = pedido.id;
            
            // Mostrar información del pedido
            document.getElementById('detalles-editar-pedido').innerHTML = `
                <div class="grid grid-2">
                    <div>
                        <h4>🍽️ Mesa ${pedido.mesa_numero}</h4>
                        <p><strong>Garzón:</strong> ${pedido.garzon_nombre}</p>
                        <p><strong>Estado:</strong> ${pedido.estado}</p>
                    </div>
                    <div style="text-align: right;">
                        <h3 class="text-success">Total: Bs/ ${pedido.total}</h3>
                    </div>
                </div>
            `;
            
            // Cargar productos en el select
            const selectProductos = document.getElementById('nuevo_producto');
            selectProductos.innerHTML = '<option value="">Seleccionar producto</option>';
            productos.forEach(producto => {
                const option = document.createElement('option');
                option.value = producto.id;
                option.textContent = `${producto.nombre} - Bs/ ${producto.precio}`;
                option.setAttribute('data-precio', producto.precio);
                selectProductos.appendChild(option);
            });
            
            // Cargar items actuales del pedido
            cargarItemsPedido(items);
            
            document.getElementById('modalEditarPedido').classList.add('active');
        }

        function cargarItemsPedido(items) {
            const contenedor = document.getElementById('lista-productos-pedido');
            contenedor.innerHTML = '';
            
            if (items.length === 0) {
                contenedor.innerHTML = '<p class="empty-state">No hay productos en este pedido</p>';
                return;
            }
            
            items.forEach((item, index) => {
                const div = document.createElement('div');
                div.className = 'item-pedido';
                div.style.display = 'flex';
                div.style.justifyContent = 'space-between';
                div.style.alignItems = 'center';
                div.style.padding = '0.5rem';
                div.style.borderBottom = '1px solid #eee';
                div.innerHTML = `
                    <div>
                        <strong>${item.producto_nombre}</strong><br>
                        <small>Cantidad: ${item.cantidad} x Bs/ ${item.precio_unitario} = Bs/ ${(item.cantidad * item.precio_unitario).toFixed(2)}</small>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="eliminarItemPedido(${index})">🗑️</button>
                    <input type="hidden" name="items[${index}][producto_id]" value="${item.producto_id}">
                    <input type="hidden" name="items[${index}][cantidad]" value="${item.cantidad}">
                    <input type="hidden" name="items[${index}][precio_unitario]" value="${item.precio_unitario}">
                `;
                contenedor.appendChild(div);
            });
        }

        function agregarProductoAlPedido() {
            const select = document.getElementById('nuevo_producto');
            const cantidadInput = document.getElementById('nueva_cantidad');
            const productoId = select.value;
            const cantidad = parseInt(cantidadInput.value);
            
            if (!productoId || cantidad < 1) {
                alert('❌ Por favor seleccione un producto y una cantidad válida');
                return;
            }
            
            const productoNombre = select.options[select.selectedIndex].text;
            const precio = parseFloat(select.options[select.selectedIndex].getAttribute('data-precio'));
            
            const contenedor = document.getElementById('lista-productos-pedido');
            const items = contenedor.querySelectorAll('.item-pedido');
            const nuevoIndex = items.length;
            
            const div = document.createElement('div');
            div.className = 'item-pedido';
            div.style.display = 'flex';
            div.style.justifyContent = 'space-between';
            div.style.alignItems = 'center';
            div.style.padding = '0.5rem';
            div.style.borderBottom = '1px solid #eee';
            div.innerHTML = `
                <div>
                    <strong>${productoNombre}</strong><br>
                    <small>Cantidad: ${cantidad} x Bs/ ${precio} = Bs/ ${(cantidad * precio).toFixed(2)}</small>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="eliminarItemPedido(${nuevoIndex})">🗑️</button>
                <input type="hidden" name="items[${nuevoIndex}][producto_id]" value="${productoId}">
                <input type="hidden" name="items[${nuevoIndex}][cantidad]" value="${cantidad}">
                <input type="hidden" name="items[${nuevoIndex}][precio_unitario]" value="${precio}">
            `;
            
            contenedor.appendChild(div);
            
            // Limpiar formulario
            select.value = '';
            cantidadInput.value = '1';
        }

        function eliminarItemPedido(index) {
            const items = document.querySelectorAll('.item-pedido');
            if (items[index]) {
                items[index].remove();
                
                // Reindexar los items restantes
                const itemsRestantes = document.querySelectorAll('.item-pedido');
                itemsRestantes.forEach((item, newIndex) => {
                    const inputs = item.querySelectorAll('input[type="hidden"]');
                    inputs[0].name = `items[${newIndex}][producto_id]`;
                    inputs[1].name = `items[${newIndex}][cantidad]`;
                    inputs[2].name = `items[${newIndex}][precio_unitario]`;
                    
                    const boton = item.querySelector('button');
                    boton.setAttribute('onclick', `eliminarItemPedido(${newIndex})`);
                });
            }
        }

        function cerrarModalEditarPedido() {
            document.getElementById('modalEditarPedido').classList.remove('active');
        }

        // Formulario para editar pedido
        document.getElementById('formEditarPedido').addEventListener('submit', function(e) {
            e.preventDefault();
            guardarCambiosPedido();
        });

        function guardarCambiosPedido() {
            const formData = new FormData(document.getElementById('formEditarPedido'));
            const pedidoId = formData.get('pedido_id');
            
            // Recopilar items del pedido
            const items = [];
            const itemElements = document.querySelectorAll('.item-pedido');
            
            itemElements.forEach(item => {
                const inputs = item.querySelectorAll('input[type="hidden"]');
                items.push({
                    producto_id: inputs[0].value,
                    cantidad: parseInt(inputs[1].value),
                    precio_unitario: parseFloat(inputs[2].value)
                });
            });
            
            if (items.length === 0) {
                alert('❌ El pedido debe tener al menos un producto');
                return;
            }
            
            const btn = document.querySelector('#formEditarPedido button[type="submit"]');
            const originalText = btn.textContent;
            btn.textContent = '⏳ Guardando...';
            btn.disabled = true;
            
            fetch('gestion_pedidos.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'actualizar',
                    pedido_id: pedidoId,
                    items: items
                }),
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Pedido actualizado correctamente');
                    cerrarModalEditarPedido();
                    location.reload(); // Recargar para ver los cambios
                } else {
                    alert('❌ Error: ' + data.message);
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error al guardar los cambios');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }

        // Funciones para editar y eliminar pedidos
        function editarPedido(id) {
            fetch(`gestion_pedidos.php?action=obtener&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarModalEditarPedido(data);
                    } else {
                        alert('❌ Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Error al cargar el pedido');
                });
        }

        function eliminarPedido(id) {
            if (confirm('¿Está seguro de eliminar este pedido? Esta acción no se puede deshacer.')) {
                fetch('gestion_pedidos.php', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'eliminar', id: id }),
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Pedido eliminado correctamente');
                        document.getElementById(`pedido-${id}`).remove();
                        
                        // Si no hay más pedidos, mostrar mensaje
                        if (document.querySelectorAll('.pedido-card').length === 0) {
                            document.getElementById('lista-pedidos').innerHTML = `
                                <div class="empty-state" style="grid-column: 1 / -1;">
                                    <h3>🎉 No hay pedidos pendientes</h3>
                                    <p>Todos los pedidos han sido pagados o no hay pedidos activos.</p>
                                </div>
                            `;
                        }
                    } else {
                        alert('❌ Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Error al eliminar el pedido');
                });
            }
        }

        // Cerrar modales al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });

        // BÚSQUEDA DE PEDIDOS (en tiempo real)
        function buscarPedidos() {
            const searchTerm = document.getElementById('searchPedidos').value.toLowerCase();
            const pedidos = document.querySelectorAll('.pedido-card');
            let resultadosEncontrados = false;

            pedidos.forEach(pedido => {
                const mesa = pedido.getAttribute('data-mesa').toLowerCase();
                const garzon = pedido.getAttribute('data-garzon').toLowerCase();
                const total = pedido.getAttribute('data-total').toLowerCase();

                if (mesa.includes(searchTerm) || garzon.includes(searchTerm) || total.includes(searchTerm)) {
                    pedido.style.display = 'block';
                    resultadosEncontrados = true;
                } else {
                    pedido.style.display = 'none';
                }
            });

            if (!resultadosEncontrados && searchTerm !== '') {
                document.getElementById('lista-pedidos').innerHTML = `
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <h3>🔍 No se encontraron resultados</h3>
                        <p>No hay pedidos que coincidan con "${searchTerm}"</p>
                    </div>
                `;
            }
        }

        function limpiarBusquedaPedidos() {
            document.getElementById('searchPedidos').value = '';
            location.reload();
        }

        // BÚSQUEDA DE MESAS (en tiempo real)
        function buscarMesas() {
            const searchTerm = document.getElementById('searchMesas').value.toLowerCase();
            const mesas = document.querySelectorAll('.mesa-card');

            mesas.forEach(mesa => {
                const numero = mesa.getAttribute('data-numero').toLowerCase();
                const estado = mesa.getAttribute('data-estado').toLowerCase();

                if (numero.includes(searchTerm) || estado.includes(searchTerm)) {
                    mesa.style.display = 'block';
                } else {
                    mesa.style.display = 'none';
                }
            });
        }

        // BÚSQUEDA DE MENÚ (en tiempo real)
        function buscarMenu() {
            const searchTerm = document.getElementById('searchMenu').value.toLowerCase();
            const productos = document.querySelectorAll('.producto-card');
            let resultadosEncontrados = false;

            productos.forEach(producto => {
                const nombre = producto.getAttribute('data-nombre');
                const precio = producto.getAttribute('data-precio');
                const categoria = producto.getAttribute('data-categoria');

                if (nombre.includes(searchTerm) || precio.includes(searchTerm) || categoria.includes(searchTerm)) {
                    producto.style.display = 'block';
                    producto.closest('.categoria-section').style.display = 'block';
                    resultadosEncontrados = true;
                } else {
                    producto.style.display = 'none';
                }
            });

            if (!resultadosEncontrados && searchTerm !== '') {
                document.getElementById('lista-productos').innerHTML = `
                    <div class="empty-state">
                        <h3>🔍 No se encontraron resultados</h3>
                        <p>No hay productos que coincidan con "${searchTerm}"</p>
                    </div>
                `;
            }
        }

        function limpiarBusquedaMenu() {
            document.getElementById('searchMenu').value = '';
            location.reload();
        }

        // FILTRADO POR CATEGORÍA
        function filtrarPorCategoria(categoria) {
            document.querySelectorAll('.categoria-filter').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            const categorias = document.querySelectorAll('.categoria-section');
            
            if (categoria === 'todas') {
                categorias.forEach(cat => {
                    cat.style.display = 'block';
                });
            } else {
                categorias.forEach(cat => {
                    if (cat.getAttribute('data-categoria') === categoria) {
                        cat.style.display = 'block';
                    } else {
                        cat.style.display = 'none';
                    }
                });
            }
        }

        // FUNCIONALIDAD DE PAGO
        function mostrarDetallesPago(pedidoId) {
            const pedidoCard = document.getElementById(`pedido-${pedidoId}`);
            const mesa = pedidoCard.getAttribute('data-mesa');
            const garzon = pedidoCard.getAttribute('data-garzon');
            const total = pedidoCard.getAttribute('data-total');
            
            // Obtener los items del pedido (simulado - en producción deberías hacer una petición AJAX)
            const items = pedidoCard.querySelector('.items-list').innerHTML;
            
            document.getElementById('tituloModalPago').textContent = `💳 Pago - Mesa ${mesa}`;
            document.getElementById('detalles-pedido').innerHTML = `
                <h4>🍽️ Mesa ${mesa} - Pedido #${pedidoId}</h4>
                <p><strong>Garzón:</strong> ${garzon}</p>
                ${items}
                <div class="pedido-total">
                    💰 Total: Bs/ ${total}
                </div>
            `;
            
            document.getElementById('pedido_id_pago').value = pedidoId;
            document.getElementById('total_pedido').value = total;
            document.getElementById('formPago').reset();
            document.getElementById('seccion-efectivo').classList.add('hidden');
            document.getElementById('cambio').textContent = 'Bs/ 0.00';
            document.getElementById('cambio').className = 'cambio-info';
            
            mostrarModalPago();
        }

        function toggleEfectivoSection() {
            const select = document.getElementById('metodo_pago');
            const efectivoSection = document.getElementById('seccion-efectivo');
            const metodoSeleccionado = select.options[select.selectedIndex];
            const tipoPago = metodoSeleccionado.getAttribute('data-tipo');

            if (tipoPago === 'efectivo') {
                efectivoSection.classList.remove('hidden');
                document.getElementById('monto_recibido').required = true;
            } else {
                efectivoSection.classList.add('hidden');
                document.getElementById('monto_recibido').required = false;
            }
            
            calcularCambio();
        }

        function calcularCambio() {
            const total = parseFloat(document.getElementById('total_pedido').value);
            const propina = parseFloat(document.getElementById('propina').value) || 0;
            const montoRecibido = parseFloat(document.getElementById('monto_recibido')?.value) || 0;
            const totalConPropina = total + propina;
            const cambio = montoRecibido - totalConPropina;
            
            const cambioElement = document.getElementById('cambio');
            if (cambioElement) {
                if (cambio >= 0) {
                    cambioElement.textContent = `Bs/ ${cambio.toFixed(2)}`;
                    cambioElement.className = 'cambio-info';
                } else {
                    cambioElement.textContent = `Faltan: Bs/ ${Math.abs(cambio).toFixed(2)}`;
                    cambioElement.className = 'cambio-info cambio-negativo';
                }
            }
        }

        // Procesar pago
        document.getElementById('formPago').addEventListener('submit', function(e) {
            e.preventDefault();
            procesarPago();
        });

        function procesarPago() {
            const pedidoId = document.getElementById('pedido_id_pago').value;
            const total = parseFloat(document.getElementById('total_pedido').value);
            const metodoPago = document.getElementById('metodo_pago').value;
            const propina = parseFloat(document.getElementById('propina').value) || 0;
            const montoRecibido = parseFloat(document.getElementById('monto_recibido').value) || 0;
            const totalConPropina = total + propina;
            const cambio = montoRecibido - totalConPropina;
            
            // Validaciones
            if (!metodoPago) {
                alert('❌ Por favor seleccione un método de pago');
                return;
            }
            
            const metodoSeleccionado = document.getElementById('metodo_pago').options[document.getElementById('metodo_pago').selectedIndex];
            const tipoPago = metodoSeleccionado.getAttribute('data-tipo');
            
            if (tipoPago === 'efectivo') {
                if (montoRecibido <= 0) {
                    alert('❌ Por favor ingrese el monto recibido del cliente');
                    return;
                }
                if (montoRecibido < totalConPropina) {
                    alert('❌ El monto recibido es menor al total + propina. Faltan: Bs/ ' + Math.abs(cambio).toFixed(2));
                    return;
                }
            }
            
            if (confirm(`¿Está seguro de procesar el pago del Pedido #${pedidoId}?\n\nTotal: Bs/ ${total}\nPropina: Bs/ ${propina}\nTotal a Pagar: Bs/ ${totalConPropina}\nMétodo: ${metodoSeleccionado.textContent}`)) {
                const btn = document.querySelector('#formPago button[type="submit"]');
                const originalText = btn.textContent;
                btn.textContent = '⏳ Procesando...';
                btn.disabled = true;
                
                fetch('procesar_pago.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        pedido_id: pedidoId,
                        metodo_pago_id: metodoPago,
                        propina: propina,
                        monto_recibido: montoRecibido,
                        cambio: cambio >= 0 ? cambio : 0
                    }),
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Pago procesado exitosamente');
                        document.getElementById(`pedido-${pedidoId}`).remove();
                        cerrarModalPago();
                        
                        if (document.querySelectorAll('.pedido-card').length === 0) {
                            document.getElementById('lista-pedidos').innerHTML = `
                                <div class="empty-state" style="grid-column: 1 / -1;">
                                    <h3>🎉 No hay pedidos pendientes</h3>
                                    <p>Todos los pedidos han sido pagados o no hay pedidos activos.</p>
                                </div>
                            `;
                        }
                    } else {
                        alert('❌ Error al procesar el pago: ' + data.message);
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Error al procesar el pago');
                    btn.textContent = originalText;
                    btn.disabled = false;
                });
            }
        }

        // Funciones para el historial
        function limpiarFiltros() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            document.getElementById('fecha_inicio').value = firstDay.toISOString().split('T')[0];
            document.getElementById('fecha_fin').value = today.toISOString().split('T')[0];
            document.getElementById('filtro_mesa').value = '';
            document.getElementById('filtro_garzon').value = '';
            filtrarHistorial();
        }

        function filtrarHistorial() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const mesaId = document.getElementById('filtro_mesa').value;
            const garzonId = document.getElementById('filtro_garzon').value;
            
            const params = new URLSearchParams({
                fecha_inicio: fechaInicio,
                fecha_fin: fechaFin,
                mesa_id: mesaId,
                garzon_id: garzonId,
                ajax: 'true'
            });
            
            fetch(`historial_pedidos.php?${params}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('resultados-historial').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al filtrar el historial');
                });
        }

        function exportarHistorial() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const mesaId = document.getElementById('filtro_mesa').value;
            const garzonId = document.getElementById('filtro_garzon').value;
            
            const url = `exportar_historial.php?fecha_inicio=${fechaInicio}&fecha_fin=${fechaFin}&mesa_id=${mesaId}&garzon_id=${garzonId}`;
            window.open(url, '_blank');
        }
    </script>
</body>
</html>