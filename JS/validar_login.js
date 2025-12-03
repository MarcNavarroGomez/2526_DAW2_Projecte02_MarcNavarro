window.onload = function () {
    // Al cargar la página guardamos los elementos del formulario con su id
    const form = document.getElementById("loginForm");
    const username = document.getElementById("username");
    const password = document.getElementById("password");

    // Crear o reutilizar el div de error
    let errorDiv = document.querySelector(".error");
    if (!errorDiv) {
        errorDiv = document.createElement("div");
        errorDiv.className = "error";
        // Si no existe en el HTML, lo añadimos al final del formulario (aunque en tu PHP ya existe)
        if (form) form.appendChild(errorDiv); 
    }

    // --- CORRECCIÓN APLICADA ---
    // Comprobamos si el div ya tiene texto traído desde PHP
    if (errorDiv && errorDiv.innerHTML.trim() !== "") {
        // Si tiene texto (ej. "Credenciales incorrectas"), lo mostramos
        errorDiv.style.display = "block";
    } else if (errorDiv) {
        // Si está vacío, lo ocultamos inicialmente
        errorDiv.style.display = "none";
    }
    // ---------------------------

    // Cuando se ejecute la función mostrarError se mostrará el mensaje de error
    function mostrarError(mensaje) {
        if (errorDiv) {
            errorDiv.innerHTML = mensaje;
            errorDiv.style.display = "block";
        }
    }

    // Cuando se ejecute la función limpiarError se limpiará el mensaje de error
    function limpiarError() {
        if (errorDiv) {
            errorDiv.innerHTML = "";
            errorDiv.style.display = "none";
        }
    }

    // Función para validar los campos del formulario
    function validarCampos() {
        // NOTA: Si prefieres que no se borre el error de PHP al salir del campo,
        // podrías comentar la línea de abajo, pero para validación JS estándar se suele dejar.
        limpiarError();

        // Si el user está vacío se mostrará el mensaje de error
        if (!username.value) {
            mostrarError("Selecciona un usuario.");
            return false;
        }
        // Si la password está vacía se mostrará el mensaje de error
        if (!password.value) {
            mostrarError("Introduce tu contraseña.");
            return false;
        }
        // Si la password tiene menos de 6 caracteres se mostrará el mensaje de error
        if (password.value.length < 6) {
            mostrarError("La contraseña debe tener al menos 6 caracteres.");
            return false;
        }

        return true;
    }

    // Validación al salir del campo (blur)
    if (username) username.onblur = validarCampos;
    if (password) password.onblur = validarCampos;

    // Validación al enviar el formulario
    if (form) {
        form.onsubmit = function (e) {
            if (!validarCampos()) {
                e.preventDefault(); // Evita el envío si no pasa la validación JS
            }
        };
    }
};