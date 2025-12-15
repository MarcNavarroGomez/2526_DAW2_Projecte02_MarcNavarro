<?php
// Inicia o reanuda la sesión actual para acceder a las variables de usuario logueado
session_start();

// --- Conexión a la BBDD ---
// Incluye el archivo de conexión. __DIR__ obtiene el directorio actual del script.
// Se usa require_once para asegurar que el archivo de conexión se incluya solo una vez.
require_once __DIR__ . '/../CONEXION/conexion.php';

// Comprobación de Seguridad: Verifica si el usuario ha iniciado sesión correctamente.
// Se comprueba si existe 'loginok' y si es estrictamente true, y si 'username' está definido.
if (isset($_SESSION['loginok']) && $_SESSION['loginok'] === true && isset($_SESSION['username'])) {
    // Si está autenticado, recupera el nombre completo sanitizado de la sesión
    $nombre = htmlspecialchars($_SESSION['nombre']);
    // Recupera el nombre de usuario sanitizado de la sesión
    $username = htmlspecialchars($_SESSION['username']);
    // Asigna el rol del usuario desde la sesión. Si no est á definido, usa 1 (Camarero) por defecto.
    $rol = $_SESSION['rol'] ?? 1; // 1=camarero, 2=admin
} else {
    // Si la sesión no es válida, redirige al usuario a la página de login
    header("Location: login.php");
    // Detiene la ejecución para evitar mostrar contenido protegido
    exit(); 
}

// --- Lógica para el mensaje de bienvenida (SweetAlert) ---
// Inicializa una bandera en "false" (string) para pasar a JavaScript posteriormente
$welcome_data_flag = "false"; 
// Inicializa una variable vacía para el nombre a mostrar en el mensaje
$welcome_data_name = ""; 

// Verifica si existe la marca de "mostrar bienvenida" en la sesión (que se pone al loguearse)
if (isset($_SESSION['show_welcome_message']) && $_SESSION['show_welcome_message'] === true) {
    // Si es true, activa la bandera para JavaScript
    $welcome_data_flag = "true"; 
    // Asigna el nombre del usuario para mostrarlo en el mensaje
    $welcome_data_name = $nombre; 
    
    // Elimina la variable de sesión para que el mensaje no aparezca en recargas futuras de la página
    unset($_SESSION['show_welcome_message']); 
}
// --- Fin lógica bienvenida ---


// ----------------------------------------------------------------------------------
// --- CONSULTAS A LA BASE DE DATOS PARA ESTADÍSTICAS ---
// ----------------------------------------------------------------------------------

// Inicia un bloque try para manejar posibles errores con la base de datos
try { 
    // Define la consulta SQL para obtener la información de ocupación de las salas
    $sql = "
        SELECT 
            s.id AS id_sala,            /* Selecciona el ID de la sala */
            s.nombre AS sala_nombre,    /* Selecciona el nombre de la sala */
            COUNT(m.id) AS total_mesas, /* Cuenta el número total de mesas en esa sala */
            /* Calcula cuántas mesas están ocupadas (estado = 2) sumando 1 si cumple la condición, sino 0 */
            SUM(CASE WHEN m.estado = 2 THEN 1 ELSE 0 END) AS mesas_ocupadas
        FROM salas s
        /* Une la tabla salas con mesas usando LEFT JOIN para incluir salas que no tengan mesas */
        LEFT JOIN mesas m ON s.id = m.id_sala
        GROUP BY s.id             /* Agrupa los resultados por ID de sala */
        ORDER BY s.nombre ASC     /* Ordena las salas alfabéticamente por su nombre */
    ";
    
    // Ejecuta la consulta directa a la base de datos
    $stmt = $conn->query($sql); 
    // Recupera todos los resultados y los almacena en el array $salas
    $salas = $stmt->fetchAll(PDO::FETCH_ASSOC); 

    // Inicializa un array vacío para guardar los datos procesados de ocupación por sala
    $ocupacion_salas = []; 
    // Inicializa contadores globales a 0
    $total_mesas = 0;       // Total de mesas en todo el restaurante
    $mesas_ocupadas = 0;    // Total de mesas ocupadas
    $total_sillas = 0;      // Total de sillas en todo el restaurante
    $sillas_ocupadas = 0;   // Total de sillas ocupadas

    // Itera a través de cada sala obtenida de la base de datos para procesar sus datos
    foreach ($salas as $s) {
        // Acumula el total de mesas y mesas ocupadas a los contadores globales
        $total_mesas += $s['total_mesas'];
        $mesas_ocupadas += $s['mesas_ocupadas'];
        
        // Calcula el porcentaje de ocupación de la sala. Si total_mesas > 0, calcula; sino 0 para evitar error.
        $ocupacion_pct = $s['total_mesas'] > 0 ? round(($s['mesas_ocupadas'] / $s['total_mesas']) * 100) : 0;

        // --- Subconsulta para obtener datos de SILLAS de esta sala específica ---
        // Prepara una consulta para sumar sillas totales y ocupadas en la sala actual (:id)
        $querySillas = $conn->prepare("
            SELECT 
                SUM(sillas) AS total_sillas, /* Suma la columna 'sillas' de todas las mesas */
                /* Suma las sillas solo si la mesa está ocupada (estado = 2) */
                SUM(CASE WHEN estado = 2 THEN sillas ELSE 0 END) AS sillas_ocupadas
            FROM mesas WHERE id_sala = :id
        ");
        // Ejecuta la consulta pasando el ID de la sala actual como parámetro
        $querySillas->execute([':id' => $s['id_sala']]); 
        // Obtiene el resultado (una sola fila con los sumatorios)
        $sillas = $querySillas->fetch(PDO::FETCH_ASSOC); 

        // Suma los datos de sillas de esta sala a los contadores globales
        $total_sillas += intval($sillas['total_sillas']);
        $sillas_ocupadas += intval($sillas['sillas_ocupadas']);

        // Añade un nuevo array con los datos formateados de esta sala al array principal $ocupacion_salas
        $ocupacion_salas[] = [
            'sala' => $s['sala_nombre'], // Nombre de la sala
            'id_sala' => $s['id_sala'],  // ID de la sala (para enlaces)
            'ocupacion_pct' => $ocupacion_pct, // Porcentaje de ocupación calculado
            'mesas_ocupadas' => $s['mesas_ocupadas'], // Cantidad numerica de mesas ocupadas
            'total_mesas' => $s['total_mesas'] // Cantidad total de mesas
        ];
    }

    // Crea un array con las estadísticas globales consolidadas para mostrar en el dashboard
    $stats = [
        'total_mesas' => $total_mesas,
        'mesas_ocupadas' => $mesas_ocupadas,
        'mesas_libres' => $total_mesas - $mesas_ocupadas, // Calcula mesas libres restando ocupadas del total
        'total_sillas' => $total_sillas,
        'sillas_ocupadas' => $sillas_ocupadas,
        'sillas_libres' => $total_sillas - $sillas_ocupadas, // Calcula sillas libres
    ];

    // Ordena el array de salas para mostrar primero las que tienen mayor porcentaje de ocupación
    // usort usa una función flecha (fn) que compara el 'ocupacion_pct' de forma descendente (<=>)
    usort($ocupacion_salas, fn($a, $b) => $b['ocupacion_pct'] <=> $a['ocupacion_pct']);
    
} catch (PDOException $e) { 
    // Si ocurre algún error en las consultas, se detiene el script y se muestra el mensaje de la excepción
    die("Error al obtener los datos: " . $e->getMessage());
}

// Lógica para determinar el saludo según la hora del servidor
$hora = date('H'); // Obtiene la hora actual en formato 00-23
if ($hora >= 6 && $hora < 12) {
    // De 6:00 a 11:59
    $saludo = "Buenos días";
} elseif ($hora >= 12 && $hora < 20) {
    // De 12:00 a 19:59
    $saludo = "Buenas tardes";
} else {
    // De 20:00 a 5:59
    $saludo = "Buenas noches";
}
?>

<!DOCTYPE html>
<html lang="es"> 
<head>
    <!-- Configuración de caracteres UTF-8 -->
    <meta charset="UTF-8"> 
    <!-- Viewport para diseño responsive en móviles -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <!-- Título de la página -->
    <title>Panel Principal - Casa GMS</title>
    
    <!-- Carga fuente Poppins de Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Carga librería de iconos Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Carga estilos CSS personalizados para el panel -->
    <link rel="stylesheet" href="../../css/panel_principal.css">
    <!-- Icono de pestaña (favicon) -->
    <link rel="icon" type="image/png" href="../../img/icono.png"> 
    <!-- Carga estilos para alertas SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

</head>

<!-- El body tiene atributos data-* para pasar información de PHP a JavaScript (mensaje bienvenida, nombre de usuario) -->
<body 
    data-show-welcome="<?php echo $welcome_data_flag; ?>" 
    data-welcome-name="<?php echo htmlspecialchars($welcome_data_name); ?>"
    data-user-name="<?php echo htmlspecialchars($nombre); ?>"
>
    
    <!-- Barra de navegación principal -->
    <nav class="main-header">
        <!-- Logo y título a la izquierda -->
        <div class="header-logo">
            <img src="../../img/basic_logo_blanco.png" alt="Logo GMS">
            <div class="logo-text">
                <span class="gms-title">CASA GMS</span>
            </div>
        </div>

        <!-- Saludo central con el nombre del usuario -->
        <div class="header-greeting">
            <?= $saludo ?> <span class="username-tag"><?= $username ?></span>
        </div>

        <!-- Menú de navegación derecha -->
        <div class="header-menu">
            <!-- Enlace a Inicio -->
            <a href="./index.php" class="nav-link">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <!-- Enlace a Histórico -->
            <a href="./historico.php" class="nav-link">
                <i class="fa-solid fa-chart-bar"></i> Histórico
            </a>
            <!-- Enlace a Admin (solo visible si el rol es 2) -->
            <?php if ($rol == 2): ?>
                <a href="./ADMIN/admin_panel.php" class="nav-link">
                    <i class="fa-solid fa-gear"></i> Admin
                </a>
            <?php endif; ?>
        </div>

        <!-- Botón de Cerrar Sesión (Formulario para usar POST) -->
        <form method="post" action="../PROCEDIMIENTOS/logout.php">
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </button>
        </form>
    </nav>

    <!-- Contenedor principal del contenido -->
    <div class="container">
        
        <!-- Título de la sección -->
        <h1 class="dashboard-title">Resumen de Ocupación Hoy</h1>

        <!-- Cuadrícula para las tarjetas de estadísticas -->
        <div class="stats-grid">
            
            <!-- Tarjeta 1: Mesas Disponibles -->
            <div class="stat-card primary">
                <div class="stat-value"><?= $stats['mesas_libres'] ?> / <?= $stats['total_mesas'] ?></div>
                <div class="stat-label">Mesas Disponibles</div>
                <i class="stat-icon fa-solid fa-check-circle"></i>
            </div>
            
            <!-- Tarjeta 2: Mesas Ocupadas -->
            <div class="stat-card warning">
                <div class="stat-value"><?= $stats['mesas_ocupadas'] ?></div>
                <div class="stat-label">Mesas Ocupadas</div>
                <i class="stat-icon fa-solid fa-users"></i>
            </div>

            <!-- Tarjeta 3: Sillas Ocupadas -->
            <div class="stat-card success">
                <div class="stat-value"><?= $stats['sillas_ocupadas'] ?> / <?= $stats['total_sillas'] ?></div>
                <div class="stat-label">Sillas Ocupadas (Total)</div>
                <i class="stat-icon fa-solid fa-user-group"></i>
            </div>
        </div>
        
        <!-- Título para la sección de salas -->
        <h2 class="section-title">Salas del Restaurante (Click para ver mesas)</h2>
        
        <!-- Cuadrícula para mostrar las tarjetas de cada sala -->
        <div class="salas-grid">
            <!-- Itera sobre las salas procesadas en PHP -->
            <?php foreach ($ocupacion_salas as $sala): ?>
                <?php
                    // --- Lógica de presentación para colores según ocupación ---
                    $color_class = 'bg-neutral-100'; // Color de fondo por defecto (gris claro)
                    if ($sala['ocupacion_pct'] >= 75) {
                        $color_class = 'bg-red-100'; // Rojo si está muy llena (>=75%)
                    } elseif ($sala['ocupacion_pct'] > 0) {
                        $color_class = 'bg-yellow-100'; // Amarillo si tiene algo de gente
                    }
                    
                    $bar_color = '#27ae60'; // Color de la barra verde por defecto
                    if ($sala['ocupacion_pct'] >= 75) {
                        $bar_color = '#e74c3c'; // Rojo si >=75%
                    } elseif ($sala['ocupacion_pct'] > 0) {
                        $bar_color = '#f39c12'; // Naranja intermedio
                    }
                ?>
                <!-- Enlace que envuelve toda la tarjeta y lleva al detalle de la sala -->
                <a href="./sala.php?id=<?= $sala['id_sala'] ?>" class="sala-card-link">
                    <!-- Tarjeta de sala con clase de color dinámica -->
                    <div class="sala-card <?= $color_class ?>">
                        <!-- Nombre de la sala -->
                        <h3 class="sala-name"><?= htmlspecialchars($sala['sala']) ?></h3>
                        
                        <!-- Texto descriptivo de ocupación -->
                        <div class="sala-occupancy">
                            <?php if ($sala['mesas_ocupadas'] == 0): ?>
                                <!-- Mensaje si está vacía -->
                                TODAS LIBRES (<?= $sala['total_mesas'] ?> Mesas)
                            <?php else: ?>
                                <!-- Mensaje con conteo X / Y si hay ocupación -->
                                <?= $sala['mesas_ocupadas'] ?> / <?= $sala['total_mesas'] ?> Mesas Ocupadas
                            <?php endif; ?>
                        </div>
                        
                        <!-- Barra de progreso visual -->
                        <div class="progress-bar-container">
                            <div 
                                class="progress-bar" 
                                style="width: <?= $sala['ocupacion_pct'] ?>%; 
                                       background-color: <?= $bar_color ?>;">
                            </div>
                        </div>
                        <!-- Porcentaje numérico -->
                        <div class="percentage"><?= $sala['ocupacion_pct'] ?>% Ocupación</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Carga librería SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Script para mostrar el mensaje de bienvenida si corresponde -->
    <script src="../../JS/mensaje_inicio.js"></script>
    
    <!-- Script para cerrar sesión por inactividad -->
    <script src="../../JS/inactivity_timer.js"></script>

</body>
</html>