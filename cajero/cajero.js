// Gestión de Mesas
function editarMesa(id) {
    fetch(`get_mesa.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalMesa').classList.add('active');
                document.getElementById('tituloModalMesa').textContent = 'Editar Mesa';
                document.getElementById('mesa_id').value = data.mesa.id;
                document.getElementById('numero_mesa').value = data.mesa.numero;
                document.getElementById('estado_mesa').value = data.mesa.estado;
            } else {
                alert('Error al cargar la mesa');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar la mesa');
        });
}

function eliminarMesa(id, event) {
    event.stopPropagation();
    
    if (confirm('¿Está seguro de eliminar esta mesa? Esta acción no se puede deshacer.')) {
        fetch(`delete_mesa.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar la mesa: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar la mesa');
            });
    }
}

// Gestión de Productos
function editarProducto(id) {
    fetch(`get_producto.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modalProducto').classList.add('active');
                document.getElementById('tituloModalProducto').textContent = 'Editar Producto';
                document.getElementById('producto_id').value = data.producto.id;
                document.getElementById('nombre_producto').value = data.producto.nombre;
                document.getElementById('precio_producto').value = data.producto.precio;
                document.getElementById('categoria_producto').value = data.producto.categoria;
                document.getElementById('activo_producto').checked = data.producto.activo == 1;
            } else {
                alert('Error al cargar el producto');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar el producto');
        });
}

function eliminarProducto(id) {
    if (confirm('¿Está seguro de eliminar este producto? Esta acción no se puede deshacer.')) {
        fetch(`delete_producto.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al eliminar el producto: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar el producto');
            });
    }
}

// Formulario de Mesas
document.getElementById('formMesa').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const mesaId = document.getElementById('mesa_id').value;
    const url = mesaId ? 'update_mesa.php' : 'create_mesa.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            cerrarModalMesa();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar la mesa');
    });
});

// Formulario de Productos
document.getElementById('formProducto').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const productoId = document.getElementById('producto_id').value;
    const url = productoId ? 'update_producto.php' : 'create_producto.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            cerrarModalProducto();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar el producto');
    });
});

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
    // Resetear el botón de pago
    const btn = document.getElementById('btnProcesarPago');
    btn.disabled = false;
    btn.textContent = '✅ Procesar Pago';
}

// Cerrar modales al hacer clic fuera
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
        // Resetear el botón de pago si se cierra el modal de pago
        if (e.target.id === 'modalPago') {
            const btn = document.getElementById('btnProcesarPago');
            btn.disabled = false;
            btn.textContent = '✅ Procesar Pago';
        }
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
        const btn = document.getElementById('btnProcesarPago');
        const originalText = btn.textContent;
        btn.textContent = '⏳ Procesando...';
        btn.disabled = true;
        
        // Agregar timeout para evitar congelamiento
        const timeoutId = setTimeout(() => {
            btn.textContent = originalText;
            btn.disabled = false;
            alert('❌ El proceso está tomando más tiempo de lo esperado. Por favor intente nuevamente.');
        }, 10000); // 10 segundos timeout
        
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
        .then(response => {
            clearTimeout(timeoutId);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('✅ Pago procesado exitosamente');
                cerrarModalPago();
                location.reload();
            } else {
                alert('❌ Error al procesar el pago: ' + data.message);
                btn.textContent = originalText;
                btn.disabled = false;
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Error:', error);
            alert('❌ Error de conexión al procesar el pago');
            btn.textContent = originalText;
            btn.disabled = false;
        });
    }
}