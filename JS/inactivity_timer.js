(function() {
    let inactivityTimer;
    
    // 5 minutos en milisegundos
    //   const timeoutDuration = 2 * 60 * 1000; 
      const timeoutDuration = 10000; // --- Descomenta esta línea para probar (10 segundos)

    // 1. Determinar la ruta correcta al script de logout
    // (Según tu estructura de carpetas)
    let logoutPath = '../PHP/PROCEDIMIENTOS/logout.php'; // Ruta por defecto (para index.php, historico.php)
    const path = window.location.pathname;

    if (path.includes('/PHP/')) {
        // Desde PUBLIC/SALAS/terraza1.php -> ../../PROCEDIMIENTOS/logout.php
        logoutPath = '../PROCEDIMIENTOS/logout.php';
    } else if (path.includes('/PROCEDIMIENTOS/')) {
        // Desde PUBLIC/PROCEDIMIENTOS/liberar_mesa.php -> logout.php
        logoutPath = 'logout.php'; 
    }

    // 2. Leer el nombre de usuario del atributo data del body
    // Usamos 'Usuario' como reserva si algo falla
    const userName = document.body.dataset.userName || 'Usuario';

    /**
     * Muestra el popup de SweetAlert
     */
    function showInactivityPopup() {
        // --- CAMBIO CLAVE ---
        // Se eliminan los listeners de actividad para que el pop-up no se cierre
        // si el usuario mueve el ratón para hacer clic.
        removeActivityListeners();

        Swal.fire({
            title: '¿Sigues ahí, ' + userName + '?',
            text: '',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '¡Sigo aquí!',
            cancelButtonText: 'Cerrar Sesión',
            reverseButtons: true, 
            allowOutsideClick: false, // No permite cerrar clicando fuera
            allowEscapeKey: false, // No permite cerrar con la tecla ESC
            
            // --- CAMBIO CLAVE ---
            // Se eliminan el temporizador y la barra de progreso de la alerta.
            timer: 60000, 
            timerProgressBar: true,

        }).then((result) => {
            if (result.isConfirmed) {
                // Si el usuario hace clic en "Sigo aquí"
                // Se resetea el timer Y se vuelven a añadir los listeners.
                resetTimer();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                // Si hace clic en "Cerrar Sesión"
                window.location.href = logoutPath;
            }
        });
    }

    /**
     * Resetea el temporizador de inactividad
     */
    function resetTimer() {
        clearTimeout(inactivityTimer);
        // Oculta cualquier alerta que pudiera estar abierta
        Swal.close(); 
        
        // --- CAMBIO CLAVE ---
        // Vuelve a añadir los listeners de actividad
        addActivityListeners();
        
        // Vuelve a empezar la cuenta de 5 minutos
        inactivityTimer = setTimeout(showInactivityPopup, timeoutDuration);
    }

    // --- NUEVO: Funciones para añadir y quitar listeners ---
    function addActivityListeners() {
        window.addEventListener('mousemove', resetTimer, { passive: true });
        window.addEventListener('keydown', resetTimer, { passive: true });
        window.addEventListener('click', resetTimer, { passive: true });
        window.addEventListener('scroll', resetTimer, { passive: true });
    }

    function removeActivityListeners() {
        window.removeEventListener('mousemove', resetTimer, { passive: true });
        window.removeEventListener('keydown', resetTimer, { passive: true });
        window.removeEventListener('click', resetTimer, { passive: true });
        window.removeEventListener('scroll', resetTimer, { passive: true });
    }

    // Iniciar el temporizador por primera vez (esto también añade los listeners)
    resetTimer();

})(); // Fin de la función autoejecutable