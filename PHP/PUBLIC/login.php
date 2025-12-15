<?php
// Inicia o reanuda la sesión existente en el servidor para poder acceder a $_SESSION
session_start();

// Comprueba si la variable de sesión 'id_usuario' ya está definida (si el usuario ya hizo login)
if (isset($_SESSION["id_usuario"])) {
    // Si el usuario ya está logueado, lo redirige directamente a la página principal
    header('Location: ../../index.php'); 
    // Detiene la ejecución inmediata del script para que no se cargue el resto de la página
    exit; 
}

// Incluye el archivo de conexión a la base de datos, necesario para realizar consultas
require '../conexion/conexion.php'; 

// Inicializa la variable $camareros como un array vacío para evitar errores si la consulta falla
$camareros = []; 
// Inicializa la variable $db_error como null para controlar si ocurren errores de conexión
$db_error = null; 

// Inicia un bloque try para intentar ejecutar comandos de base de datos que podrían fallar
try {
    // Ejecuta una consulta SQL para seleccionar id, username, nombre y apellido de los usuarios activos
    // Filtra por 'fecha_baja IS NULL' para excluir usuarios dados de baja y ordena por nombre
    $stmt = $conn->query("SELECT id, username, nombre, apellido FROM users WHERE fecha_baja IS NULL ORDER BY nombre");
    
    // Recupera todas las filas resultantes de la consulta y las almacena en el array $camareros
    // PDO::FETCH_ASSOC hace que el array sea asociativo (claves con nombres de columna)
    $camareros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
// Captura excepciones de tipo PDOException que ocurren si hay problemas con la base de datos
} catch (PDOException $e) {
    // Asigna un mensaje de error amigable a la variable $db_error para mostrarlo después
    $db_error = "Error al cargar la lista de usuarios. Contacte al administrador.";
}
?>

<!DOCTYPE html>
<!-- Define el idioma del documento HTML como español -->
<html lang="es">
<head>
    <!-- Establece la codificación de caracteres a UTF-8 para soportar tildes y ñ -->
    <meta charset="UTF-8">
    <!-- Configura la vista para dispositivos móviles, asegurando que se escale correctamente -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Define el título que aparecerá en la pestaña del navegador -->
    <title>Login - Casa GMS</title>
    
    <!-- Preconecta con Google Fonts para mejorar la velocidad de carga de fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Carga la fuente 'Poppins' desde Google Fonts con diferentes pesos -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Carga la librería de iconos Font Awesome desde un CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Vincula el icono de la pestaña (favicon) -->
    <link rel="icon" type="image/png" href="../../img/icono.png"> 
    <!-- Vincula la hoja de estilos CSS específica para la página de login -->
    <link rel="stylesheet" href="../../css/login.css"> 
</head>
<body>

    <!-- Contenedor para el logo superior pequeño -->
    <div class="top-logo">
        <img src="../../img/basic_logo.png" alt="Logo Guillem Samuel y Marc">
    </div>


    <main> 
        <!-- Contenedor para el logo principal de la tarjeta -->
        <div class="logo-card">
            <img src="../../img/casa_gms.png" alt="Logo Casa GMS">
        </div>

        <!-- Contenedor principal del formulario de login -->
        <div class="login-container">
            
            <!-- Título del formulario -->
            <h1 class="login-title">LOGIN</h1>

            <!-- Comprueba si existe un parámetro 'error' en la URL o si hubo un error de BD -->
            <?php if (isset($_GET['error']) || $db_error): ?>
                <!-- Contenedor para mostrar mensajes de error -->
                <div class="error">
                    <?php
                    // Si hay un error de base de datos capturado anteriormente
                    if ($db_error) {
                        // Muestra el mensaje de error de la BD
                        echo $db_error;
                    } else {
                        // Si no es error de BD, analiza el código de error enviado por GET desde procesar_login.php
                        switch ($_GET['error']) {
                            // Caso: campos vacíos en el formulario
                            case 'campos_vacios':
                                echo 'Por favor, completa todos los campos.';
                                break;
                            // Caso: usuario o contraseña incorrectos
                            case 'credenciales_invalidas':
                                echo 'Usuario o contraseña incorrectos.';
                                break;
                            // Caso: nombre de usuario muy corto
                            case 'usuario_corto':
                                echo 'El nombre de usuario es demasiado corto (mín. 3 caracteres).';
                                break;
                            // Caso: contraseña muy corta
                            case 'password_corto':
                                echo 'La contraseña es demasiado corta (mín. 6 caracteres).';
                                break;
                            // Caso: error genérico de base de datos
                            case 'error_bd':
                                echo 'Error de servidor. Intenta más tarde.';
                                break;
                            // Caso por defecto para cualquier otro error
                            default:
                                echo 'Error en el inicio de sesión.';
                                break;
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Formulario de inicio de sesión. Envía datos por POST a procesar_login.php -->
            <!-- novalidate desactiva la validación HTML5 por defecto para usar nuestra propia validación JS o PHP -->
            <form id="loginForm" method="post" action="../PROCEDIMIENTOS/procesar_login.php" novalidate>
                
                <!-- Grupo de entrada para el selector de usuario -->
                <div class="input-group select-wrapper">
                    <!-- Icono de usuario -->
                    <i class="fa-solid fa-user"></i> 
                    <!-- Select desplegable con los nombres de usuario cargados de la BD -->
                    <select id="username" name="username">
                        <!-- Opción por defecto deshabilitada -->
                        <option value="" disabled selected>Selecciona tu usuario</option>
                        <!-- Bucle foreach para iterar sobre cada usuario obtenido de la BD -->
                        <?php foreach ($camareros as $camarero): ?>
                            <!-- Opción con el username como valor y nombre completo como texto visible -->
                            <!-- htmlspecialchars previene inyección de código HTML/JS -->
                            <option value="<?php echo htmlspecialchars($camarero['username']); ?>">
                                <?php echo htmlspecialchars($camarero['nombre'] . ' ' . $camarero['apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Grupo de entrada para la contraseña -->
                <div class="input-group">
                    <!-- Icono de candado -->
                    <i class="fa-solid fa-lock"></i> 
                    <!-- Campo de contraseña -->
                    <input type="password" id="password" name="password" placeholder="Contraseña">
                </div>

                <!-- Botón de envío del formulario -->
                <button type="submit">Iniciar sesión</button>
            </form>
        </div>
    </main>
    <!-- Enlace al archivo JavaScript para validación del cliente -->
    <script src="../../JS/validar_login.js"></script>
    </body>
</html>