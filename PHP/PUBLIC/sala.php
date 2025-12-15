<?php
session_start();
require_once __DIR__ . '/../CONEXION/conexion.php';

// Verificar sesión
if (!isset($_SESSION['loginok'])) {
    header("Location: login.php");
    exit();
}

// Obtener ID de sala desde URL
$id_sala = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_sala <= 0) {
    header("Location: index.php?error=sala_invalida");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$rol = $_SESSION['rol'] ?? 1;

// Obtener información de la sala
try {
    $stmt_sala = $conn->prepare("SELECT id, nombre, imagen FROM salas WHERE id = :id");
    $stmt_sala->execute([':id' => $id_sala]);
    $sala = $stmt_sala->fetch(PDO::FETCH_ASSOC);
    
    if (!$sala) {
        header("Location: index.php?error=sala_no_encontrada");
        exit();
    }
    
    // Obtener mesas de esta sala y verificar reservas activas
    $stmt_mesas = $conn->prepare("
        SELECT 
            m.id,
            m.nombre,
            m.sillas,
            m.estado,
            m.asignado_por,
            u.username as camarero_nombre,
            -- Reserva Activa AHORA (Camarero)
            (
                SELECT u2.username
                FROM ocupaciones o 
                JOIN users u2 ON o.id_camarero = u2.id
                WHERE o.id_mesa = m.id 
                AND o.inicio_ocupacion <= NOW() 
                AND (o.final_ocupacion IS NULL OR o.final_ocupacion > NOW())
                AND o.id_reserva IS NOT NULL
                LIMIT 1
            ) as camarero_reserva_activa,
            -- Reserva Activa AHORA (Hora Inicio)
            (
                SELECT DATE_FORMAT(o.inicio_ocupacion, '%H:%i')
                FROM ocupaciones o 
                WHERE o.id_mesa = m.id 
                AND o.inicio_ocupacion <= NOW() 
                AND (o.final_ocupacion IS NULL OR o.final_ocupacion > NOW())
                AND o.id_reserva IS NOT NULL
                LIMIT 1
            ) as hora_reserva_activa,
            -- Próxima Reserva HOY
            (
                SELECT DATE_FORMAT(o.inicio_ocupacion, '%H:%i')
                FROM ocupaciones o
                WHERE o.id_mesa = m.id
                AND o.inicio_ocupacion > NOW()
                AND DATE(o.inicio_ocupacion) = CURDATE()
                AND o.id_reserva IS NOT NULL
                ORDER BY o.inicio_ocupacion ASC
                LIMIT 1
            ) as proxima_reserva_hora
        FROM mesas m
        LEFT JOIN users u ON m.asignado_por = u.id
        WHERE m.id_sala = :id_sala
        ORDER BY m.nombre
    ");
    $stmt_mesas->execute([':id_sala' => $id_sala]);
    $mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);

    // Obtener todas las salas para la barra lateral
    try {
        $stmt_all_salas = $conn->query("SELECT id, nombre FROM salas ORDER BY nombre");
        $todas_las_salas = $stmt_all_salas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $todas_las_salas = [];
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Función para obtener clase CSS según estado
function getEstadoClase($estado, $tiene_reserva = false) {
    if ($tiene_reserva) return 'reservada';
    switch ($estado) {
        case 1: return 'libre';
        case 2: return 'ocupada';
        case 3: return 'reservada';
        default: return 'libre';
    }
}

function getEstadoTexto($estado) {
    switch ($estado) {
        case 1: return 'Libre';
        case 2: return 'Ocupada';
        case 3: return 'Reservada';
        default: return 'Desconocido';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($sala['nombre']) ?> - Casa GMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/salas_general.css">
    <link rel="icon" type="image/png" href="../../img/icono.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body>
    <nav class="main-header">
        <div class="header-logo">
            <a href="index.php">
                <img src="../../img/basic_logo_blanco.png" alt="Logo GMS">
            </a>
            <div class="logo-text">
                <span class="gms-title">CASA GMS</span>
            </div>
        </div>

        <div class="header-greeting">
            Hola <span class="username-tag"><?= $username ?></span>
        </div>

        <div class="header-menu">
            <a href="index.php" class="nav-link">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="reservas.php" class="nav-link">
                <i class="fa-solid fa-calendar-check"></i> Reservas
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

    <!-- Contenedor Principal con Layout Flex -->
    <div class="sala-container">
        
        <!-- ZONA IZQUIERDA: VISUALIZACIÓN DE SALA -->
        <div class="sala-layout" style="background-image: url('../../img/salas/<?= htmlspecialchars($sala['imagen'] ?? 'default_room.png') ?>');">
            
            <!-- Botón Ver Reservas -->
            <a href="reservas.php?sala_id=<?= $sala['id'] ?>" class="btn-ver-reservas">
                <i class="fa-solid fa-calendar-days"></i> Ver Reservas
            </a>
            <?php if (empty($mesas)): ?>
                <div class="empty-message">
                    <i class="fa-solid fa-info-circle"></i> 
                    Esta sala no tiene mesas asignadas.
                </div>
            <?php else: ?>
                <div class="mesas-grid-overlay">
                    <?php foreach ($mesas as $mesa): ?>
                        <div class="mesa-wrapper">
                            <?php 
                                $camarero_reserva = $mesa['camarero_reserva_activa'] ?? null;
                                $hora_activa = $mesa['hora_reserva_activa'] ?? null;
                                $proxima_hora = $mesa['proxima_reserva_hora'] ?? null;
                                $clase_visual = getEstadoClase($mesa['estado'], !empty($camarero_reserva));
                                
                                // Determinar acción (SIEMPRE DISPONIBLE)
                                $action = '';
                                $titulo_accion = '';

                                if ($mesa['estado'] == 2) {
                                    // Si está ocupada físicamente -> Liberar
                                    $action = 'liberar_mesa.php';
                                    $titulo_accion = 'Liberar Mesa';
                                } else {
                                    // Si está libre o reservada -> Asignar
                                    $action = 'asignar_mesa.php';
                                    $titulo_accion = 'Asignar Mesa';
                                    
                                    if ($camarero_reserva) {
                                        $titulo_accion .= ' (Reservada: ' . $hora_activa . ' por ' . htmlspecialchars($camarero_reserva) . ')';
                                    } elseif ($proxima_hora) {
                                        $titulo_accion .= " (Reserva a las $proxima_hora)";
                                    }
                                }
                            ?>
                            
                            <?php if ($action): ?>
                                <a href="<?= $action ?>?id_mesa=<?= $mesa['id'] ?>" class="mesa-card-btn" title="<?= $titulo_accion ?>" style="text-decoration: none; display: block;">
                                    <div class="mesa-card <?= $clase_visual ?>">
                                        <img src="../../img/mesa2.png" class="mesa-img-real" alt="Mesa">
                                        
                                        <div class="mesa-label-top">
                                            <span class="mesa-name"><?= htmlspecialchars($mesa['nombre']) ?></span>
                                            <span class="mesa-seats"><i class="fa-solid fa-users"></i> <?= $mesa['sillas'] ?></span>
                                        </div>

                                        <?php if ($mesa['estado'] == 2 && $mesa['camarero_nombre']): ?>
                                            <div class="mesa-waiter-tag">
                                                <i class="fa-solid fa-user"></i> <?= htmlspecialchars($mesa['camarero_nombre']) ?>
                                            </div>
                                        <?php elseif ($camarero_reserva): ?>
                                            <!-- Reserva Activa AHORA -->
                                            <div class="mesa-waiter-tag" style="background: #f39c12;">
                                                <i class="fa-regular fa-clock"></i> <?= $hora_activa ?> • <?= htmlspecialchars($camarero_reserva) ?>
                                            </div>
                                        <?php elseif ($proxima_hora): ?>
                                            <!-- Aviso de Próxima Reserva -->
                                            <div class="mesa-waiter-tag" style="background: #3498db;">
                                                <i class="fa-regular fa-clock"></i> Res: <?= $proxima_hora ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php else: ?>
                                <!-- Solo para estado 3 manual si existiera -->
                                <div class="mesa-card <?= $clase_visual ?>">
                                    <img src="../../img/mesa2.png" class="mesa-img-real" alt="Mesa">
                                    <div class="mesa-label-top">
                                        <span class="mesa-name"><?= htmlspecialchars($mesa['nombre']) ?></span>
                                        <span class="mesa-seats"><i class="fa-solid fa-users"></i> <?= $mesa['sillas'] ?></span>
                                    </div>
                                    <div class="mesa-waiter-tag" style="background: #f39c12;">Reservada</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ZONA DERECHA: NAVEGACIÓN LATERAL -->
        <div class="salas-navigation">
            <?php foreach ($todas_las_salas as $s): ?>
                <a href="sala.php?id=<?= $s['id'] ?>" 
                   class="sala-nav-link <?= ($s['id'] == $id_sala) ? 'active' : '' ?>">
                    <?= htmlspecialchars($s['nombre']) ?>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../JS/salas.js"></script>
</body>
</html>
