<?php
// Inicia o reanuda la sesión existente
session_start();

// --- Conexión a la BBDD ---
// Requiere el archivo de conexión, usando una ruta absoluta basada en la ubicación actual (__DIR__)
require_once __DIR__ . '/../CONEXION/conexion.php';

// Comprobar si el usuario está logueado correctamente
// Verifica si existen las variables de sesión 'loginok' (true) y 'username'
if (isset($_SESSION['loginok']) && $_SESSION['loginok'] === true && isset($_SESSION['username'])) {
    // Si está logueado, guarda las variables de sesión en variables locales
    // htmlspecialchars() previene ataques XSS al imprimir estas variables
    $nombre = htmlspecialchars($_SESSION['nombre']);
    $username = htmlspecialchars($_SESSION['username']);
    // Asigna el rol. Si no está definido, asume 1 (camarero) por defecto.
    $rol = $_SESSION['rol'] ?? 1; // 1=camarero, 2=admin
} else {
    // Si no está logueado, redirige a la página de login
    header("Location: login.php");
    exit(); // Detiene la ejecución del script
}

// --- Lógica para el mensaje de bienvenida (Toast) ---
// Inicializa variables para el mensaje emergente (SweetAlert)
$welcome_data_flag = "false"; // Flag para que JS sepa si mostrar el mensaje
$welcome_data_name = ""; // Nombre a mostrar en el mensaje

// Comprueba si la variable de sesión 'show_welcome_message' existe y es true
if (isset($_SESSION['show_welcome_message']) && $_SESSION['show_welcome_message'] === true) {
    $welcome_data_flag = "true"; // Activa el flag para JS
    $welcome_data_name = $nombre; // Asigna el nombre del usuario
    
    // Borra la variable de sesión para que no se muestre de nuevo al recargar
    unset($_SESSION['show_welcome_message']); 
}
// --- FIN DEL AÑADIDO ---


// ----------------------------------------------------------------------------------
// --- CONSULTAS A LA BASE DE DATOS ---
// ----------------------------------------------------------------------------------

try { // Inicia un bloque try-catch para manejar errores de BBDD (PDO)
    // --- Consulta principal para obtener datos de las SALAS ---
    $sql = "
        SELECT 
            s.id AS id_sala,
            s.nombre AS sala_nombre,
            COUNT(m.id) AS total_mesas,
            /* Suma 1 por cada mesa (m) cuyo estado sea 2 (ocupada) */
            SUM(CASE WHEN m.estado = 2 THEN 1 ELSE 0 END) AS mesas_ocupadas
        FROM salas s
        /* Une con mesas (LEFT JOIN) para incluir salas aunque no tengan mesas */
        LEFT JOIN mesas m ON s.id = m.id_sala
        GROUP BY s.id /* Agrupa resultados por sala */
        ORDER BY s.nombre ASC /* Ordena alfabéticamente por nombre de sala */
    ";
    $stmt = $conn->query($sql); // Ejecuta la consulta
    $salas = $stmt->fetchAll(PDO::FETCH_ASSOC); // Obtiene todos los resultados como array asociativo

    // Inicializa variables para las estadísticas globales
    $ocupacion_salas = []; // Array para guardar los datos procesados de cada sala
    $total_mesas = 0;
    $mesas_ocupadas = 0;
    $total_sillas = 0;
    $sillas_ocupadas = 0;

    // Itera sobre cada sala obtenida en la consulta anterior
    foreach ($salas as $s) {
        // Acumula los totales globales
        $total_mesas += $s['total_mesas'];
        $mesas_ocupadas += $s['mesas_ocupadas'];
        // Calcula el porcentaje de ocupación (evita división por cero)
        $ocupacion_pct = $s['total_mesas'] > 0 ? round(($s['mesas_ocupadas'] / $s['total_mesas']) * 100) : 0;

        // --- Consulta secundaria para obtener datos de SILLAS por sala ---
        $querySillas = $conn->prepare("
            SELECT 
                SUM(sillas) AS total_sillas, /* Suma todas las sillas de la sala */
                /* Suma las sillas solo de las mesas con estado 2 (ocupada) */
                SUM(CASE WHEN estado = 2 THEN sillas ELSE 0 END) AS sillas_ocupadas
            FROM mesas WHERE id_sala = :id /* Filtra por el ID de la sala actual */
        ");
        $querySillas->execute([':id' => $s['id_sala']]); // Ejecuta la consulta preparada
        $sillas = $querySillas->fetch(PDO::FETCH_ASSOC); // Obtiene el resultado

        // Acumula los totales globales de sillas (intval asegura que sean números)
        $total_sillas += intval($sillas['total_sillas']);
        $sillas_ocupadas += intval($sillas['sillas_ocupadas']);

        // Añade los datos procesados de esta sala al array $ocupacion_salas
        $ocupacion_salas[] = [
            'sala' => $s['sala_nombre'],
            // Genera el nombre del archivo PHP (ej: "Comedor 1" -> "comedor1.php")
            'file' => strtolower(str_replace(' ', '', $s['sala_nombre'])) . '.php',
            'ocupacion_pct' => $ocupacion_pct,
            'mesas_ocupadas' => $s['mesas_ocupadas'],
            'total_mesas' => $s['total_mesas']
        ];
    }

    // Guarda las estadísticas globales en un array
    $stats = [
        'total_mesas' => $total_mesas,
        'mesas_ocupadas' => $mesas_ocupadas,
        'mesas_libres' => $total_mesas - $mesas_ocupadas, // Calcula mesas libres
        'total_sillas' => $total_sillas,
        'sillas_ocupadas' => $sillas_ocupadas,
        'sillas_libres' => $total_sillas - $sillas_ocupadas, // Calcula sillas libres
    ];

    // Ordena el array de salas por porcentaje de ocupación (de mayor a menor)
    usort($ocupacion_salas, fn($a, $b) => $b['ocupacion_pct'] <=> $a['ocupacion_pct']);
    
} catch (PDOException $e) { // Captura cualquier error de BBDD
    // Detiene el script y muestra un mensaje de error
    die("Error al obtener los datos: " . $e->getMessage());
}

// Saludo dinámico
$hora = date('H');
if ($hora >= 6 && $hora < 12) {
    $saludo = "Buenos días";
} elseif ($hora >= 12 && $hora < 20) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}
?>

<!DOCTYPE html>
<html lang="es"> <head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Panel Principal - Casa GMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/panel_principal.css">
    <link rel="icon" type="image/png" href="../../img/icono.png"> <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

</head>

<body 
    data-show-welcome="<?php echo $welcome_data_flag; ?>" 
    data-welcome-name="<?php echo htmlspecialchars($welcome_data_name); ?>"
    data-user-name="<?php echo htmlspecialchars($nombre); ?>"
>
    
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
            <a href="./index.php" class="nav-link">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="./historico.php" class="nav-link">
                <i class="fa-solid fa-chart-bar"></i> Histórico
            </a>
            <?php if ($rol == 2): ?>
                <a href="./ADMIN/admin_panel.php" class="nav-link">
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
    <div class="container">
        
        <h1 class="dashboard-title">Resumen de Ocupación Hoy</h1>

        <div class="stats-grid">
            
            <div class="stat-card primary">
                <div class="stat-value"><?= $stats['mesas_libres'] ?> / <?= $stats['total_mesas'] ?></div>
                <div class="stat-label">Mesas Disponibles</div>
                <i class="stat-icon fa-solid fa-check-circle"></i>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-value"><?= $stats['mesas_ocupadas'] ?></div>
                <div class="stat-label">Mesas Ocupadas</div>
                <i class="stat-icon fa-solid fa-users"></i>
            </div>

            <div class="stat-card success">
                <div class="stat-value"><?= $stats['sillas_ocupadas'] ?> / <?= $stats['total_sillas'] ?></div>
                <div class="stat-label">Sillas Ocupadas (Total)</div>
                <i class="stat-icon fa-solid fa-user-group"></i>
            </div>
        </div>
        
        <h2 class="section-title">Salas del Restaurante (Click para ver mesas)</h2>
        <div class="salas-grid">
            <?php foreach ($ocupacion_salas as $sala): ?>
                <?php
                    // --- Lógica para asignar colores a la tarjeta y barra de progreso ---
                    $color_class = 'bg-neutral-100'; // Color por defecto (libre)
                    if ($sala['ocupacion_pct'] >= 75) {
                        $color_class = 'bg-red-100'; // Ocupación alta
                    } elseif ($sala['ocupacion_pct'] > 0) {
                        $color_class = 'bg-yellow-100'; // Ocupación media
                    }
                    
                    $bar_color = '#27ae60'; // Color barra (libre)
                    if ($sala['ocupacion_pct'] >= 75) {
                        $bar_color = '#e74c3c'; // Color barra (alta)
                    } elseif ($sala['ocupacion_pct'] > 0) {
                        $bar_color = '#f39c12'; // Color barra (media)
                    }
                ?>
                <a href="./SALAS/<?= htmlspecialchars($sala['file']) ?>" class="sala-card-link">
                    <div class="sala-card <?= $color_class ?>">
                        <h3 class="sala-name"><?= htmlspecialchars($sala['sala']) ?></h3>
                        <div class="sala-occupancy">
                            <?php if ($sala['mesas_ocupadas'] == 0): ?>
                                TODAS LIBRES (<?= $sala['total_mesas'] ?> Mesas)
                            <?php else: ?>
                                <?= $sala['mesas_ocupadas'] ?> / <?= $sala['total_mesas'] ?> Mesas Ocupadas
                            <?php endif; ?>
                        </div>
                        
                        <div class="progress-bar-container">
                            <div 
                                class="progress-bar" 
                                style="width: <?= $sala['ocupacion_pct'] ?>%; 
                                       background-color: <?= $bar_color ?>;">
                            </div>
                        </div>
                        <div class="percentage"><?= $sala['ocupacion_pct'] ?>% Ocupación</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="../../JS/mensaje_inicio.js"></script>
    
    <script src="../../JS/inactivity_timer.js"></script>

</body>
</html>