<?php
session_start();
require_once __DIR__ . '/../CONEXION/conexion.php';

// --- Verificación de sesión ---
if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? null;
if (!$username) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$rol = $_SESSION['rol'] ?? 1;

// --- Obtener Mesa (GET) ---
$id_mesa = isset($_GET['id_mesa']) ? intval($_GET['id_mesa']) : 0;

if ($id_mesa <= 0) {
    header("Location: index.php");
    exit();
}

// --- Obtener datos de la Mesa ---
$stmt_mesa = $conn->prepare("
    SELECT m.*, u.username AS camarero, s.nombre AS sala_nombre, s.imagen AS sala_imagen, m.asignado_por
    FROM mesas m
    LEFT JOIN users u ON m.asignado_por = u.id
    JOIN salas s ON m.id_sala = s.id
    WHERE m.id = ?
");
$stmt_mesa->execute([$id_mesa]);
$mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

if (!$mesa) {
    header("Location: index.php");
    exit();
}

// --- Info Sala ---
$id_sala_actual = $mesa['id_sala'];
$sala_nombre = $mesa['sala_nombre'];
$sala_imagen = $mesa['sala_imagen'];
$sala_redirect_url = 'sala.php?id=' . $id_sala_actual;

// --- Obtener tiempo de ocupación ---
$stmt_ocupacion_tiempo = $conn->prepare("
    SELECT DATE_FORMAT(o.inicio_ocupacion, '%d/%m/%Y %H:%i:%s') AS tiempo
    FROM ocupaciones o
    WHERE o.id_mesa = ?
    ORDER BY o.inicio_ocupacion DESC
    LIMIT 1;
");
$stmt_ocupacion_tiempo->execute([$id_mesa]);
$ocupacion_tiempo = $stmt_ocupacion_tiempo->fetch(PDO::FETCH_ASSOC);

$camarero_id_actual = $_SESSION['id_usuario'] ?? 0;
// Note: In PHP scripts, typically id is stored in session.
// Need to fetch user id from username if not present directly, or use procedural logic.
// In asignar_mesa.php we did query. Let's replicate obtaining ID.
$stmt_uid = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt_uid->execute([$username]);
$u_data = $stmt_uid->fetch(PDO::FETCH_ASSOC);
$id_camarero_sesion = $u_data['id'] ?? 0;

// Navbar vars
$hora = date('H');
if ($hora >= 6 && $hora < 12) {
    $saludo = "Buenos días";
} elseif ($hora >= 12 && $hora < 20) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}

// Salas sidebar
try {
    $stmt_salas = $conn->query("SELECT id, nombre FROM salas ORDER BY nombre");
    $salas = $stmt_salas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liberar <?php echo htmlspecialchars($mesa['nombre']); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="../../JS/liberar_mesa.js"></script> 
    <link rel="stylesheet" href="../../css/panel_principal.css">
    <link rel="stylesheet" href="../../css/salas_general.css">
</head>

<body data-user-name="<?php echo htmlspecialchars($username); ?>" data-rol="<?php echo (int)$rol; ?>">

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
        </div>

        <form method="post" action="../PROCEDIMIENTOS/logout.php">
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </button>
        </form>
    </nav>

    <div class="sala-container">
        <main class="sala-layout" style="background-image: url('../../img/salas/<?= htmlspecialchars($sala_imagen ?? 'default.png') ?>');">
            
            <div class="interstitial-form">
                <h2>Liberar <?php echo htmlspecialchars($mesa['nombre']); ?></h2>
                <p>Asignada por: <strong><?php echo htmlspecialchars($mesa['camarero'] ?? 'N/A'); ?></strong></p>
                <p>Asignada a las: <strong><?php echo htmlspecialchars($ocupacion_tiempo['tiempo'] ?? 'N/A'); ?></strong></p>
                <p>¿Seguro que quieres liberar esta mesa?</p>

                <!-- Check permisos visual (Logic in backend too) -->
                <?php 
                $puede_liberar = ($mesa['asignado_por'] == $id_camarero_sesion || $rol == 2);
                if (!$puede_liberar): 
                ?>
                    <div class="form-error-message">
                        No tienes permisos para liberar esta mesa (asignada por otro).
                    </div>
                <?php endif; ?>

                <form method="POST" action="../PROCEDIMIENTOS/liberar_mesa.php" id="liberar-mesa-form" class="form-full-page">
                    <input type="hidden" name="id_mesa" value="<?php echo htmlspecialchars($id_mesa); ?>">
                    
                    <input type="hidden" id="camarero" value="<?php echo (int)($mesa['asignado_por'] ?? 0); ?>">
                    <input type="hidden" id="camarero_sesion" value="<?php echo (int)$id_camarero_sesion; ?>">

                    <div class="form-actions">
                        <?php if ($puede_liberar): ?>
                            <button type="submit" id="btn-liberar" name="confirmar" value="1" class="btn-danger">Sí, liberar</button>
                        <?php else: ?>
                            <button type="button" disabled class="btn-danger" style="opacity: 0.5; cursor: not-allowed;">Sin Permiso</button>
                        <?php endif; ?>
                        
                        <a href="<?php echo $sala_redirect_url; ?>" class="btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </main>

        <aside class="salas-navigation">
            <?php foreach ($salas as $sala): ?>
                <?php
                    $clase_activa = ($sala['id'] == $id_sala_actual) ? 'active' : '';
                    $url = 'sala.php?id=' . $sala['id']; 
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
