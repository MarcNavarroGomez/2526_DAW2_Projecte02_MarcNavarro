/**
 * FUNCIONES AUXILIARES
 * Estas funciones nos ayudan a no repetir código para mostrar/ocultar cosas.
 */
function mostrarModal(idModal) {
    var modal = document.getElementById(idModal);
    if (modal) {
        modal.style.display = 'flex'; // 'flex' para que se centre bien
    }
}

function cerrarModal(idModal) {
    var modal = document.getElementById(idModal);
    if (modal) {
        modal.style.display = 'none'; // 'none' para ocultarlo
    }
}

/**
 * CARGA PRINCIPAL
 * Todo lo que esté aquí dentro se ejecuta cuando la página ha terminado de cargar.
 */
window.onload = function () {

    // ==========================================
    // 1. BOTÓN "NUEVO USUARIO"
    // ==========================================
    var btnNuevo = document.getElementById('btn-nuevo-usuario');
    if (btnNuevo) {
        btnNuevo.onclick = function () {
            mostrarModal('modalCrear');
        };
    }

    // ==========================================
    // 2. BOTÓN "USUARIOS INACTIVOS"
    // ==========================================
    var btnInactivos = document.getElementById('btn-ver-inactivos');
    if (btnInactivos) {
        btnInactivos.onclick = function () {
            mostrarModal('modalInactivos');
        };
    }

    // ==========================================
    // 3. BOTONES DE CERRAR (La "X" de los modales)
    // ==========================================
    var botonesCerrar = document.getElementsByClassName('close');
    // Recorremos todos los elementos que tengan la clase "close"
    for (var i = 0; i < botonesCerrar.length; i++) {
        botonesCerrar[i].onclick = function () {
            // Leemos qué modal debe cerrar este botón (atributo data-modal)
            var idModalACerrar = this.getAttribute('data-modal');
            cerrarModal(idModalACerrar);
        };
    }

    // ==========================================
    // 4. BOTONES DE EDITAR (Lógica de rellenar formulario)
    // ==========================================
    var botonesEditar = document.getElementsByClassName('btn-editar-usuario');
    for (var i = 0; i < botonesEditar.length; i++) {
        botonesEditar[i].onclick = function () {
            // 'this' es el botón al que le acabamos de hacer click

            // A) LEER DATOS DEL BOTÓN (que pusimos en el PHP)
            var id = this.getAttribute('data-id');
            var username = this.getAttribute('data-username');
            var nombre = this.getAttribute('data-nombre');
            var apellido = this.getAttribute('data-apellido');
            var email = this.getAttribute('data-email');
            var rol = this.getAttribute('data-rol');

            // B) RELLENAR LOS INPUTS DEL FORMULARIO
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_apellido').value = apellido;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_rol').value = rol;

            // C) MOSTRAR EL MODAL
            mostrarModal('modalEditar');
        };
    }

    // ==========================================
    // 5. VALIDACIÓN DEL FORMULARIO DE CREAR
    // Aquí puedes añadir tus reglas de validación
    // ==========================================
    var formCrear = document.querySelector('#modalCrear form'); // Buscamos el formulario dentro del modal
    if (formCrear) {
        formCrear.onsubmit = function (event) {
            // Obtenemos los valores
            var pass = formCrear.querySelector('input[name="password"]').value;
            var nombre = formCrear.querySelector('input[name="nombre"]').value;

            // EJEMPLO DE REGLA: La contraseña debe tener al menos 5 caracteres
            if (pass.length < 5) {
                event.preventDefault(); // EVITA que el formulario se envíe
                Swal.fire('Error', 'La contraseña debe tener al menos 5 caracteres', 'error');
                return false;
            }

            // EJEMPLO DE REGLA: El nombre no puede estar vacío (aunque el 'required' de HTML ya lo hace)
            if (nombre.trim() === "") {
                event.preventDefault();
                Swal.fire('Error', 'El nombre no puede estar vacío', 'error');
                return false;
            }

            // Si todo está bien, el formulario se envía normalmente
            return true;
        };
    }

    // ==========================================
    // 6. BOTONES DE ACCIÓN (Eliminar, Rehabilitar, Borrar Permanente)
    // Usan SweetAlert para confirmar
    // ==========================================

    // --- ELIMINAR (Baja Lógica) ---
    var botonesEliminar = document.getElementsByClassName('btn-eliminar-usuario');
    for (var i = 0; i < botonesEliminar.length; i++) {
        botonesEliminar[i].onclick = function () {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-username');
            confirmarAccion('¿Eliminar usuario?', 'Se dará de baja a ' + nombre, '../../PROCEDIMIENTOS/ADMIN/eliminar_usuario.php', id);
        };
    }

    // --- REHABILITAR ---
    var botonesRehabilitar = document.getElementsByClassName('btn-rehabilitar-usuario');
    for (var i = 0; i < botonesRehabilitar.length; i++) {
        botonesRehabilitar[i].onclick = function () {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-username');
            confirmarAccion('¿Rehabilitar?', 'Se reactivará a ' + nombre, '../../PROCEDIMIENTOS/ADMIN/rehabilitar_usuario.php', id, 'question', '#2ecc71');
        };
    }

    // --- BORRAR PERMANENTE ---
    var botonesBorrarPerma = document.getElementsByClassName('btn-borrar-permanente');
    for (var i = 0; i < botonesBorrarPerma.length; i++) {
        botonesBorrarPerma[i].onclick = function () {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-username');
            confirmarAccion('¿BORRAR DEFINITIVAMENTE?', 'Se borrará TODO el historial de ' + nombre, '../../PROCEDIMIENTOS/ADMIN/borrar_usuario_permanente.php', id, 'warning', '#c0392b');
        };
    }

    // ==========================================
    // 7. MENSAJES DE URL (Feedback al usuario)
    // ==========================================
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        var msg = urlParams.get('success');
        var texto = 'Operación correcta';

        // Traducimos el código de éxito a un mensaje legible
        if (msg === 'usuario_creado') texto = 'Usuario creado correctamente';
        if (msg === 'usuario_editado') texto = 'Datos actualizados';
        if (msg === 'usuario_eliminado') texto = 'Usuario dado de baja';
        if (msg === 'usuario_rehabilitado') texto = 'Usuario reactivado';
        if (msg === 'usuario_borrado_total') texto = 'Usuario eliminado para siempre';

        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: texto,
            timer: 2000,
            showConfirmButton: false
        });
    }

    if (urlParams.has('error')) {
        var errorMsg = urlParams.get('error');
        // Traducimos errores comunes
        if (errorMsg === 'usuario_con_historial') errorMsg = 'No se puede borrar: tiene historial.';

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMsg
        });
    }
};

/**
 * FUNCIÓN HELPER PARA CONFIRMACIONES
 * Crea un formulario oculto y lo envía si el usuario dice "Sí".
 */
function confirmarAccion(titulo, texto, urlAction, idUsuario, icono = 'warning', colorBoton = '#e74c3c') {
    Swal.fire({
        title: titulo,
        text: texto,
        icon: icono,
        showCancelButton: true,
        confirmButtonColor: colorBoton,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Creamos un formulario "al vuelo" con JS
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = urlAction;

            // Añadimos el ID del usuario
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id_usuario';
            input.value = idUsuario;

            form.appendChild(input);
            document.body.appendChild(form); // Lo añadimos al documento
            form.submit(); // Y lo enviamos
        }
    });
}
