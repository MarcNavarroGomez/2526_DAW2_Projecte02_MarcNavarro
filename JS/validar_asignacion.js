window.onload = function () {

    // --- Guardamos elementos del DOM ---
    const form = document.getElementById("asignar-mesa-form");
    const inputComensales = document.getElementById("num-comensales");
    const maxSillas = parseInt(document.getElementById("max-sillas").value);
    const botonAsignar = document.getElementById("btn-asignar");

    // Crear o reutilizar el div de error
    let errorDiv = document.querySelector(".error");
    if (!errorDiv) {
        errorDiv = document.createElement("div");
        errorDiv.className = "error";
        form.appendChild(errorDiv);
    }

    // Ocultar inicialmente
    errorDiv.style.display = "none";

    // --- Función para mostrar error ---
    function mostrarError(mensaje) {
        errorDiv.innerHTML = mensaje;
        errorDiv.style.display = "block";
        botonAsignar.style.display = "none"; // Ocultamos botón si hay error
    }

    // --- Función para limpiar error ---
    function limpiarError() {
        errorDiv.innerHTML = "";
        errorDiv.style.display = "none";
        botonAsignar.style.display = "inline-block"; // Mostramos botón si NO hay error
    }

    // --- Función de validación ---
    function validarCampo() {
        limpiarError();

        const valor = inputComensales.value.trim();

        // Campo vacío
        if (!valor) {
            mostrarError("Debes indicar el número de comensales.");
            return false;
        }

        // No es número
        if (isNaN(valor)) {
            mostrarError("Introduce un número válido.");
            return false;
        }

        const num = parseInt(valor);

        // Menor que 1
        if (num < 1) {
            mostrarError("El mínimo permitido es 1 comensal.");
            return false;
        }

        // Mayor que sillas disponibles
        if (num > maxSillas) {
            mostrarError("La mesa no admite más de " + maxSillas + " comensales.");
            return false;
        }

        return true;
    }

    // Validación al salir del campo
    inputComensales.onmouseleave = validarCampo;

    // Validación al enviar
    form.onsubmit = function (e) {
        if (!validarCampo()) {
            e.preventDefault(); // Bloquea envío
        }
    };
};
