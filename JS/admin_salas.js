window.onload = function() {
    // --- Modal Crear Sala ---
    var btnNuevaSala = document.getElementById('btn-nueva-sala');
    var modalCrear = document.getElementById('modalCrear');
    
    if (btnNuevaSala) {
        btnNuevaSala.onclick = function() {
            modalCrear.style.display = 'flex';
        };
    }

    // --- Cerrar Modales ---
    var closeButtons = document.getElementsByClassName('close');
    for (var i = 0; i < closeButtons.length; i++) {
        closeButtons[i].onclick = function() {
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        };
    }

    // --- Cambiar Imagen ---
    var btnsImagen = document.getElementsByClassName('btn-cambiar-imagen');
    for (var i = 0; i < btnsImagen.length; i++) {
        btnsImagen[i].onclick = function() {
            var idSala = this.getAttribute('data-id');
            document.getElementById('imagen_id_sala').value = idSala;
            document.getElementById('modalImagen').style.display = 'flex';
        };
    }

    // --- Eliminar Sala ---
    var btnsEliminar = document.getElementsByClassName('btn-eliminar-sala');
    for (var i = 0; i < btnsEliminar.length; i++) {
        btnsEliminar[i].onclick = function() {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-nombre');

            Swal.fire({
                title: '¿Eliminar sala?',
                text: 'Se eliminará la sala "' + nombre + '" y todas sus mesas',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '../../PROCEDIMIENTOS/ADMIN/eliminar_sala.php';
                    
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'id_sala';
                    input.value = id;
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        };
    }

    // --- Mensajes de URL (SweetAlert) ---
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        var msg = urlParams.get('success');
        var texto = 'Operación realizada correctamente';
        if (msg === 'sala_creada') texto = 'Sala creada correctamente';
        if (msg === 'sala_eliminada') texto = 'Sala eliminada correctamente';
        if (msg === 'imagen_subida') texto = 'Imagen subida correctamente';
        
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: texto,
            timer: 2000,
            showConfirmButton: false
        });
    }
    if (urlParams.has('error')) {
        var error = urlParams.get('error');
        var texto = 'Ha ocurrido un error';
        if (error === 'tipo_invalido') texto = 'Tipo de archivo no permitido';
        if (error === 'archivo_grande') texto = 'El archivo es demasiado grande (máx. 5MB)';
        
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: texto
        });
    }
};
```
