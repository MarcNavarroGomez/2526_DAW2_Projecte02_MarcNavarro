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

// --- Obtener Mesa (GET) ---
$id_mesa = isset($_GET['id_mesa']) ? intval($_GET['id_mesa']) : 0;

if ($id_mesa <= 0) {
    header("Location: index.php");
    exit();
}

// Busca los datos de la mesa
$stmt_mesa = $conn->prepare("SELECT * FROM mesas WHERE id = ?");
$stmt_mesa->execute([$id_mesa]);
$mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

// --- Validación de Estado ---
if (!$mesa || $mesa['estado'] != 1) {
    // Si no está libre, volvemos a la sala
    if ($mesa) {
        header("Location: sala.php?id=" . $mesa['id_sala']);
    } else {
        header("Location: index.php");
    }
    exit();
}

// --- Info de la Sala ---
$id_sala_actual = $mesa['id_sala'];
$stmt_sala_info = $conn->prepare("SELECT nombre, imagen FROM salas WHERE id = ?");
$stmt_sala_info->execute([$id_sala_actual]);
$sala_info = $stmt_sala_info->fetch(PDO::FETCH_ASSOC);
$sala_nombre = $sala_info['nombre'];
$sala_imagen = $sala_info['imagen'];

// URL de cancelación
$sala_redirect_url = 'sala.php?id=' . $id_sala_actual;

// --- Navbar Variables ---
$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? $username);
$rol = $_SESSION['rol'] ?? 1;
$hora = date('H');
if ($hora >= 6 && $hora < 12) {
    $saludo = "Buenos días";
} elseif ($hora >= 12 && $hora < 20) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}

// Lista de salas para el menú lateral
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
    <title>Asignar <?php echo htmlspecialchars($mesa['nombre']); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="../../img/icono.png">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <link rel="stylesheet" href="../../css/panel_principal.css">
    <link rel="stylesheet" href="../../css/salas_general.css">
</head>
<body>
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

    <div class="sala-container">
        <main class="sala-layout" style="background-image: url('../../img/salas/<?= htmlspecialchars($sala_imagen ?? 'default.png') ?>');">
            
            <div class="interstitial-form">
                <h2>Asignar <?php echo htmlspecialchars($mesa['nombre']); ?></h2>
                <p><strong>Sala:</strong> <?php echo htmlspecialchars($sala_nombre); ?></p>
                <p><strong>Capacidad:</strong> <?php echo $mesa['sillas']; ?> comensales</p>
                
        
                <form method="POST" action="../PROCEDIMIENTOS/asignar_mesa.php" id="asignar-mesa-form" class="form-full-page">
                    <input type="hidden" name="id_mesa" value="<?php echo $id_mesa; ?>">
                    
                    <label for="num-comensales">Número de comensales:</label>
                    <input type="number" id="num-comensales" name="num_comensales" min="1" max="<?php echo $mesa['sillas']; ?>" >
                    
                    <input type="hidden" id="max-sillas" value="<?php echo (int)$mesa['sillas']; ?>">

                    
                    <div class="form-actions">
                        <button type="submit" id="btn-asignar" class="btn-primary">Asignar Mesa</button>
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
    
    <script src="../../JS/validar_asignacion.js"></script>
    <script src="../../JS/alert_asignar.js"></script>
    
</body>
</html>
