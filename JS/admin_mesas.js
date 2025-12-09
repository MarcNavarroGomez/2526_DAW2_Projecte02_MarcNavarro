/**
 * GESTIÓN DE MESAS - DOM CLÁSICO
 * Manejo de modales y confirmaciones
 */

window.onload = function () {
    // --- Modal Crear Mesa ---
    var btnNuevaMesa = document.getElementById('btn-nueva-mesa');
    var modalCrear = document.getElementById('modalCrear');

    if (btnNuevaMesa) {
        btnNuevaMesa.onclick = function () {
            modalCrear.style.display = 'flex';
        };
    }

    // --- Cerrar Modales ---
    var closeButtons = document.getElementsByClassName('close');
    for (var i = 0; i < closeButtons.length; i++) {
        closeButtons[i].onclick = function () {
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        };
    }

    // --- Editar Mesa ---
    var btnsEditar = document.getElementsByClassName('btn-editar-mesa');
    for (var i = 0; i < btnsEditar.length; i++) {
        btnsEditar[i].onclick = function () {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-nombre');
            var sala = this.getAttribute('data-sala');
            var sillas = this.getAttribute('data-sillas');

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_sala').value = sala;
            document.getElementById('edit_sillas').value = sillas;

            document.getElementById('modalEditar').style.display = 'flex';
        };
    }

    // --- Eliminar Mesa ---
    var btnsEliminar = document.getElementsByClassName('btn-eliminar-mesa');
    for (var i = 0; i < btnsEliminar.length; i++) {
        btnsEliminar[i].onclick = function () {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-nombre');

            Swal.fire({
                title: '¿Eliminar mesa?',
                text: 'Se eliminará la mesa "' + nombre + '"',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../../PROCEDIMIENTOS/ADMIN/eliminar_mesa.php';

                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'id_mesa';
                    input.value = id;

                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        };
    }

    // --- Mensajes de URL ---
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        var msg = urlParams.get('success');
        var texto = 'Operación realizada correctamente';
        if (msg === 'mesa_creada') texto = 'Mesa creada correctamente';
        if (msg === 'mesa_editada') texto = 'Mesa editada correctamente';
        if (msg === 'mesa_eliminada') texto = 'Mesa eliminada correctamente';

        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: texto,
            timer: 2000,
            showConfirmButton: false
        });
    }
    if (urlParams.has('error')) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: urlParams.get('error')
        });
    }
};
