<?php
// Inicia o reanuda la sesión del usuario
session_start();
// Requiere el archivo de conexión a la base de datos (sube un nivel y entra en CONEXION)
require_once './../CONEXION/conexion.php';

// Inicializa una variable para almacenar mensajes de error
$error = ''; 

// --- Verificación de sesión ---
// Si el usuario no está logueado (no existe 'loginok' o no es true), lo redirige al login
if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
    header("Location: ../PUBLIC/login.php");
    exit(); // Detiene la ejecución
}
// Obtiene el username de la sesión
$username = $_SESSION['username'] ?? null;
// Si el username es nulo (extraña_seguridad), destruye la sesión y redirige al login
if (!$username) {
    session_destroy(); header("Location: ../PUBLIC/login.php"); exit();
}

// --- Consultar ID del camarero ---
// Prepara una consulta para obtener el ID del usuario logueado usando su username
$stmt_camarero = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
$stmt_camarero->execute([':username' => $username]);
$camarero = $stmt_camarero->fetch(PDO::FETCH_ASSOC);
// Si no se encuentra el camarero en la BBDD, destruye la sesión y redirige al login
if (!$camarero) {
    session_destroy(); header("Location: ../PUBLIC/login.php"); exit();
}
// Almacena el ID del camarero logueado
$id_camarero = $camarero['id'];
// Obtiene el ROL del camarero (1=camarero, 2=admin)
$rol = $_SESSION['rol'] ?? 1; 


// --- Variables para el Header ---
// Prepara el nombre para mostrar en el header (con htmlspecialchars por seguridad)
$nombre = htmlspecialchars($_SESSION['nombre'] ?? $username);
// $rol = $_SESSION['rol'] ?? 1; // Ya definida arriba
// Saludo dinámico según la hora
$hora = date('H');
if ($hora >= 6 && $hora < 12) {
    $saludo = "Buenos días";
} elseif ($hora >= 12 && $hora < 20) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}


// --- Obtener Mesa ---
// Obtiene el ID de la mesa enviado por POST desde el formulario de la sala
$id_mesa = $_POST['mesa_id'] ?? null;
// Si no se envió un 'mesa_id', redirige a una sala por defecto
if (!$id_mesa) {
    header("Location: ./../PUBLIC/SALAS/comedor1.php"); 
    exit();
}

// --- Obtener datos de la Mesa y quién la asignó ---
// Prepara una consulta para obtener todos los datos de la mesa, el nombre del camarero que la asignó (u.username)
// y el nombre de la sala (s.nombre)
$stmt_mesa = $conn->prepare("
    SELECT m.*, u.username AS camarero, s.nombre AS sala_nombre, m.asignado_por
    FROM mesas m
    LEFT JOIN users u ON m.asignado_por = u.id
    JOIN salas s ON m.id_sala = s.id
    WHERE m.id = ?
");
$stmt_mesa->execute([$id_mesa]);
$mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

// Si la mesa no se encuentra en la BBDD, guarda un error
if (!$mesa) {
    $error = "Mesa no encontrada.";
}

// --- Info de la Sala ---
// Prepara variables para la sala actual (para CSS y redirección)
$id_sala_actual = $mesa['id_sala'] ?? 0;
$sala_nombre = $mesa['sala_nombre'] ?? 'Sala Desconocida';
// Convierte "Comedor 1" en "comedor1" para el nombre del archivo CSS
$sala_css_class = strtolower(str_replace(' ', '', $sala_nombre));
// Crea la URL de redirección para volver a la sala de origen
$sala_redirect_url = './../PUBLIC/SALAS/' . $sala_css_class . '.php';

// --- Lógica de Liberación (POST) ---
// Comprueba si la página se ha enviado a sí misma (POST) y si se presionó el botón 'confirmar'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar']) && !$error) {
    // Inicia una transacción: si algo falla, se revierte todo
    $conn->beginTransaction();
    try {
        // --- CONTROL DE PERMISOS (Servidor) ---
        // Comprueba si el camarero que intenta liberar NO es quien la asignó Y NO es admin (rol 2)
        if ($mesa['asignado_por'] != $id_camarero && $rol != 2) {
            // Si no tiene permisos, guarda un error
            $error = "No puedes liberar una mesa asignada por otro camarero.";
        } else {
            // --- Si tiene permisos, procede a liberar ---
            
            // 1. Actualiza la mesa: estado=1 (libre), asignado_por=NULL
            $conn->prepare("UPDATE mesas SET estado=1, asignado_por=NULL WHERE id=?")->execute([$id_mesa]);
            
            // 2. Actualiza la ocupación: pone la hora actual en 'final_ocupacion'
            //    Busca la ocupación de esta mesa que AÚN NO ha finalizado (IS NULL)
            $conn->prepare("
                UPDATE ocupaciones SET final_ocupacion=NOW()
                WHERE id_mesa=? AND final_ocupacion IS NULL
                ORDER BY inicio_ocupacion DESC LIMIT 1
            ")->execute([$id_mesa]);

            // Si todo ha ido bien, confirma los cambios en la BBDD
            $conn->commit();
            // Redirige al usuario de vuelta a la sala de la que venía
            header("Location: " . $sala_redirect_url); 
            exit();
        }
    } catch (Exception $e) {
        // Si algo falló (BBDD), revierte todos los cambios
        $conn->rollBack();
        $error = "Error al liberar la mesa: " . $e->getMessage();
    }
}

// --- Consulta para la barra lateral (Navegación de salas) ---
try {
    $stmt_salas = $conn->query("SELECT id, nombre FROM salas");
    $salas = $stmt_salas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar las salas: " . $e->getMessage());
}


// --- Obtener tiempo de inicio de ocupación ---
$ocupacion_tiempo = null;
if ($id_mesa && !$error) {
    // Busca la hora de inicio de la ocupación más reciente de esta mesa
    $stmt_ocupacion_tiempo = $conn->prepare("
        SELECT DATE_FORMAT(o.inicio_ocupacion, '%d/%m/%Y %H:%i:%s') AS tiempo
        FROM ocupaciones o
        WHERE o.id_mesa = ?
        ORDER BY o.inicio_ocupacion DESC
        LIMIT 1;
    ");
    $stmt_ocupacion_tiempo->execute([$id_mesa]);
    $ocupacion_tiempo = $stmt_ocupacion_tiempo->fetch(PDO::FETCH_ASSOC);
}

// Si no se encontró un registro de ocupación (raro, pero posible), guarda un error
if (!$ocupacion_tiempo && !$error) {
    $error_ocupacion = "Inicio de la ocupacion no detectado.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liberar <?php echo htmlspecialchars($mesa['nombre'] ?? 'Mesa'); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="../../JS/liberar_mesa.js"></script> <link rel="stylesheet" href="../../css/panel_principal.css">
    <link rel="stylesheet" href="../../css/salas_general.css">
    <link rel="stylesheet" href="../../css/<?php echo $sala_css_class; ?>.css">
</head>

<body data-user-name="<?php echo htmlspecialchars($nombre); ?>" data-rol="<?php echo (int)$rol; ?>">

<nav class="main-header">
        <div class="header-logo">
            <img src="../../img/basic_logo_blanco.png" alt="Logo GMS">
            <div class="logo-text">
                <span class="gms-title">CASA GMS</span>
            </div>
        </div>

        <div class="header-greeting">
            <?= $saludo ?> <span class="username-tag"><?= $username ?></span>
        </div>

        <div class="header-menu">
            <a href="../PUBLIC/index.php" class="nav-link">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="../PUBLIC/historico.php" class="nav-link">
                <i class="fa-solid fa-chart-bar"></i> Histórico
            </a>
        </div>

        <form method="post" action="logout.php">
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </button>
        </form>
    </nav>

    <div class="sala-container">
        <main class="sala-layout <?php echo $sala_css_class; ?>">
            
            <div class="interstitial-form">
                <h2>Liberar <?php echo htmlspecialchars($mesa['nombre']); ?></h2>
                <p>Asignada por: <strong><?php echo htmlspecialchars($mesa['camarero'] ?? 'N/A'); ?></strong></p>
                <p>Asignada a las: <strong><?php echo htmlspecialchars($ocupacion_tiempo['tiempo'] ?? $error_ocupacion ?? 'N/A'); ?></strong></p>
                <p>¿Seguro que quieres liberar esta mesa?</p>

                <?php if ($error): ?>
                    <div class="form-error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="liberar-mesa-form" class="form-full-page">
                    <input type="hidden" name="mesa_id" value="<?php echo htmlspecialchars($id_mesa ?? ''); ?>">
                    
                    <input type="hidden" id="camarero" value="<?php echo (int)($mesa['asignado_por'] ?? 0); ?>">
                    
                    <input type="hidden" id="camarero_sesion" value="<?php echo (int)$id_camarero; ?>">

                    <div class="form-actions">
                        <button type="submit" id="btn-liberar" name="confirmar" value="1" class="btn-danger">Sí, liberar</button>
                        <a href="<?php echo $sala_redirect_url; ?>" class="btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </main>

        <aside class="salas-navigation">
            <?php foreach ($salas as $sala): ?>
                <?php
                    // Comprueba si esta sala es la activa (la que estamos viendo)
                    $clase_activa = ($sala['id'] == $id_sala_actual) ? 'active' : '';
                    // Genera el nombre del archivo PHP (ej: "comedor1")
                    $nombre_fichero = strtolower(str_replace(' ', '', $sala['nombre']));
                    
                    // Crea la URL para el enlace
                    $url = './../PUBLIC/SALAS/' . $nombre_fichero . ".php"; 
                ?>
                <a href="<?php echo $url; ?>" class="sala-nav-link <?php echo $clase_activa; ?>">
                    <?php echo htmlspecialchars($sala['nombre']); ?>
                </a>
            <?php endforeach; ?>
        </aside>

    </div>

    <script src="../../JS/inactivity_timer.js"></script>

    <script src="../../JS/liberar_mesa.js"></script>
    <script src="../../JS/alert_liberar.js"></script>
    
</body>
</html>