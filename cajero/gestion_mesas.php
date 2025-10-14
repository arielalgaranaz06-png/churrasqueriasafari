<?php
include '../config/database.php';

// Obtener todas las mesas
$sql = "SELECT * FROM mesas ORDER BY numero";
$stmt = $pdo->query($sql);
$mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

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