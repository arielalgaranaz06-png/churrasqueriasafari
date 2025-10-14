<?php
session_start();

// Verificar si el usuario está logueado y es cajero
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'cajero') {
    header('Location: ../login.php');
    exit();
}

include '../config/database.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Cajero</title>
    <link rel="stylesheet" href="cajero.css">
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
            <?php include 'pedidos_pendientes.php'; ?>
        </div>

        <!-- Tab: Gestión de Mesas -->
        <div id="mesas" class="tab-content">
            <?php include 'gestion_mesas.php'; ?>
        </div>

        <!-- Tab: Gestión de Menú -->
        <div id="menu" class="tab-content">
            <?php include 'gestion_menu.php'; ?>
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
                            <?php 
                            $sql_metodos = "SELECT * FROM metodos_pago WHERE activo = true";
                            $stmt_metodos = $pdo->query($sql_metodos);
                            $metodos_pago = $stmt_metodos->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($metodos_pago as $metodo): ?>
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

    <script src="cajero.js"></script>
</body>
</html>