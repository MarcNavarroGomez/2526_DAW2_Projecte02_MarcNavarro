/**
 * GESTIÓN DE RESERVAS - DOM CLÁSICO
 * Manejo de modales y validación de formularios
 */

/**
 * GESTIÓN DE RESERVAS - DOM CLÁSICO
 * Manejo de modales y validación de formularios
 */

// Variables globales
var modal = document.getElementById('modalReserva');
var closeBtn = document.getElementById('closeModal');

// Cerrar modal con la X
if (closeBtn) {
    closeBtn.onclick = function () {
        modal.style.display = 'none';
    }
}

// Cerrar modal al hacer click fuera
window.onclick = function (event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

/**
 * Abre el modal de confirmación de reserva
 * Lee los datos del elemento clickeado (mesa)
 */
function abrirModalReserva(elemento) {
    var id = elemento.getAttribute('data-id');
    var nombre = elemento.getAttribute('data-nombre');
    var sala = elemento.getAttribute('data-sala');
    var inicio = elemento.getAttribute('data-inicio');
    var fin = elemento.getAttribute('data-fin');

    // Formatear hora para mostrar (HH:MM)
    var horaInicio = inicio.split(' ')[1].substring(0, 5);
    var horaFin = fin.split(' ')[1].substring(0, 5);

    // Rellenar datos en el modal
    document.getElementById('res_mesa').innerText = nombre;
    document.getElementById('res_sala').innerText = sala;
    document.getElementById('res_horario').innerText = horaInicio + ' - ' + horaFin;

    // Rellenar campos ocultos del formulario
    document.getElementById('input_mesa_id').value = id;
    document.getElementById('input_fecha_inicio').value = inicio;
    document.getElementById('input_fecha_fin').value = fin;

    // Mostrar modal
    modal.style.display = 'flex';
}

/**
 * Mostrar mensajes de éxito o error desde PHP
 * Lee los atributos data- del body
 */
var body = document.getElementsByTagName('body')[0];
var successMsg = body.getAttribute('data-success');
var errorMsg = body.getAttribute('data-error');

if (successMsg && successMsg !== '') {
    Swal.fire('¡Reserva Creada!', successMsg, 'success');
}

if (errorMsg && errorMsg !== '') {
    Swal.fire('Error', errorMsg, 'error');
}
