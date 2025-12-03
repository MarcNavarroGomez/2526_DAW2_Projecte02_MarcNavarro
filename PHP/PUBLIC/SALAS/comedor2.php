<?php
// ===============================
// COMEDOR 2 - Casa GMS
// ===============================

// --- INICIO DE SESIÓN ---
session_start();
require_once '../../CONEXION/conexion.php';

// --- VERIFICACIÓN DE SESIÓN ---
if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
    header("Location: ../login.php");
    exit();
}

// --- CORRECCIÓN: Comprobar que exista username ---
if (!isset($_SESSION['username'])) {
    session_destroy();
    header("Location: ../login.php?error=session_expired");
    exit();
}

$username = $_SESSION['username'];

// --- CONSULTAR ID DEL CAMARERO (seguridad extra) ---
$stmt = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
$stmt->execute([':username' => $username]);
$camarero = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Si no se encuentra, cerrar sesión ---
if (!$camarero) {
    session_destroy();
    header("Location: ../login.php?error=user_not_found");
    exit();
}

$id_camarero = $camarero['id'];

// --- VARIABLES PARA EL HEADER ---
$nombre = htmlspecialchars($_SESSION['nombre'] ?? $username);
$rol = $_SESSION['rol'] ?? 1;
$saludo = "Buenos días"; // Puedes personalizar según la hora

// --- VARIABLES DE SALA ---
$id_sala_actual = 5; 
$nombre_sala_actual = "Comedor 2";

// --- CONSULTA: MESAS DE LA SALA ---
try {
    $stmt_mesas = $conn->prepare("
        SELECT m.*, u.username AS camarero
        FROM mesas m
        LEFT JOIN users u ON m.asignado_por = u.id
        WHERE m.id_sala = :sala
    ");
    $stmt_mesas->execute(['sala' => $id_sala_actual]);
    $mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar las mesas: " . $e->getMessage());
}

// --- CONSULTA: SALAS PARA NAVEGACIÓN ---
try {
    $stmt_salas = $conn->query("SELECT id, nombre FROM salas");
    $salas = $stmt_salas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar las salas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nombre_sala_actual); ?> - Casa GMS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="../../../img/icono.png">

    <link rel="stylesheet" href="../../../css/panel_principal.css">
    <link rel="stylesheet" href="../../../css/salas_general.css">
    <link rel="stylesheet" href="../../../css/comedor2.css">
</head>
<body>

    <?php 
    // --- HEADER GLOBAL ---
    require_once '../header.php'; 
    ?>

    <div class="sala-container">

        <main class="sala-layout comedor2">

            <div class="sala-layout-dropdown dropdown">
                <button class="btn btn-salas" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-layer-group"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($salas as $sala_dropdown): ?>
                        <?php
                            $clase_activa_dropdown = ($sala_dropdown['id'] == $id_sala_actual) ? 'active' : '';
                            $nombre_fichero_dropdown = strtolower(str_replace(' ', '', $sala_dropdown['nombre']));
                            $url_dropdown = $nombre_fichero_dropdown . ".php"; 
                        ?>
                        <li>
                            <a class="dropdown-item <?php echo $clase_activa_dropdown; ?>" href="<?php echo $url_dropdown; ?>">
                                <?php echo htmlspecialchars($sala_dropdown['nombre']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php foreach ($mesas as $mesa): ?>
                <?php 
                    $clase = $mesa['estado'] == 2 ? 'ocupada' : 'libre';
                    $accion = $mesa['estado'] == 1 
                        ? './../../PROCEDIMIENTOS/asignar_mesa.php' 
                        : './../../PROCEDIMIENTOS/liberar_mesa.php';
                ?>
                <form action="<?php echo $accion; ?>" method="POST" class="mesa-form">
                    <input type="hidden" name="mesa_id" value="<?php echo $mesa['id']; ?>">

                    <button type="submit" class="mesa <?php echo $clase; ?>" id="mesa-<?php echo $mesa['id']; ?>">

                        <img src="../../../img/mesa2.png" alt="Mesa" class="mesa-img">
                        <span class="mesa-label"><?php echo htmlspecialchars($mesa['nombre']); ?></span>

                        <div class="mesa-sillas">
                            <i class="fa-solid fa-chair"></i> <?php echo $mesa['sillas']; ?>
                        </div>

                        <?php if ($mesa['estado'] == 2): ?>
                            <div class="mesa-camarero">
                                Asig: <?php echo htmlspecialchars($mesa['camarero'] ?? 'N/A'); ?>
                            </div>
                        <?php endif; ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </main>

        <aside class="salas-navigation">
            <?php foreach ($salas as $sala): ?>
                <?php
                    $clase_activa = ($sala['id'] == $id_sala_actual) ? 'active' : '';
                    
                    // Lógica coherente con Comedor 1
                    $nombre_fichero = strtolower(str_replace(' ', '', $sala['nombre']));
                   
                    $url = $nombre_fichero . ".php"; 
                ?>
                <a href="<?php echo $url; ?>" class="sala-nav-link <?php echo $clase_activa; ?>">
                    <?php echo htmlspecialchars($sala['nombre']); ?>
                </a>
            <?php endforeach; ?>
        </aside>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>