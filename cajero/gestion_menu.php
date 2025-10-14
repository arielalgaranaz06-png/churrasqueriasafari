<?php
include '../config/database.php';

// Obtener productos agrupados por categoría
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

$categorias_nombres = [
    'plato_principal' => '🍽️ Platos Principales',
    'acompanamiento' => '🥗 Acompañamientos',
    'bebida' => '🥤 Bebidas'
];
?>

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
        
        <?php if (empty($productos)): ?>
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
                <p><strong>Total de productos activos:</strong> <?php echo count($productos); ?></p>
                <?php foreach ($productos_por_categoria as $categoria => $productos_cat): ?>
                    <p><strong><?php echo $categorias_nombres[$categoria] ?? ucfirst($categoria); ?>:</strong> 
                        <?php echo count($productos_cat); ?> productos
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>