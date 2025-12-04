<?php
// Inicia o reanuda la sesión
session_start();

// 1. Redirigir si ya está autenticado
// Si el usuario ya tiene una sesión (id_usuario), no debe ver el login
if (isset($_SESSION["id_usuario"])) {
    // Lo redirige al panel principal (index.php)
    header('Location: ../../index.php'); 
    exit; // Detiene la ejecución del script
}

// 2. Incluir el archivo de conexión a la BBDD
require '../conexion/conexion.php'; 

// Inicializa variables
$camareros = []; // Array para almacenar la lista de usuarios
$db_error = null; // Variable para guardar errores de BBDD

try {
    // 3. Obtener solo los camareros (rol=1) y que no estén dados de baja
    $stmt = $conn->query("SELECT id, username, nombre, apellido FROM users WHERE fecha_baja IS NULL ORDER BY nombre");
    $camareros = $stmt->fetchAll(PDO::FETCH_ASSOC); // Obtiene los resultados
    
} catch (PDOException $e) {
    // 4. Manejo de error de la base de datos
    // Si la consulta falla, guarda un mensaje de error genérico
    $db_error = "Error al cargar la lista de usuarios. Contacte al administrador.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Casa GMS</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../../img/icono.png"> <link rel="stylesheet" href="../../css/login.css"> </head>
<body>

    <div class="top-logo">
        <img src="../../img/basic_logo.png" alt="Logo Guillem Samuel y Marc">
    </div>


    <main> <div class="logo-card">
            <img src="../../img/casa_gms.png" alt="Logo Casa GMS">
        </div>

        <div class="login-container">
            
            <h1 class="login-title">LOGIN</h1>

            <?php if (isset($_GET['error']) || $db_error): ?>
                <div class="error">
                    <?php
                    if ($db_error) {
                        // Muestra el error de BBDD si existe
                        echo $db_error;
                    } else {
                        // Muestra mensajes de error según el parámetro 'error' de la URL
                        switch ($_GET['error']) {
                            case 'campos_vacios':
                                echo 'Por favor, completa todos los campos.';
                                break;
                            case 'credenciales_invalidas':
                                echo 'Usuario o contraseña incorrectos.';
                                break;
                            case 'usuario_corto':
                                echo 'El nombre de usuario es demasiado corto (mín. 3 caracteres).';
                                break;
                            case 'password_corto':
                                echo 'La contraseña es demasiado corta (mín. 6 caracteres).';
                                break;
                            case 'error_bd':
                                echo 'Error de servidor. Intenta más tarde.';
                                break;
                            default:
                                echo 'Error en el inicio de sesión.';
                                break;
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="post" action="../PROCEDIMIENTOS/procesar_login.php" novalidate>
                
                <div class="input-group select-wrapper">
                    <i class="fa-solid fa-user"></i> <select id="username" name="username">
                        <option value="" disabled selected>Selecciona tu usuario</option>
                        <?php foreach ($camareros as $camarero): ?>
                            <option value="<?php echo htmlspecialchars($camarero['username']); ?>">
                                <?php echo htmlspecialchars($camarero['nombre'] . ' ' . $camarero['apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group">
                    <i class="fa-solid fa-lock"></i> <input type="password" id="password" name="password" placeholder="Contraseña">
                </div>

                <button type="submit">Iniciar sesión</button>
            </form>
        </div>
    </main>
    <script src="../../JS/validar_login.js"></script>
    </body>
</html>