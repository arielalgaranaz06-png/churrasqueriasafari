// URL base para las acciones
const ACTIONS_URL = 'cajero_actions.php';

// Gestión de Mesas
function editarMesa(id) {
    fetch(`${ACTIONS_URL}?action=get_mesa&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('mesa_id').value = data.mesa.id;
                document.getElementById('numero_mesa').value = data.mesa.numero;
                document.getElementById('estado_mesa').value = data.mesa.estado;
                document.getElementById('tituloModalMesa').textContent = 'Editar Mesa';
                document.getElementById('modalMesa').style.display = 'block';
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los datos de la mesa');
        });
}

function eliminarMesa(id, event) {
    if (event) event.stopPropagation();
    
    if (!confirm('¿Estás seguro de que deseas eliminar esta mesa?')) {
        return;
    }
    
    fetch(ACTIONS_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_mesa&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar la mesa');
    });
}

// Gestión de Productos
function editarProducto(id) {
    fetch(`${ACTIONS_URL}?action=get_producto&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const producto = data.producto;
                document.getElementById('producto_id').value = producto.id;
                document.getElementById('nombre_producto').value = producto.nombre;
                document.getElementById('precio_producto').value = producto.precio;
                document.getElementById('categoria_producto').value = producto.categoria;
                document.getElementById('activo_producto').checked = producto.activo == 1;
                document.getElementById('tituloModalProducto').textContent = 'Editar Producto';
                document.getElementById('modalProducto').style.display = 'block';
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los datos del producto');
        });
}

function eliminarProducto(id) {
    if (!confirm('¿Estás seguro de que deseas eliminar este producto?')) {
        return;
    }
    
    fetch(ACTIONS_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_producto&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar el producto');
    });
}

// Gestión de Pagos
function mostrarDetallesPago(pedidoId) {
    // Cargar detalles del pedido
    fetch(`${ACTIONS_URL}?action=get_pedido_detalles&id=${pedidoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('pedido_id_pago').value = pedidoId;
                document.getElementById('total_pedido').value = data.pedido.total;
                document.getElementById('tituloModalPago').textContent = `💳 Pago - Mesa ${data.pedido.mesa_numero}`;
                
                // Mostrar detalles del pedido
                const detallesDiv = document.getElementById('detalles-pedido');
                detallesDiv.innerHTML = `
                    <h4>Detalles del Pedido</h4>
                    <p><strong>Mesa:</strong> ${data.pedido.mesa_numero}</p>
                    <p><strong>Garzón:</strong> ${data.pedido.garzon_nombre}</p>
                    <p><strong>Total:</strong> Bs/ ${parseFloat(data.pedido.total).toFixed(2)}</p>
                    <div class="items-list">
                        ${Object.entries(data.pedido.items).map(([categoria, items]) => `
                            <div class="item-categoria">
                                <strong>${categoria}:</strong>
                                ${items.map(item => `
                                    <div style="margin-left: 1rem;">
                                        ${item.cantidad}x ${item.producto_nombre} - Bs/ ${(item.precio_unitario * item.cantidad).toFixed(2)}
                                    </div>
                                `).join('')}
                            </div>
                        `).join('')}
                    </div>
                `;
                
                document.getElementById('modalPago').style.display = 'block';
                calcularCambio();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los detalles del pedido');
        });
}

function toggleEfectivoSection() {
    const metodoSelect = document.getElementById('metodo_pago');
    const selectedOption = metodoSelect.options[metodoSelect.selectedIndex];
    const esEfectivo = selectedOption.getAttribute('data-tipo') === 'efectivo';
    const seccionEfectivo = document.getElementById('seccion-efectivo');
    
    if (esEfectivo) {
        seccionEfectivo.classList.remove('hidden');
        document.getElementById('monto_recibido').required = true;
    } else {
        seccionEfectivo.classList.add('hidden');
        document.getElementById('monto_recibido').required = false;
        document.getElementById('cambio').textContent = 'Bs/ 0.00';
    }
}

function calcularCambio() {
    const total = parseFloat(document.getElementById('total_pedido').value) || 0;
    const propina = parseFloat(document.getElementById('propina').value) || 0;
    const montoRecibido = parseFloat(document.getElementById('monto_recibido').value) || 0;
    
    const totalConPropina = total + propina;
    const cambio = montoRecibido - totalConPropina;
    
    document.getElementById('cambio').textContent = `Bs/ ${cambio >= 0 ? cambio.toFixed(2) : '0.00'}`;
    
    // Validar que el monto recibido sea suficiente
    const btnProcesar = document.getElementById('btnProcesarPago');
    const metodoSelect = document.getElementById('metodo_pago');
    const selectedOption = metodoSelect.options[metodoSelect.selectedIndex];
    const esEfectivo = selectedOption.getAttribute('data-tipo') === 'efectivo';
    
    if (esEfectivo && cambio < 0) {
        btnProcesar.disabled = true;
        btnProcesar.title = 'El monto recibido es insuficiente';
    } else {
        btnProcesar.disabled = false;
        btnProcesar.title = '';
    }
}

// Funciones de búsqueda y filtrado
function buscarPedidos() {
    const searchTerm = document.getElementById('searchPedidos').value.toLowerCase();
    const pedidos = document.querySelectorAll('.pedido-card');
    
    pedidos.forEach(pedido => {
        const mesa = pedido.getAttribute('data-mesa').toLowerCase();
        const garzon = pedido.getAttribute('data-garzon').toLowerCase();
        const total = pedido.getAttribute('data-total');
        
        if (mesa.includes(searchTerm) || garzon.includes(searchTerm) || total.includes(searchTerm)) {
            pedido.style.display = 'block';
        } else {
            pedido.style.display = 'none';
        }
    });
}

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

function buscarMenu() {
    const searchTerm = document.getElementById('searchMenu').value.toLowerCase();
    const productos = document.querySelectorAll('.producto-card');
    
    productos.forEach(producto => {
        const nombre = producto.getAttribute('data-nombre');
        const precio = producto.getAttribute('data-precio');
        
        if (nombre.includes(searchTerm) || precio.includes(searchTerm)) {
            producto.style.display = 'block';
        } else {
            producto.style.display = 'none';
        }
    });
}

function filtrarPorCategoria(categoria) {
    const secciones = document.querySelectorAll('.categoria-section');
    const filtros = document.querySelectorAll('.categoria-filter');
    
    // Actualizar filtros activos
    filtros.forEach(filtro => filtro.classList.remove('active'));
    event.target.classList.add('active');
    
    secciones.forEach(seccion => {
        if (categoria === 'todas' || seccion.getAttribute('data-categoria') === categoria) {
            seccion.style.display = 'block';
        } else {
            seccion.style.display = 'none';
        }
    });
}

// Funciones de limpieza de búsqueda
function limpiarBusquedaPedidos() {
    document.getElementById('searchPedidos').value = '';
    buscarPedidos();
}

function limpiarBusquedaMenu() {
    document.getElementById('searchMenu').value = '';
    buscarMenu();
}

// Funciones de modales
function mostrarModalMesa() {
    document.getElementById('formMesa').reset();
    document.getElementById('mesa_id').value = '';
    document.getElementById('tituloModalMesa').textContent = 'Nueva Mesa';
    document.getElementById('modalMesa').style.display = 'block';
}

function cerrarModalMesa() {
    document.getElementById('modalMesa').style.display = 'none';
}

function mostrarModalProducto() {
    document.getElementById('formProducto').reset();
    document.getElementById('producto_id').value = '';
    document.getElementById('tituloModalProducto').textContent = 'Nuevo Producto';
    document.getElementById('modalProducto').style.display = 'block';
}

function cerrarModalProducto() {
    document.getElementById('modalProducto').style.display = 'none';
}

function cerrarModalPago() {
    document.getElementById('modalPago').style.display = 'none';
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Formulario de Mesa
    document.getElementById('formMesa').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', document.getElementById('mesa_id').value ? 'update_mesa' : 'create_mesa');
        
        fetch(ACTIONS_URL, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                cerrarModalMesa();
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar la mesa');
        });
    });
    
    // Formulario de Producto
    document.getElementById('formProducto').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', document.getElementById('producto_id').value ? 'update_producto' : 'create_producto');
        
        fetch(ACTIONS_URL, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                cerrarModalProducto();
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar el producto');
        });
    });
    
    // Formulario de Pago
    document.getElementById('formPago').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            pedido_id: document.getElementById('pedido_id_pago').value,
            metodo_pago_id: document.getElementById('metodo_pago').value,
            propina: parseFloat(document.getElementById('propina').value) || 0,
            monto_recibido: parseFloat(document.getElementById('monto_recibido').value) || 0,
            cambio: parseFloat(document.getElementById('cambio').textContent.replace('Bs/ ', '')) || 0
        };
        
        fetch(ACTIONS_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'procesar_pago',
                ...formData
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`✅ ${data.message}\nMesa ${data.data.mesa_liberada} liberada`);
                cerrarModalPago();
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar el pago');
        });
    });
    
    // Cerrar modales al hacer clic fuera
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    };
});

// Funciones de navegación entre tabs
function mostrarTab(tabName) {
    // Ocultar todos los tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Mostrar el tab seleccionado
    document.getElementById(tabName).classList.add('active');
    
    // Actualizar botones de navegación
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
}