/**
 * FUNCIONES AUXILIARES
 */
function mostrarModal(idModal) {
    var modal = document.getElementById(idModal);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function cerrarModal(idModal) {
    var modal = document.getElementById(idModal);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * CARGA PRINCIPAL
 */
window.onload = function () {

    // ==========================================
    // 1. BOTONES DE CERRAR (La "X" de los modales)
    // ==========================================
    var botonesCerrar = document.getElementsByClassName('close');
    for (var i = 0; i < botonesCerrar.length; i++) {
        botonesCerrar[i].onclick = function () {
            var idModalACerrar = this.getAttribute('data-modal');
            cerrarModal(idModalACerrar);
        };
    }

    // ==========================================
    // 2. BOTONES DE EDITAR RESERVA
    // ==========================================
    var botonesEditar = document.getElementsByClassName('btn-editar-reserva');
    for (var i = 0; i < botonesEditar.length; i++) {
        botonesEditar[i].onclick = function () {
            // A) LEER DATOS DEL BOTÓN
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-nombre');
            var telefono = this.getAttribute('data-telefono');
            var notas = this.getAttribute('data-notas');

            // B) RELLENAR LOS INPUTS DEL FORMULARIO
            document.getElementById('edit_id_reserva').value = id;
            document.getElementById('edit_nombre_cliente').value = nombre;
            document.getElementById('edit_telefono_cliente').value = telefono;
            document.getElementById('edit_notas').value = notas;

            // C) MOSTRAR EL MODAL
            mostrarModal('modalEditarReserva');
        };
    }

    // ==========================================
    // 3. BOTONES DE ELIMINAR RESERVA
    // ==========================================
    var botonesEliminar = document.getElementsByClassName('btn-eliminar-reserva');
    for (var i = 0; i < botonesEliminar.length; i++) {
        botonesEliminar[i].onclick = function () {
            var id = this.getAttribute('data-id');
            confirmarAccion('¿Eliminar reserva?', 'Esta acción no se puede deshacer.', '../PROCEDIMIENTOS/eliminar_reserva.php', id);
        };
    }

    // ==========================================
    // 4. MENSAJES DE ERROR/EXITO (PHP -> JS)
    // ==========================================
    const body = document.body;
    const success = body.dataset.success;
    const error = body.dataset.error;

    if (success) {
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: success,
            confirmButtonColor: '#27ae60'
        });
    }

    if (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error,
            confirmButtonColor: '#c0392b'
        });
    }
};

// Función para abrir el modal de confirmación de reserva (Creación) - Mantenida del código anterior
function abrirModalReserva(elemento) {
    const id = elemento.dataset.id;
    const nombre = elemento.dataset.nombre;
    const sala = elemento.dataset.sala;
    const inicio = elemento.dataset.inicio;
    const fin = elemento.dataset.fin;

    document.getElementById('res_mesa').textContent = nombre;
    document.getElementById('res_sala').textContent = sala;

    const fechaInicio = new Date(inicio);
    const horaInicio = fechaInicio.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    document.getElementById('res_horario').textContent = horaInicio;

    document.getElementById('input_mesa_id').value = id;
    document.getElementById('input_fecha_inicio').value = inicio;
    document.getElementById('input_fecha_fin').value = fin;

    mostrarModal('modalReserva');
}

// Cerrar modal al hacer clic fuera
window.onclick = function (event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

/**
 * FUNCIÓN HELPER PARA CONFIRMACIONES
 */
function confirmarAccion(titulo, texto, urlAction, idReserva, icono = 'warning', colorBoton = '#e74c3c') {
    Swal.fire({
        title: titulo,
        text: texto,
        icon: icono,
        showCancelButton: true,
        confirmButtonColor: colorBoton,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = urlAction;

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id_reserva';
            input.value = idReserva;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    });
}
