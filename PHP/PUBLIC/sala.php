<?php
// Inicia o reanuda la sesión del usuario
session_start();

// Incluye el archivo de conexión a la base de datos (ruta absoluta)
require_once __DIR__ . '/../CONEXION/conexion.php';

// Verificar si el usuario ha iniciado sesión. Si no, redirigir a login.
if (!isset($_SESSION['loginok'])) {
    header("Location: login.php");
    exit(); // Finaliza la ejecución
}

// Obtener el ID de la sala desde la URL (parámetro GET 'id')
// Si no existe, asigna 0 como valor por defecto.
$id_sala = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validación básica: Si el ID no es válido (<= 0), redirigir al índice con error.
if ($id_sala <= 0) {
    header("Location: index.php?error=sala_invalida");
    exit();
}

// Recupera datos del usuario de la sesión para mostrar en la interfaz
$username = htmlspecialchars($_SESSION['username']);
$rol = $_SESSION['rol'] ?? 1; // 1 = Camarero, 2 = Admin (valor por defecto 1)

// Bloque try-catch para consultas a la base de datos
try {
    // ----------------------------------------------------------------------
    // 1. Obtener información de la sala actual (nombre, imagen)
    // ----------------------------------------------------------------------
    $stmt_sala = $conn->prepare("SELECT id, nombre, imagen FROM salas WHERE id = :id");
    // Ejecuta la consulta vinculando el parámetro :id para seguridad
    $stmt_sala->execute([':id' => $id_sala]);
    // Obtiene los datos de la sala
    $sala = $stmt_sala->fetch(PDO::FETCH_ASSOC);
    
    // Si la sala no existe en la base de datos, redirige al usuario
    if (!$sala) {
        header("Location: index.php?error=sala_no_encontrada");
        exit();
    }
    
    // ----------------------------------------------------------------------
    // 2. Obtener mesas de esta sala + Verificar estado de reservas
    // ----------------------------------------------------------------------
    // Esta consulta es compleja porque verifica varias cosas al mismo tiempo para cada mesa:
    // - Datos básicos (id, nombre, sillas, estado)
    // - Quién la ocupó (camarero_nombre) si el estado es 'ocupada'
    // - Si hay una RESERVA ACTIVA en este momento (camarero_reserva_activa)
    // - Cuándo empieza la próxima reserva de hoy (proxima_reserva_hora)
    $stmt_mesas = $conn->prepare("
        SELECT 
            m.id,
            m.nombre,
            m.sillas,
            m.estado,
            m.asignado_por,
            u.username as camarero_nombre, /* Nombre del camarero que la ocupó físicamente */
            
            -- Subconsulta: Verifica si hay una reserva ACTIVA AHORA MISMO
            -- 'Activa' significa: inicio <= AHORA y (fin es NULL o fin > AHORA)
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
            
            -- Subconsulta: Obtiene la HORA de inicio de esa reserva activa
            (
                SELECT DATE_FORMAT(o.inicio_ocupacion, '%H:%i')
                FROM ocupaciones o 
                WHERE o.id_mesa = m.id 
                AND o.inicio_ocupacion <= NOW() 
                AND (o.final_ocupacion IS NULL OR o.final_ocupacion > NOW())
                AND o.id_reserva IS NOT NULL
                LIMIT 1
            ) as hora_reserva_activa,
            
            -- Subconsulta: Busca la PRÓXIMA reserva para hoy que aún no ha empezado
            (
                SELECT DATE_FORMAT(o.inicio_ocupacion, '%H:%i')
                FROM ocupaciones o
                WHERE o.id_mesa = m.id
                AND o.inicio_ocupacion > NOW()        /* Reservas futuras */
                AND DATE(o.inicio_ocupacion) = CURDATE() /* Solo hoy */
                AND o.id_reserva IS NOT NULL
                ORDER BY o.inicio_ocupacion ASC       /* La más próxima */
                LIMIT 1
            ) as proxima_reserva_hora
            
        FROM mesas m
        LEFT JOIN users u ON m.asignado_por = u.id /* Join para saber quién ocupó la mesa físicamente */
        WHERE m.id_sala = :id_sala
        ORDER BY m.nombre /* Ordenar mesas alfabéticamente */
    ");
    // Ejecuta la consulta pasando el ID de la sala
    $stmt_mesas->execute([':id_sala' => $id_sala]);
    // Obtiene todas las mesas
    $mesas = $stmt_mesas->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------------------------------------------------
    // 3. Obtener listado de TODAS las salas para la barra de navegación lateral
    // ----------------------------------------------------------------------
    try {
        $stmt_all_salas = $conn->query("SELECT id, nombre FROM salas ORDER BY nombre");
        $todas_las_salas = $stmt_all_salas->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $todas_las_salas = []; // Si falla, array vacío para no romper la web
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage()); // Detiene si hay error crítico
}

// ----------------------------------------------------------------------
// Funciones Helper para la Vista (Presentación)
// ----------------------------------------------------------------------

// Devuelve la clase CSS (color) según el estado de la mesa
function getEstadoClase($estado, $tiene_reserva = false) {
    // Si tiene reserva activa, se muestra con estilo de reserva
    if ($tiene_reserva) return 'reservada';
    
    switch ($estado) {
        case 1: return 'libre';    // Verde
        case 2: return 'ocupada';  // Rojo
        case 3: return 'reservada';// Amarillo/Naranja
        default: return 'libre';
    }
}

// Devuelve el texto legible del estado
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
    <!-- Título dinámico con el nombre de la sala -->
    <title><?= htmlspecialchars($sala['nombre']) ?> - Casa GMS</title>
    
    <!-- Fuentes y estilos -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- CSS general y específico -->
    <link rel="stylesheet" href="../../css/admin.css"> <!-- Reutilizando estilos base -->
    <link rel="stylesheet" href="../../css/salas_general.css"> <!-- Estilos específicos de salas -->
    <link rel="icon" type="image/png" href="../../img/icono.png">
    
    <!-- Alertas SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body>
    <!-- Header de navegación -->
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
            <!-- Enlace directo a la gestión de reservas -->
            <a href="reservas.php" class="nav-link">
                <i class="fa-solid fa-calendar-check"></i> Reservas
            </a>
            <!-- Panel Admin solo para rol 2 -->
            <?php if ($rol == 2): ?>
                <a href="ADMIN/admin_panel.php" class="nav-link">
                    <i class="fa-solid fa-gear"></i> Admin
                </a>
            <?php endif; ?>
        </div>

        <!-- Botón Logout -->
        <form method="post" action="../PROCEDIMIENTOS/logout.php">
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </button>
        </form>
    </nav>

    <!-- Contenedor Principal con Layout Flex (Sala visual izquierda, Navegación derecha) -->
    <div class="sala-container">
        
        <!-- ZONA IZQUIERDA: VISUALIZACIÓN DE SALA Y MESAS -->
        <!-- Fondo dinámico con la imagen de la sala -->
        <div class="sala-layout" style="background-image: url('../../img/salas/<?= htmlspecialchars($sala['imagen'] ?? 'default_room.png') ?>');">
            
            <!-- Botón flotante para ver historial de reservas de ESTA sala específica -->
            <a href="reservas.php?sala_id=<?= $sala['id'] ?>" class="btn-ver-reservas">
                <i class="fa-solid fa-calendar-days"></i> Ver Reservas
            </a>

            <!-- Si la sala no tiene mesas creadas -->
            <?php if (empty($mesas)): ?>
                <div class="empty-message">
                    <i class="fa-solid fa-info-circle"></i> 
                    Esta sala no tiene mesas asignadas.
                </div>
            <?php else: ?>
                <!-- Grid Overlay para posicionar las mesas -->
                <div class="mesas-grid-overlay">
                    <!-- Iterar sobre cada mesa obtenida -->
                    <?php foreach ($mesas as $mesa): ?>
                        <div class="mesa-wrapper">
                            <?php 
                                // Extraer variables para facilitar lectura
                                $camarero_reserva = $mesa['camarero_reserva_activa'] ?? null;
                                $hora_activa = $mesa['hora_reserva_activa'] ?? null;
                                $proxima_hora = $mesa['proxima_reserva_hora'] ?? null;
                                
                                // Determinar clase visual (color de la mesa)
                                $clase_visual = getEstadoClase($mesa['estado'], !empty($camarero_reserva));
                                
                                // Determinar acción al hacer click (SIEMPRE DISPONIBLE):
                                // El usuario siempre puede hacer click para Liberar o Asignar
                                $action = '';
                                $titulo_accion = '';

                                if ($mesa['estado'] == 2) {
                                    // Si está ocupada físicamente (estado 2) -> Click lleva a LIBERAR
                                    $action = 'liberar_mesa.php';
                                    $titulo_accion = 'Liberar Mesa';
                                } else {
                                    // Si está libre o reservada pero no ocupada -> Click lleva a ASIGNAR
                                    $action = 'asignar_mesa.php';
                                    $titulo_accion = 'Asignar Mesa';
                                    
                                    // Añadir información extra al tooltip (title)
                                    if ($camarero_reserva) {
                                        $titulo_accion .= ' (Reservada: ' . $hora_activa . ' por ' . htmlspecialchars($camarero_reserva) . ')';
                                    } elseif ($proxima_hora) {
                                        $titulo_accion .= " (Reserva a las $proxima_hora)";
                                    }
                                }
                            ?>
                            
                            <!-- Si hay acción definida (siempre debería haberla) -->
                            <?php if ($action): ?>
                                <!-- Enlace que cubre toda la mesa y lleva a la acción correspondiente pasando ID mesa -->
                                <a href="<?= $action ?>?id_mesa=<?= $mesa['id'] ?>" class="mesa-card-btn" title="<?= $titulo_accion ?>" style="text-decoration: none; display: block;">
                                    
                                    <!-- Tarjeta Visual de la Mesa -->
                                    <div class="mesa-card <?= $clase_visual ?>">
                                        <!-- Imagen de la mesa -->
                                        <img src="../../img/mesa2.png" class="mesa-img-real" alt="Mesa">
                                        
                                        <!-- Etiquetas superiores (Nombre y Sillas) -->
                                        <div class="mesa-label-top">
                                            <span class="mesa-name"><?= htmlspecialchars($mesa['nombre']) ?></span>
                                            <span class="mesa-seats"><i class="fa-solid fa-users"></i> <?= $mesa['sillas'] ?></span>
                                        </div>

                                        <!-- Etiquetas inferiores (Estado/Camarero) -->
                                        
                                        <!-- Caso 1: Ocupada físicamente (Estado 2) -->
                                        <?php if ($mesa['estado'] == 2 && $mesa['camarero_nombre']): ?>
                                            <div class="mesa-waiter-tag">
                                                <i class="fa-solid fa-user"></i> <?= htmlspecialchars($mesa['camarero_nombre']) ?>
                                            </div>
                                        
                                        <!-- Caso 2: Reserva Activa Ahora -->
                                        <?php elseif ($camarero_reserva): ?>
                                            <div class="mesa-waiter-tag" style="background: #f39c12;">
                                                <i class="fa-regular fa-clock"></i> <?= $hora_activa ?> • <?= htmlspecialchars($camarero_reserva) ?>
                                            </div>
                                        
                                        <!-- Caso 3: Próxima Reserva Hoy (aviso) -->
                                        <?php elseif ($proxima_hora): ?>
                                            <div class="mesa-waiter-tag" style="background: #3498db;">
                                                <i class="fa-regular fa-clock"></i> Res: <?= $proxima_hora ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php else: ?>
                                <!-- Fallback visual por si acaso (no debería ocurrir con lógica actual) -->
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

        <!-- ZONA DERECHA: NAVEGACIÓN LATERAL DE SALAS -->
        <div class="salas-navigation">
            <!-- Itera sobre todas las salas para crear enlaces rápidos -->
            <?php foreach ($todas_las_salas as $s): ?>
                <a href="sala.php?id=<?= $s['id'] ?>" 
                   class="sala-nav-link <?= ($s['id'] == $id_sala) ? 'active' : '' ?>">
                    <?= htmlspecialchars($s['nombre']) ?>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Scripts JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../JS/salas.js"></script>
</body>
</html>
