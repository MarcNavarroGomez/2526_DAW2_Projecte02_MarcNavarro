<?php
// Inicia o reanuda la sesión
session_start();

// Requiere el archivo de conexión (sube un nivel y entra a CONEXION)
require_once __DIR__ . '/../CONEXION/conexion.php';

// Recibe usuario y contraseña por POST
// trim() elimina espacios en blanco al inicio y final del username
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validar campos vacíos en el servidor
if ($username === '' || $password === '') {
    // Redirige de vuelta al login con un código de error
    header('Location: ../PUBLIC/login.php?error=campos_vacios');
    exit; // Detiene el script
}

// Validaciones básicas de longitud en el servidor
// mb_strlen() es para contar caracteres multibyte (como ñ, á, ç)
if (mb_strlen($username) < 3) {
    header('Location: ../PUBLIC/login.php?error=usuario_corto');
    exit;
}

if (mb_strlen($password) < 6) {
    header('Location: ../PUBLIC/login.php?error=password_corto');
    exit;
}

// Inicia un bloque try-catch para manejar errores de BBDD
try {
    // Prepara la consulta para buscar al usuario
    // Pide el id, username, nombre, apellido, email, el hash de la contraseña y el rol
    $stmt = $conn->prepare('SELECT id, username, nombre, apellido, email, password_hash, rol FROM users WHERE username = :username LIMIT 1');
    // Ejecuta la consulta pasando el username de forma segura
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC); // Obtiene el resultado

    // Comprobación de credenciales:
    // 1. ¿Existe el usuario? (!$user)
    // 2. ¿Coincide la contraseña enviada con el hash guardado? (!password_verify)
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Si una de las dos falla, redirige con error
        header('Location: ../PUBLIC/login.php?error=credenciales_invalidas');
        exit;
    }

    // --- Login correcto: guardar datos en sesión ---
    
    // Guarda el ID del usuario
    $_SESSION['id_usuario'] = $user['id'];
    // Guarda el username
    $_SESSION['username'] = $user['username'];
    
    // Guarda el Nombre y Apellido concatenados (para el saludo)
    $_SESSION['nombre'] = $user['nombre'] . ' ' . $user['apellido']; 
    
    // Establece la bandera de "login OK"
    $_SESSION['loginok'] = true;
    
    // Guarda el ROL del usuario (1=camarero, 2=admin)
    $_SESSION['rol'] = $user['rol'];

    // Establece la "bandera" para mostrar el mensaje de bienvenida (SweetAlert)
    $_SESSION['show_welcome_message'] = true; 

    // Redirige al panel principal (index.php)
    header('Location: ../PUBLIC/index.php');
    exit;

} catch (PDOException $e) {
    // Si hubo un error en la consulta (ej. BBDD caída), redirige con error genérico
    header('Location: ../PUBLIC/login.php?error=error_bd');
    exit;
}
?>