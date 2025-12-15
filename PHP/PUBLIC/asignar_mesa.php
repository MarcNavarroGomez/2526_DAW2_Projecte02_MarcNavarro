<?php
// Inicia o reanuda la sesión del usuario
session_start();

// Incluye el archivo de conexión a la base de datos
require_once __DIR__ . '/../CONEXION/conexion.php';

// --- Verificación de sesión ---
// Si la variable 'loginok' no está seteada o no es true, redirige al login
if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
    header("Location: login.php");
    exit();
}

// Obtener nombre de usuario o fallback a null
$username = $_SESSION['username'] ?? null;
// Si no hay usuario válido, destruir sesión y redirigir
if (!$username) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// --- Obtener Mesa (GET) ---
// Recuperar ID de la mesa desde la URL, asegurando que sea un entero
$id_mesa = isset($_GET['id_mesa']) ? intval($_GET['id_mesa']) : 0;

// Validación básica del ID de la mesa
if ($id_mesa <= 0) {
    header("Location: index.php");
    exit();
}

// Busca los datos de la mesa solicitada en la BD
$stmt_mesa = $conn->prepare("SELECT * FROM mesas WHERE id = ?");
$stmt_mesa->execute([$id_mesa]);
$mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

// --- Validación de Estado ---
// Solo se puede ASIGNAR una mesa si su estado es 1 (LIBRE)
if (!$mesa || $mesa['estado'] != 1) {
    // Si la mesa existe pero no está libre, devolver a la vista de la sala correspondiente
    if ($mesa) {
        header("Location: sala.php?id=" . $mesa['id_sala']);
    } else {
        // Si la mesa no existe, al index
        header("Location: index.php");
    }
    exit();
}

// --- Info de la Sala ---
// Obtener info de la sala a la que pertenece la mesa para mostrar contexto y volver
$id_sala_actual = $mesa['id_sala'];
$stmt_sala_info = $conn->prepare("SELECT nombre, imagen FROM salas WHERE id = ?");
$stmt_sala_info->execute([$id_sala_actual]);
$sala_info = $stmt_sala_info->fetch(PDO::FETCH_ASSOC);
$sala_nombre = $sala_info['nombre'];
$sala_imagen = $sala_info['imagen'];

// URL para el botón "Cancelar"
$sala_redirect_url = 'sala.php?id=' . $id_sala_actual;

// --- Navbar Variables ---
// Preparar datos para el header común
$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? $username);
$rol = $_SESSION['rol'] ?? 1;

// Cálculo del saludo según la hora
$hora = date('H');
if ($hora >= 6 && $hora < 12) {
    $saludo = "Buenos días";
} elseif ($hora >= 12 && $hora < 20) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}

// Lista de salas para el menú lateral de navegación
try {
    $stmt_salas = $conn->query("SELECT id, nombre FROM salas ORDER BY nombre");
    $salas = $stmt_salas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Título dinámico -->
    <title>Asignar <?php echo htmlspecialchars($mesa['nombre']); ?></title>
    
    <!-- Fuentes y estilos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="../../img/icono.png">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- CSS Propio -->
    <link rel="stylesheet" href="../../css/panel_principal.css">
    <link rel="stylesheet" href="../../css/salas_general.css">
</head>
<body>
    <!-- Header Principal -->
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
            <a href="index.php" class="nav-link">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="historico.php" class="nav-link">
                <i class="fa-solid fa-chart-bar"></i> Histórico
            </a>
            <?php if ($rol == 2): ?>
                <a href="ADMIN/admin_panel.php" class="nav-link">
                    <i class="fa-solid fa-gear"></i> Admin
                </a>
            <?php endif; ?>
        </div>

        <form method="post" action="../PROCEDIMIENTOS/logout.php">
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </button>
        </form>
    </nav>

    <!-- Contenedor Principal -->
    <div class="sala-container">
        <!-- Area Principal: Formulario de Asignación -->
        <!-- Fondo personalizado con la imagen de la sala -->
        <main class="sala-layout" style="background-image: url('../../img/salas/<?= htmlspecialchars($sala_imagen ?? 'default.png') ?>');">
            
            <!-- TARJETA/MODAL VISUAL EN EL CENTRO DE LA PANTALLA -->
            <div class="interstitial-form">
                <h2>Asignar <?php echo htmlspecialchars($mesa['nombre']); ?></h2>
                <p><strong>Sala:</strong> <?php echo htmlspecialchars($sala_nombre); ?></p>
                <p><strong>Capacidad:</strong> <?php echo $mesa['sillas']; ?> comensales</p>
                
                <!-- Formulario que envía al PROCEDIMIENTO -->
                <form method="POST" action="../PROCEDIMIENTOS/asignar_mesa.php" id="asignar-mesa-form" class="form-full-page">
                    <input type="hidden" name="id_mesa" value="<?php echo $id_mesa; ?>">
                    
                    <!-- Input para número de comensales real -->
                    <label for="num-comensales">Número de comensales:</label>
                    <input type="number" id="num-comensales" name="num_comensales" min="1" max="<?php echo $mesa['sillas']; ?>" >
                    
                    <!-- Campo oculto para validación JS del máximo -->
                    <input type="hidden" id="max-sillas" value="<?php echo (int)$mesa['sillas']; ?>">

                    <div class="form-actions">
                        <button type="submit" id="btn-asignar" class="btn-primary">Asignar Mesa</button>
                        <!-- Botón cancelar regresa a la sala -->
                        <a href="<?php echo $sala_redirect_url; ?>" class="btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </main>

        <!-- Barra lateral de navegación -->
        <aside class="salas-navigation">
            <?php foreach ($salas as $sala): ?>
                <?php
                    // Marcar sala activa si coincide
                    $clase_activa = ($sala['id'] == $id_sala_actual) ? 'active' : '';
                    $url = 'sala.php?id=' . $sala['id']; 
                ?>
                <a href="<?php echo $url; ?>" class="sala-nav-link <?php echo $clase_activa; ?>">
                    <?php echo htmlspecialchars($sala['nombre']); ?>
                </a>
            <?php endforeach; ?>
        </aside>

    </div>
    
    <!-- Scripts de validación y feedback -->
    <script src="../../JS/validar_asignacion.js"></script>
    <script src="../../JS/alert_asignar.js"></script>
    
</body>
</html>
