<?php
// ===============================
// PRIVADA 4 - Vista de Sala PÃºblica
// ===============================

// Inicializar sesiÃ³n
session_start();
// Fichero conexiÃ³n
require_once '../../CONEXION/conexion.php';

// --- SEGURIDAD SESIÃ“N ---
// Verificar login
if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Verificar nombre de usuario set
if (!isset($_SESSION['username'])) {
    session_destroy();
    header("Location: ../login.php?error=session_expired");
    exit();
}

$username = $_SESSION['username'];

// --- SEGURIDAD USUARIO ---
// Obtener ID para evitar problemas futuros de consistencia
try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $camarero = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener el camarero: " . $e->getMessage());
}

if (!$camarero) {
    session_destroy();
    header("Location: ../login.php?error=user_not_found");
    exit();
}

$id_camarero = $camarero['id'];

// --- CABECERA ---
$nombre = htmlspecialchars($_SESSION['nombre'] ?? $username);
$rol = $_SESSION['rol'] ?? 1;
$saludo = "Buenos días";

// --- SALA ACTUAL ---
$id_sala_actual = 9; 
$nombre_sala_actual = "Privada 4";

// --- CONSULTA MESAS ---
// Mesas de Privada 4
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

// --- CONSULTA LISTA SALAS ---
// Para generar el menú
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

    <!-- Hojas de Estilo Externas -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="../../../img/icono.png">

    <!-- Hojas de Estilo Internas -->
    <link rel="stylesheet" href="../../../css/panel_principal.css">
    <link rel="stylesheet" href="../../../css/salas_general.css">
    <!-- Estilo propio Privada 4 -->
    <link rel="stylesheet" href="../../../css/privada4.css">
    <link rel="stylesheet" href="../../../css/mesas_privadas.css">
</head>
<body>

    <?php require_once '../header.php'; ?>

    <div class="sala-container">
        <!-- Layout Sala Privada 4 -->
        <main class="sala-layout privada4">

            <!-- MenÃº Desplegable (Mobile) -->
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

            <!-- VisualizaciÃ³n de Mesas -->
            <?php foreach ($mesas as $mesa): ?>
                <?php 
                    $clase = $mesa['estado'] == 2 ? 'ocupada' : 'libre';
                    $url_destino = $mesa['estado'] == 1 
                        ? '../asignar_mesa.php?id_mesa=' . $mesa['id'] 
                        : '../liberar_mesa.php?id_mesa=' . $mesa['id'];
                ?>
                <a href="<?php echo $url_destino; ?>" class="mesa <?php echo $clase; ?>" id="mesa-<?php echo $mesa['id']; ?>" style="text-decoration: none; display: block; cursor: pointer;">
                    
                    <!-- Imagen Privada 4 -->
                    <img src="../../../img/mesa_privada4.png" alt="Mesa" class="mesa-img">
                    <span class="mesa-label"><?php echo htmlspecialchars($mesa['nombre']); ?></span>

                    <div class="mesa-sillas">
                        <i class="fa-solid fa-chair"></i> <?php echo $mesa['sillas']; ?>
                    </div>

                    <?php if ($mesa['estado'] == 2): ?>
                        <div class="mesa-camarero">
                            Asig: <?php echo htmlspecialchars($mesa['camarero'] ?? 'N/A'); ?>
                        </div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </main>

        <!-- NavegaciÃ³n Lateral (Desktop) -->
        <aside class="salas-navigation">
            <?php foreach ($salas as $sala): ?>
                <?php
                    $clase_activa = ($sala['id'] == $id_sala_actual) ? 'active' : '';
                    $nombre_fichero = strtolower(str_replace(' ', '', $sala['nombre']));
                    $url = $nombre_fichero . ".php"; 
                ?>
                <a href="<?php echo $url; ?>" class="sala-nav-link <?php echo $clase_activa; ?>">
                    <?php echo htmlspecialchars($sala['nombre']); ?>
                </a>
            <?php endforeach; ?>
        </aside>

    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>