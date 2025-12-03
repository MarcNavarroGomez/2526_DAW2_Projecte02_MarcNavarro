window.onload = function () {
    // --- Guardamos elementos del DOM ---
    const form = document.getElementById("liberar-mesa-form");
    const inputcamarero = document.getElementById("camarero");
    const inputcamareroSesion = document.getElementById("camarero_sesion");
    const botonAsignar = document.getElementById("btn-liberar");

    console.log("Camarero mesa:", inputcamarero.value);
    console.log("Camarero sesión:", inputcamareroSesion.value);

    // --- Función de validación ---
    function validarCampo() {

            // Verificar que el camarero de la mesa coincide con el camarero en sesión
        if (inputcamarero.value !== inputcamareroSesion.value) {
            botonAsignar.style.display = "none"; // No mostrar botón
            return false;
        }
        return true;
    }

    // Validación al salir del campo
    validarCampo();

    // // Validación al enviar
    // form.onsubmit = function (e) {
    //     if (!validarCampo()) {
    //         e.preventDefault(); // Bloquea envío
    //     }
    // };
};
