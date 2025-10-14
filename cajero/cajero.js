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

// Funciones para pedidos
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

// Formularios
document.getElementById('formMesa').addEventListener('submit', function(e) {
    e.preventDefault();
    guardarMesa();
});

document.getElementById('formProducto').addEventListener('submit', function(e) {
    e.preventDefault();
    guardarProducto();
});

document.getElementById('formEditarPedido').addEventListener('submit', function(e) {
    e.preventDefault();
    guardarCambiosPedido();
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

// Cargar historial al mostrar la pestaña
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

// Funciones de pago
function mostrarDetallesPago(pedidoId) {
    const pedidoCard = document.getElementById(`pedido-${pedidoId}`);
    const mesa = pedidoCard.getAttribute('data-mesa');
    const garzon = pedidoCard.getAttribute('data-garzon');
    const total = pedidoCard.getAttribute('data-total');
    
    // Obtener los items del pedido
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
    
    document.getElementById('modalPago').classList.add('active');
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
                document.getElementById('modalPago').classList.remove('active');
                
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

// Funciones de cierre de modales
function cerrarModalMesa() {
    document.getElementById('modalMesa').classList.remove('active');
}

function cerrarModalProducto() {
    document.getElementById('modalProducto').classList.remove('active');
}

function cerrarModalPago() {
    document.getElementById('modalPago').classList.remove('active');
}

// Cerrar modales al hacer clic fuera
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});