// Funciones para mesas
function editarMesa(id) {
    fetch(`gestion_mesas.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(mesa => {
            document.getElementById('tituloModalMesa').textContent = 'Editar Mesa';
            document.getElementById('mesa_id').value = mesa.id;
            document.getElementById('numero_mesa').value = mesa.numero;
            document.getElementById('estado_mesa').value = mesa.estado;
            document.getElementById('modalMesa').classList.add('active');
        })
        .catch(error => {
            console.error('Error al cargar mesa:', error);
            alert('Error al cargar los datos de la mesa');
        });
}

function eliminarMesa(id, event) {
    event.stopPropagation();
    if (confirm('¿Está seguro de eliminar esta mesa?')) {
        fetch('gestion_mesas.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'eliminar', id: id }),
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Mesa eliminada correctamente');
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Error al eliminar la mesa');
        });
    }
}

// Funciones para productos
function editarProducto(id) {
    fetch(`gestion_menu.php?action=obtener&id=${id}`)
        .then(response => response.json())
        .then(producto => {
            document.getElementById('tituloModalProducto').textContent = 'Editar Producto';
            document.getElementById('producto_id').value = producto.id;
            document.getElementById('nombre_producto').value = producto.nombre;
            document.getElementById('precio_producto').value = producto.precio;
            document.getElementById('categoria_producto').value = producto.categoria;
            document.getElementById('activo_producto').checked = producto.activo == 1;
            document.getElementById('modalProducto').classList.add('active');
        })
        .catch(error => {
            console.error('Error al cargar producto:', error);
            alert('Error al cargar los datos del producto');
        });
}

function eliminarProducto(id) {
    if (confirm('¿Está seguro de eliminar este producto?')) {
        fetch('gestion_menu.php', {
            method: 'POST',
            body: JSON.stringify({ action: 'eliminar', id: id }),
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Producto eliminado correctamente');
                location.reload();
            } else {
                alert('❌ Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Error al eliminar el producto');
        });
    }
}

// Formularios
document.getElementById('formMesa').addEventListener('submit', function(e) {
    e.preventDefault();
    guardarMesa();
});

document.getElementById('formProducto').addEventListener('submit', function(e) {
    e.preventDefault();
    guardarProducto();
});

function guardarMesa() {
    const formData = new FormData(document.getElementById('formMesa'));
    const data = {
        action: formData.get('mesa_id') ? 'editar' : 'crear',
        id: formData.get('mesa_id'),
        numero: formData.get('numero_mesa'),
        estado: formData.get('estado_mesa')
    };

    fetch('gestion_mesas.php', {
        method: 'POST',
        body: JSON.stringify(data),
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            document.getElementById('modalMesa').classList.remove('active');
            location.reload();
        } else {
            alert('❌ Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al guardar la mesa');
    });
}

function guardarProducto() {
    const formData = new FormData(document.getElementById('formProducto'));
    const data = {
        action: formData.get('producto_id') ? 'editar' : 'crear',
        id: formData.get('producto_id'),
        nombre: formData.get('nombre_producto'),
        precio: formData.get('precio_producto'),
        categoria: formData.get('categoria_producto'),
        activo: document.getElementById('activo_producto').checked ? 1 : 0
    };

    fetch('gestion_menu.php', {
        method: 'POST',
        body: JSON.stringify(data),
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            document.getElementById('modalProducto').classList.remove('active');
            location.reload();
        } else {
            alert('❌ Error: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al guardar el producto');
    });
}