<?php
// Inicia o reanuda la sesión existente en el servidor para almacenar variables de usuario
session_start();

// Requiere el archivo de conexión a la base de datos (utilizando __DIR__ para una ruta absoluta segura)
// Sube un nivel ('/../') y entra en el directorio 'CONEXION'
require_once __DIR__ . '/../CONEXION/conexion.php';

// Obtiene el 'username' del array superglobal $_POST. Si no existe, asigna una cadena vacía.
// trim() elimina los espacios en blanco al principio y al final del texto.
$username = trim($_POST['username'] ?? '');
// Obtiene la 'password' del array $_POST. Si no existe, asigna una cadena vacía.
$password = $_POST['password'] ?? '';

// Comprueba si el usuario o la contraseña están vacíos después de limpiar
if ($username === '' || $password === '') {
    // Si alguno está vacío, redirige de vuelta al login indicando el error 'campos_vacios'
    header('Location: ../PUBLIC/login.php?error=campos_vacios');
    // Finaliza la ejecución del script inmediatamente
    exit; 
}

// Verifica la longitud del nombre de usuario (mínimo 3 caracteres)
// mb_strlen() cuenta correctamente los caracteres multibyte (como tildes)
if (mb_strlen($username) < 3) {
    // Redirige al login con error 'usuario_corto'
    header('Location: ../PUBLIC/login.php?error=usuario_corto');
    // Salida del script
    exit;
}

// Verifica la longitud de la contraseña (mínimo 6 caracteres)
if (mb_strlen($password) < 6) {
    // Redirige al login con error 'password_corto'
    header('Location: ../PUBLIC/login.php?error=password_corto');
    // Salida del script
    exit;
}

// Bloque try para manejar posibles errores de conexión SQL
try {
    // Prepara una sentencia SQL para buscar al usuario por su nombre de usuario.
    // LIMIT 1 asegura que solo devuelva un registro.
    $stmt = $conn->prepare('SELECT id, username, nombre, apellido, email, password_hash, rol FROM users WHERE username = :username LIMIT 1');
    
    // Ejecuta la consulta pasando el parámetro :username para evitar inyección SQL
    $stmt->execute([':username' => $username]);
    
    // Obtiene el resultado como un array asociativo. Si no hay coincidencias, devuelve false.
    $user = $stmt->fetch(PDO::FETCH_ASSOC); 

    // Verificación de credenciales:
    // 1. !$user: Si no se encontró ningún usuario con ese nombre.
    // 2. !password_verify(...): Verifica si la contraseña ingresada coincide con el hash almacenado.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Si el usuario no existe o la contraseña es incorrecta, redirige con error 'credenciales_invalidas'
        header('Location: ../PUBLIC/login.php?error=credenciales_invalidas');
        // Termina el script
        exit;
    }

    // --- Si pasa las verificaciones, el login es correcto ---
    
    // Guarda el ID único del usuario en la sesión
    $_SESSION['id_usuario'] = $user['id'];
    // Guarda el nombre de usuario (username) en la sesión
    $_SESSION['username'] = $user['username'];
    
    // Concatena Nombre y Apellido para mostrar un saludo completo y lo guarda en sesión
    $_SESSION['nombre'] = $user['nombre'] . ' ' . $user['apellido']; 
    
    // Establece una variable de control 'loginok' a true para verificar el estado de login en otras páginas
    $_SESSION['loginok'] = true;
    
    // Guarda el rol del usuario (ej: 1 para camarero, 2 para admin) para control de acceso
    $_SESSION['rol'] = $user['rol'];

    // Establece una bandera para mostrar el mensaje de bienvenida "Toast" en la página de inicio
    $_SESSION['show_welcome_message'] = true; 

    // Redirige al usuario al panel principal del sitio
    header('Location: ../PUBLIC/index.php');
    // Finaliza el script
    exit;

// Captura cualquier excepción relacionada con PDO (errores de base de datos)
} catch (PDOException $e) {
    // Si hay un error técnico, redirige con 'error_bd'
    header('Location: ../PUBLIC/login.php?error=error_bd');
    // Finaliza el script
    exit;
}
?>