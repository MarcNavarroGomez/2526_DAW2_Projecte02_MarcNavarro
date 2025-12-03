<?php
// Inicia o reanuda la sesión del usuario. Es necesario para acceder a $_SESSION.
session_start();

// Requiere el archivo de conexión a la BBDD. 
// __DIR__ . '/../CONEXION/conexion.php' es una ruta absoluta que significa:
// "Desde el directorio de ESTE fichero (historico.php), sube un nivel y entra en /CONEXION/ y carga conexion.php".
require_once __DIR__ . '/../CONEXION/conexion.php'; 

// Establece la zona horaria por defecto a "Europa/Madrid".
// Esto es CRÍTICO para que funciones como CURDATE() y NOW() en SQL usen la hora correcta.
date_default_timezone_set('Europe/Madrid');

// --- CONTROL DE SESIÓN ---
// Comprueba si la variable de sesión 'loginok' NO existe O NO es estrictamente true.
if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
    // Si el usuario no está logueado, lo redirige a la página de login.
    header("Location: login.php"); 
    // Detiene la ejecución del script para que no se cargue nada más.
    exit(); 
}

// --- RECUPERACIÓN DE DATOS DE SESIÓN ---
// Guarda el username del usuario logueado.
$username = $_SESSION['username'];
// Guarda el rol. Usa el "operador de fusión de null" (??): si $_SESSION['rol'] existe, usa ese valor; si no, usa 1 (camarero) por defecto.
$rol = $_SESSION['rol'] ?? 1; 

// --- LÓGICA DEL HEADER (Saludo dinámico) ---
// Obtiene la hora actual en formato 24h (ej: "08", "14", "22").
$hora = date('H');
// Define un saludo personalizado según la franja horaria.
if ($hora >= 6 && $hora < 12) {
    $saludo = "Buenos días";
} elseif ($hora >= 12 && $hora < 20) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}
// --- FIN LÓGICA HEADER ---


// --- VARIABLES DE FILTRO (Para la tabla) ---
// Recoge los valores de la URL (método GET). 
// Si el parámetro (ej: ?sala=3) existe, usa su valor. Si no, usa un string vacío ''.
$filtro_sala = $_GET['sala'] ?? '';
$filtro_mesa = $_GET['mesa'] ?? ''; // (Nota: Este filtro no se usa en la consulta SQL de abajo)
$filtro_camarero = $_GET['camarero'] ?? ''; 
$filtro_mes = $_GET['mes'] ?? '';           
$filtro_dia = $_GET['dia'] ?? '';           
$filtro_ano = $_GET['ano'] ?? '';

// --- BLOQUE PRINCIPAL DE CONSULTAS A LA BBDD ---
// Se usa un bloque try-catch para capturar cualquier error de SQL (PDOException).
try {
    
    // --- 1. KPIs GENERALES (Tarjetas superiores) ---
    // Esta consulta calcula las 4 estadísticas principales en una sola llamada.
    $sql_general = "SELECT 
        COUNT(*) AS total_ocupaciones, /* Cuenta el total de registros de ocupaciones */
        SUM(num_comensales) AS total_comensales, /* Suma todos los comensales históricos */
        AVG(duracion_segundos) AS avg_duracion_segundos, /* Calcula la media de duración en segundos */
        
        /* Una subconsulta para contar solo las ocupaciones de HOY (CURDATE() = Fecha actual) */
        (SELECT COUNT(*) FROM ocupaciones WHERE DATE(inicio_ocupacion) = CURDATE()) AS ocupaciones_hoy
        FROM ocupaciones
        /* IMPORTANTE: Solo cuenta ocupaciones que ya han terminado (tienen fecha de fin) */
        WHERE final_ocupacion IS NOT NULL";
    
    // Ejecuta la consulta y obtiene la única fila de resultados.
    $stats_general = $conn->query($sql_general)->fetch(PDO::FETCH_ASSOC);

    // Convierte la duración promedio de segundos a minutos, redondeado a 1 decimal.
    // Comprueba si es > 0 para evitar errores si no hay datos.
    $avg_minutos = ($stats_general['avg_duracion_segundos'] > 0) ? round($stats_general['avg_duracion_segundos'] / 60, 1) : 0;
    
    // --- 2. Comparativa Mes Actual vs Anterior (para la tarjeta de "Tendencia") ---
    // Consulta para comparar el rendimiento del mes actual con el mes anterior.
    $sql_comparativa = "SELECT 
        /* Cuenta 1 por cada registro de ESTE mes y ESTE año */
        SUM(CASE WHEN YEAR(inicio_ocupacion) = YEAR(CURDATE()) AND MONTH(inicio_ocupacion) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS mes_actual,
        
        /* Cuenta 1 por cada registro del MES ANTERIOR (CURDATE() - INTERVAL 1 MONTH) */
        SUM(CASE WHEN YEAR(inicio_ocupacion) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(inicio_ocupacion) = MONTH(CURDATE() - INTERVAL 1 MONTH) THEN 1 ELSE 0 END) AS mes_anterior
        FROM ocupaciones";
    $comparativa = $conn->query($sql_comparativa)->fetch(PDO::FETCH_ASSOC);

    // Calcula el porcentaje de tendencia.
    $tendencia_porcentaje = 0;
    // IMPORTANTE: Comprueba si 'mes_anterior' > 0 para evitar un error de "División por cero".
    if ($comparativa['mes_anterior'] > 0) {
        $tendencia_porcentaje = round((($comparativa['mes_actual'] - $comparativa['mes_anterior']) / $comparativa['mes_anterior']) * 100, 1);
    }

    // --- 3. DATOS GRÁFICO: Top 5 Camareros ---
    // Obtiene los 5 camareros con más mesas asignadas.
    $sql_top_camareros = "SELECT u.username, COUNT(o.id) AS total_mesas
        FROM ocupaciones o
        /* Une con la tabla 'users' para obtener el nombre (username) a partir del 'id_camarero' */
        JOIN users u ON o.id_camarero = u.id 
        GROUP BY o.id_camarero /* Agrupa las cuentas por camarero */
        ORDER BY total_mesas DESC /* Ordena de mayor a menor */
        LIMIT 5"; // Coge solo los 5 primeros
    $top_camareros = $conn->query($sql_top_camareros)->fetchAll(PDO::FETCH_ASSOC);
    
    // Para el gráfico de barras HTML, necesitamos saber cuál es el valor MÁXIMO (el 100%).
    // array_column() saca solo los números (ej: [50, 45, 30, 20, 10])
    // max() obtiene el más alto (ej: 50).
    // Si el array está vacío, usa 1 para evitar división por cero.
    $max_camareros = !empty($top_camareros) ? max(array_column($top_camareros, 'total_mesas')) : 1;
    
    // --- 4. DATOS GRÁFICO: Top 5 Salas ---
    // Misma lógica que el Top Camareros, pero agrupando por sala.
    $sql_top_salas = "SELECT s.nombre, COUNT(o.id) AS total_ocupaciones
        FROM ocupaciones o
        JOIN salas s ON o.id_sala = s.id /* Une con 'salas' para obtener el nombre */
        GROUP BY o.id_sala
        ORDER BY total_ocupaciones DESC
        LIMIT 5";
    $top_salas = $conn->query($sql_top_salas)->fetchAll(PDO::FETCH_ASSOC);
    // Saca el valor máximo de ocupaciones de sala.
    $max_salas = !empty($top_salas) ? max(array_column($top_salas, 'total_ocupaciones')) : 1;

    // --- 5. DATOS GRÁFICO: Horas Pico ---
    // Cuenta cuántas ocupaciones se iniciaron en cada hora del día (0-23).
    $sql_horas_pico = "SELECT HOUR(inicio_ocupacion) AS hora, COUNT(*) AS ocupaciones
        FROM ocupaciones
        GROUP BY HOUR(inicio_ocupacion) /* Agrupa por la HORA */
        ORDER BY hora"; // Ordena por hora
    $horas_pico = $conn->query($sql_horas_pico)->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepara un array "plantilla" con 24 horas, todas a 0.
    // Ej: [0 => 0, 1 => 0, 2 => 0, ..., 23 => 0]
    $horas_data = array_fill(0, 24, 0); 
    // Rellena el array plantilla con los datos de la BBDD.
    // Si hubo 10 ocupaciones a las 14h, $horas_data[14] pasará a valer 10.
    foreach ($horas_pico as $hora) {
        $horas_data[$hora['hora']] = $hora['ocupaciones']; 
    }
    // Saca el valor máximo de la hora pico (el 100% de la barra).
    $max_horas = !empty($horas_data) ? max($horas_data) : 1;
    
    // --- 6. DATOS GRÁFICO: Días de la Semana ---
    // Misma lógica que las horas, pero con días.
    // WEEKDAY() en MySQL devuelve 0=Lunes, 1=Martes, ..., 6=Domingo.
    $sql_dias_semana = "SELECT WEEKDAY(inicio_ocupacion) AS dia_num, COUNT(*) AS ocupaciones
        FROM ocupaciones
        GROUP BY dia_num 
        ORDER BY dia_num";
    $dias_semana = $conn->query($sql_dias_semana)->fetchAll(PDO::FETCH_ASSOC);
    
    // Array para las etiquetas del gráfico.
    $dias_labels = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    // Array plantilla con 7 días, todos a 0.
    $dias_data = array_fill(0, 7, 0);
    // Rellena el array plantilla con los datos de la BBDD.
    foreach ($dias_semana as $dia) {
        $dias_data[$dia['dia_num']] = $dia['ocupaciones'];
    }
    // Saca el valor máximo del día más ocupado.
    $max_dias = !empty($dias_data) ? max($dias_data) : 1;


    // --- 7. DATOS PARA LA TABLA DE HISTÓRICO ---
    
    // Primero, obtenemos los listados para rellenar los <select> del formulario de filtros.
    $salas = $conn->query("SELECT id, nombre FROM salas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $mesas = $conn->query("SELECT id, nombre, id_sala FROM mesas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $camareros_filtro = $conn->query("SELECT id, username FROM users WHERE rol = 1 ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
    // Obtiene solo los años distintos que existen en la tabla (ej: [2025, 2024]).
    $anos = $conn->query("SELECT DISTINCT YEAR(inicio_ocupacion) AS ano FROM ocupaciones ORDER BY ano DESC")->fetchAll(PDO::FETCH_ASSOC);
    // Un array PHP para los nombres de los meses.
    $meses = [ 
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    // --- CONSTRUCCIÓN DE LA CONSULTA DINÁMICA PARA LA TABLA ---
    // Esta es la consulta base. Obtiene todos los datos y los nombres de las tablas relacionadas.
    $sql_tabla = "
        SELECT o.*, s.nombre AS sala_nombre, m.nombre AS mesa_nombre, u.username AS camarero
        FROM ocupaciones o
        JOIN salas s ON o.id_sala = s.id
        JOIN mesas m ON o.id_mesa = m.id
        JOIN users u ON o.id_camarero = u.id
        WHERE 1=1"; // "WHERE 1=1" es un truco: permite añadir siempre "AND" sin preocuparse de si es el primer filtro.
    
    // Array para los parámetros de la consulta preparada (evita Inyección SQL).
    $params_tabla = []; 

    // --- Añadir filtros dinámicamente ---
    // Comprueba si la variable de filtro (de la URL) NO está vacía.
    if ($filtro_sala !== '') {
        $sql_tabla .= " AND o.id_sala = :sala"; // Añade la condición SQL (con un marcador :sala).
        $params_tabla[':sala'] = $filtro_sala; // Añade el valor al array de parámetros.
    }
    // Repite la lógica para los demás filtros.
    if ($filtro_camarero !== '') {
        $sql_tabla .= " AND o.id_camarero = :camarero";
        $params_tabla[':camarero'] = $filtro_camarero;
    }
    if ($filtro_ano !== '') {
        $sql_tabla .= " AND YEAR(o.inicio_ocupacion) = :ano";
        $params_tabla[':ano'] = $filtro_ano;
    }
    if ($filtro_mes !== '') {
        $sql_tabla .= " AND MONTH(o.inicio_ocupacion) = :mes";
        $params_tabla[':mes'] = $filtro_mes;
    }
    if ($filtro_dia !== '') {
        $sql_tabla .= " AND DAY(o.inicio_ocupacion) = :dia";
        $params_tabla[':dia'] = $filtro_dia;
    }
    
    // Añade el orden y un límite (para no sobrecargar la página).
    $sql_tabla .= " ORDER BY o.inicio_ocupacion DESC LIMIT 200"; 
    
    // Prepara la consulta SQL (ya construida dinámicamente).
    $stmt_tabla = $conn->prepare($sql_tabla); 
    // Ejecuta la consulta, pasando el array de parámetros. PDO se encarga de la seguridad.
    $stmt_tabla->execute($params_tabla); 
    // Obtiene todos los resultados filtrados.
    $ocupaciones_tabla = $stmt_tabla->fetchAll(PDO::FETCH_ASSOC); 


} catch(PDOException $e) { // Si cualquier consulta del bloque 'try' falla...
    // ...detiene el script y muestra el error de la BBDD.
    die("Error de conexión o consulta: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico y Estadísticas - GMS</title>
    
    <link rel="stylesheet" href="../../css/historico.css"> 
    <link rel="icon" type="image/png" href="../../img/icono.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
</head>
<body>

<nav class="main-header">
    <div class="header-logo">
        <a href="./index.php">
            <img src="../../img/basic_logo_blanco.png" alt="Logo GMS">
        </a>
        <div class="logo-text">
            <span class="gms-title">CASA GMS</span>
        </div>
    </div>

    <div class="header-greeting">
        <?= $saludo ?> <span class="username-tag"><?= htmlspecialchars($username) ?></span>
    </div>

    <div class="header-menu">
        <a href="./index.php" class="nav-link">
            <i class="fa-solid fa-house"></i> Inicio
        </a>
        <a href="./historico.php" class="nav-link"> <i class="fa-solid fa-chart-bar"></i> Histórico
        </a>
        <?php if ($rol == 2): ?>
            <a href="admin_panel.php" class="nav-link">
                <i class="fa-solid fa-gear"></i> Admin
            </a>
        <?php endif; ?>
    </div>

    <form method="post" action="./../PROCEDIMIENTOS/logout.php">
        <button type="submit" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
        </button>
    </form>
</nav>

<div class="main-content p-4">

    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="metric-card">
                <i class="fas fa-bookmark metric-icon"></i>
                <h2 class="metric-number"><?= $stats_general['total_ocupaciones'] ?></h2>
                <p class="metric-label">Ocupaciones Totales</p>
                <div class="metric-trend">
                    <?php if ($tendencia_porcentaje > 0): ?>
                        <i class="fas fa-arrow-up"></i>
                        <span>+<?= abs($tendencia_porcentaje) ?>%</span>
                    <?php elseif ($tendencia_porcentaje < 0): ?>
                        <i class="fas fa-arrow-down"></i>
                        <span><?= $tendencia_porcentaje ?>%</span>
                    <?php else: ?>
                        <i class="fas fa-minus"></i>
                        <span>0%</span>
                    <?php endif; ?>
                    <span class="ms-1">vs mes ant.</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="metric-card success">
                <i class="fas fa-users metric-icon"></i>
                <h2 class="metric-number"><?= $stats_general['total_comensales'] ?></h2>
                <p class="metric-label">Comensales Totales</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="metric-card danger">
                <i class="fas fa-calendar-day metric-icon"></i>
                <h2 class="metric-number"><?= $stats_general['ocupaciones_hoy'] ?? 0 ?></h2>
                <p class="metric-label">Ocupaciones Hoy</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="metric-card info">
                <i class="fas fa-clock metric-icon"></i>
                <h2 class="metric-number"><?= $avg_minutos ?></h2>
                <p class="metric-label">Minutos Promedio</p>
            </div>
        </div>
    </div>
<div class="row mb-4">
        <div class="col-12">
            <div class="glass-card p-4">
                <div class="section-header">
                    <h5 class="section-title mb-0">
                        <i class="fas fa-history text-primary"></i>
                        Histórico de Ocupaciones
                    </h5>
                </div>
                
                <form method="get" action="historico.php" class="filter-form-inline">
                    <fieldset>
                        <legend class="visually-hidden">Filtros de Búsqueda</legend>
                        <div class="row g-2"> <div class="col-md-3 col-6">
                                <label for="sala" class="form-label-sm">Sala</label>
                                <select name="sala" id="sala" class="form-select form-select-sm">
                                    <option value="">Todas</option>
                                    <?php foreach ($salas as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $filtro_sala == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <label for="camarero" class="form-label-sm">Camarero</label>
                                <select name="camarero" id="camarero" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php foreach ($camareros_filtro as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= $filtro_camarero == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-4">
                                <label for="ano" class="form-label-sm">Año</label>
                                <select name="ano" id="ano" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php foreach ($anos as $a): ?>
                                        <option value="<?= $a['ano'] ?>" <?= $filtro_ano == $a['ano'] ? 'selected' : '' ?>><?= $a['ano'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-4">
                                <label for="mes" class="form-label-sm">Mes</label>
                                <select name="mes" id="mes" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php foreach ($meses as $num => $nombre): ?>
                                        <option value="<?= $num ?>" <?= $filtro_mes == $num ? 'selected' : '' ?>><?= $nombre ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1 col-4">
                                <label for="dia" class="form-label-sm">Día</label>
                                <select name="dia" id="dia" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    <?php for ($d = 1; $d <= 31; $d++): ?>
                                        <option value="<?= $d ?>" <?= $filtro_dia == $d ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-1 col-12 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                            </div>
                        </div>
                    </fieldset>
                </form>
                
                <div class="table-responsive mt-3" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-striped table-hover table-sm">
                        <thead class="table-dark" style="position: sticky; top: 0;">
                            <tr>
                                <th>Sala</th>
                                <th>Mesa</th>
                                <th>Camarero</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Dur (min)</th>
                                <th>Comensales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ocupaciones_tabla)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No se encontraron registros con esos filtros.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ocupaciones_tabla as $o): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($o['sala_nombre']) ?></td>
                                        <td><?= htmlspecialchars($o['mesa_nombre']) ?></td>
                                        <td><?= htmlspecialchars($o['camarero']) ?></td>
                                        <td><?= date('d/m/y H:i', strtotime($o['inicio_ocupacion'])) ?></td>
                                        <td><?= $o['final_ocupacion'] ? date('d/m/y H:i', strtotime($o['final_ocupacion'])) : 'En uso' ?></td>
                                        <td>
                                            <?= ($o['duracion_segundos'] > 0) ? round($o['duracion_segundos'] / 60) : '-' ?>
                                        </td>
                                        <td><?= $o['num_comensales'] ?></td>
                                    </tr>
                                <?php endforeach; // Fin del bucle de resultados ?>
                            <?php endif; // Fin del if (empty...) ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
    <div class="row mb-4">
        
        <div class="col-lg-6 mb-4">
            <div class="glass-card p-4">
                <h5 class="section-title mb-0"><i class="fas fa-medal text-warning"></i> Top Camareros</h5>
                <div class="bar-chart-container">
                    <?php foreach ($top_camareros as $item): ?>
                        <?php $percent = ($item['total_mesas'] / $max_camareros) * 100; ?>
                        <div class="bar-row">
                            <span class="bar-label"><?= htmlspecialchars($item['username']) ?></span>
                            <div class="bar-wrap">
                                <div class="bar" style="width: <?= $percent ?>%;"></div>
                            </div>
                            <span class="bar-value"><?= $item['total_mesas'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="glass-card p-4">
                <h5 class="section-title mb-0"><i class="fas fa-door-open text-danger"></i> Salas más Ocupadas</h5>
                <div class="bar-chart-container">
                    <?php foreach ($top_salas as $item): ?>
                        <?php $percent = ($item['total_ocupaciones'] / $max_salas) * 100; ?>
                        <div class="bar-row">
                            <span class="bar-label"><?= htmlspecialchars($item['nombre']) ?></span>
                            <div class="bar-wrap">
                                <div class="bar" style="width: <?= $percent ?>%;"></div>
                            </div>
                            <span class="bar-value"><?= $item['total_ocupaciones'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="glass-card p-4">
                <h5 class="section-title mb-0"><i class="fas fa-clock text-info"></i> Ocupaciones por Hora</h5>
                <div class="bar-chart-container-scroll">
                    <?php for ($i = 0; $i < 24; $i++): ?>
                        <?php $percent = ($horas_data[$i] / $max_horas) * 100; ?>
                        <div class="bar-row">
                            <span class="bar-label"><?= $i ?>:00h</span> <div class="bar-wrap">
                                <div class="bar" style="width: <?= $percent ?>%;"></div>
                            </div>
                            <span class="bar-value"><?= $horas_data[$i] ?></span> </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="glass-card p-4">
                <h5 class="section-title mb-0"><i class="fas fa-calendar-week text-success"></i> Ocupaciones por Día</h5>
                <div class="bar-chart-container">
                    <?php foreach ($dias_labels as $index => $label): ?>
                        <?php $percent = ($dias_data[$index] / $max_dias) * 100; ?>
                        <div class="bar-row">
                            <span class="bar-label"><?= $label ?></span> <div class="bar-wrap">
                                <div class="bar" style="width: <?= $percent ?>%;"></div>
                            </div>
                            <span class="bar-value"><?= $dias_data[$index] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    
</div>

</body>
</html>