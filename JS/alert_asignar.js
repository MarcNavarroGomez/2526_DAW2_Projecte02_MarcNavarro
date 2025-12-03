// Usamos 'addEventListener' para ASEGURARNOS de que este 'onload' 
// NO SOBREESCRIBA el 'onload' de 'validar_asignacion.js'
window.addEventListener('load', function() {

    // 1. Obtenemos los elementos
    const form = document.getElementById("asignar-mesa-form");
    const boton = document.getElementById("btn-asignar");
    const inputComensales = document.getElementById("num-comensales");
    
    // 2. Buscamos el 'errorDiv' que el OTRO script creó.
    //    Usamos un 'setTimeout' de 1ms para asegurarnos de que el otro script
    //    'onload' ya lo ha creado.
    setTimeout(function() {
        const errorDiv = document.querySelector(".error");

        if (!form || !boton || !inputComensales || !errorDiv) {
            console.error("alert_asignar.js: No se pudieron encontrar todos los elementos (form, boton, input, o errorDiv).");
            return;
        }

        // 3. Escuchamos el CLIC en el botón
        //    (Usamos 'click' en lugar de 'submit' para interceptarlo antes)
        boton.addEventListener("click", function(e) {

            // 4. Comprobamos si el OTRO script (validar_asignacion.js) 
            //    tiene el div de error visible.
            //    'getComputedStyle' es la forma más fiable de leer si está oculto.
            const errorEstaVisible = window.getComputedStyle(errorDiv).display === "block";

            // 5. Si el error está visible, NO hacemos nada. 
            //    El otro script ya se encargará de frenar el envío.
            if (errorEstaVisible) {
                return;
            }

            // 6. SI EL ERROR NO ESTÁ VISIBLE (validación OK):
            //    ¡Aquí actuamos!
            
            // a. Prevenimos el envío del formulario
            e.preventDefault(); 
            
            // b. Obtenemos el valor
            const numComensales = inputComensales.value;

            // c. Lanzamos el SweetAlert de confirmación
            const swalWithBootstrapButtons = Swal.mixin({
                customClass: {
                    confirmButton: "btn-confirmar",
                    cancelButton: "btn-cancelar"
                },
                buttonsStyling: false
            });

            swalWithBootstrapButtons.fire({
                title: "¿Confirmar asignación?",
                text: `¿Deseas asignar esta mesa para ${numComensales} comensales?`,
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Sí, asignar",
                cancelButtonText: "Cancelar",
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // d. Si el usuario confirma, enviamos el formulario
                    form.submit();
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // e. Si cancela, mostramos el aviso
                    swalWithBootstrapButtons.fire({
                        title: "Cancelado",
                        text: "No se ha asignado la mesa.",
                        icon: "error"
                    });
                }
            });
        });
    }, 1); // El timeout de 1ms es para asegurar que .error ya existe
});