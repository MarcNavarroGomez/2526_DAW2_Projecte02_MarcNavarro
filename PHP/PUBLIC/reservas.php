<?php
// Inicia o reanuda la sesión
session_start();
// Incluye el archivo de conexión a la base de datos
require_once __DIR__ . '/../CONEXION/conexion.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['loginok'])) {
    header("Location: login.php");
    exit();
}

// Obtener datos del usuario de la sesión
$username = htmlspecialchars($_SESSION['username']);
$rol = $_SESSION['rol'] ?? 1;

// Recuperar mensajes de error o éxito pasados por URL
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Filtro opcional de sala para listar reservas
$filtro_sala_id = $_GET['sala_id'] ?? null;

// --- LÓGICA DE BÚSQUEDA DE MESAS DISPONIBLES (PARTE SUPERIOR) ---
// Obtener parámetros de búsqueda del POST (o valores por defecto)
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$hora = $_POST['hora'] ?? date('H:i');
$comensales = $_POST['comensales'] ?? 2;
// Bandera para saber si se ha enviado el formulario de búsqueda
$busqueda_realizada = isset($_POST['buscar_mesas']);
$mesas_disponibles = [];

if ($busqueda_realizada) {
    // Calcular fechas inicio y fin para la búsqueda de disponibilidad
    $fecha_inicio_str = "$fecha $hora";
    // Duración estándar de reserva: 1 hora 30 minutos
    $fecha_fin_normal = date('Y-m-d H:i:s', strtotime('+90 minutes', strtotime($fecha_inicio_str)));
    
    // Definir turnos específicos para salas privadas (Comida vs Cena)
    if ($hora < '17:00') {
        $fecha_fin_privada = "$fecha 17:00:00"; // Turno de comida hasta las 17:00
    } else {
        $fecha_fin_privada = "$fecha 23:59:59"; // Turno de cena hasta final del día
    }

    // Consulta SQL compleja para encontrar mesas disponibles
    // Se buscan mesas que cumplan capacidad y NO tengan ocupaciones que solapen
    $sql = "
        SELECT m.id, m.nombre, m.sillas, s.nombre as sala_nombre, s.id as sala_id
        FROM mesas m
        JOIN salas s ON m.id_sala = s.id
        WHERE m.sillas >= :comensales  /* Filtro por capacidad */
        AND m.id NOT IN (
            /* Subconsulta: Encontrar mesas ocupadas/reservadas en el rango horario */
            SELECT o.id_mesa 
            FROM ocupaciones o
            WHERE (
                /* Lógica de solapamiento de horarios */
                /* Caso 1: Sala Normal - Solapamiento con turno de 90min */
                (s.nombre NOT LIKE 'Privada%' AND :inicio < o.final_ocupacion AND :fin_normal > o.inicio_ocupacion)
                OR
                /* Caso 2: Sala Privada - Solapamiento con turno fijo */
                (s.nombre LIKE 'Privada%' AND :inicio < o.final_ocupacion AND :fin_privada > o.inicio_ocupacion)
            )
            /* Considerar reservas futuras confirmadas (final NOT NULL) o activas hoy */
            AND o.final_ocupacion IS NOT NULL 
            OR (o.final_ocupacion IS NULL AND DATE(o.inicio_ocupacion) = DATE(:inicio)) 
        )
        ORDER BY m.sillas ASC, s.nombre ASC /* Ordenar por capacidad y luego nombre */
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':comensales' => $comensales,
        ':inicio' => $fecha_inicio_str,
        ':fin_normal' => $fecha_fin_normal,
        ':fin_privada' => $fecha_fin_privada ?? $fecha_fin_normal 
    ]);
    
    $mesas_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- OBTENER LISTADO DE RESERVAS FUTURAS (PARTE INFERIOR) ---
// Consulta para obtener reservas desde hoy en adelante
$sql_reservas = "
    SELECT r.*, m.nombre as mesa_nombre, s.nombre as sala_nombre
    FROM reservas r
    JOIN ocupaciones o ON r.id = o.id_reserva
    JOIN mesas m ON o.id_mesa = m.id
    JOIN salas s ON m.id_sala = s.id
    WHERE r.fecha_reserva >= CURDATE()
";

$params_reservas = [];

// Aplicar filtro por sala si existe
if ($filtro_sala_id) {
    $sql_reservas .= " AND s.id = ?";
    $params_reservas[] = $filtro_sala_id;
}

$sql_reservas .= " ORDER BY r.fecha_reserva ASC";

$stmt_listado = $conn->prepare($sql_reservas);
$stmt_listado->execute($params_reservas);
$reservas_futuras = $stmt_listado->fetchAll(PDO::FETCH_ASSOC);

// Obtener nombre de sala para mostrar en el filtro visual
$nombre_sala_filtro = '';
if ($filtro_sala_id) {
    $stmt_s = $conn->prepare("SELECT nombre FROM salas WHERE id = ?");
    $stmt_s->execute([$filtro_sala_id]);
    $nombre_sala_filtro = $stmt_s->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reservas - Casa GMS</title>
    <!-- Fuentes y estilos externos -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/reservas.css">
    <link rel="stylesheet" href="../../css/reservas_extra.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body 
    data-success="<?= htmlspecialchars($success) ?>" 
    data-error="<?= htmlspecialchars($error) ?>">
    
    <!-- Header de navegación -->
    <nav class="main-header">
        <div class="header-logo">
            <img src="../../img/basic_logo_blanco.png" alt="Logo GMS">
            <div class="logo-text"><span class="gms-title">RESERVAS</span></div>
        </div>
        <div class="header-greeting">Hola <span class="username-tag"><?= $username ?></span></div>
        <div class="header-menu">
            <a href="index.php" class="nav-link"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="reservas.php" class="nav-link active"><i class="fa-solid fa-calendar-check"></i> Reservas</a>
        </div>
        <form method="post" action="../PROCEDIMIENTOS/logout.php">
            <button type="submit" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Salir</button>
        </form>
    </nav>

    <div class="reservas-container">
        
        <!-- SECCIÓN DE BÚSQUEDA -->
        <div class="search-box">
            <h2 style="margin-bottom: 20px;"><i class="fa-solid fa-magnifying-glass"></i> Nueva Reserva</h2>
            <!-- Formulario de búsqueda (POST a la misma página) -->
            <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: end;">
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="<?= $fecha ?>" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Hora</label>
                    <input type="time" name="hora" class="form-control" value="<?= $hora ?>" required>
                </div>
                <div class="form-group">
                    <label>Comensales</label>
                    <input type="number" name="comensales" class="form-control" value="<?= $comensales ?>" min="1" max="30" required>
                </div>
                <button type="submit" name="buscar_mesas" class="btn btn-primary" style="height: 45px;">
                    Buscar Mesas
                </button>
            </form>
        </div>

        <!-- RESULTADOS DE BÚSQUEDA -->
        <?php if ($busqueda_realizada): ?>
            <h3 style="margin-bottom: 20px;">Mesas Disponibles para <?= date('d/m/Y', strtotime($fecha)) ?> a las <?= $hora ?></h3>
            
            <?php if (empty($mesas_disponibles)): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i> No hay mesas disponibles para estos criterios. Intenta otra hora o fecha.
                </div>
            <?php else: ?>
                <div class="mesas-grid">
                    <?php foreach ($mesas_disponibles as $mesa): ?>
                        <?php 
                            // Determinar tipo de sala para mostrar info relevante
                            $es_privada = strpos($mesa['sala_nombre'], 'Privada') !== false;
                            $inicio_str = "$fecha $hora";
                            if ($es_privada) {
                                $fin_str = ($hora < '17:00') ? "$fecha 17:00" : "$fecha 23:59";
                                $tipo_reserva = "Turno Completo";
                            } else {
                                $fin_str = date('Y-m-d H:i', strtotime('+90 minutes', strtotime($inicio_str)));
                                $tipo_reserva = "1h 30m";
                            }
                        ?>
                        <!-- Tarjeta de mesa disponible -->
                        <div class="mesa-card" 
                             data-id="<?= $mesa['id'] ?>"
                             data-nombre="<?= htmlspecialchars($mesa['nombre']) ?>"
                             data-sala="<?= htmlspecialchars($mesa['sala_nombre']) ?>"
                             data-inicio="<?= $inicio_str ?>"
                             data-fin="<?= $fin_str ?>"
                             onclick="abrirModalReserva(this)"> <!-- Click abre modal JS -->
                            
                            <div class="mesa-icon">
                                <i class="fa-solid <?= $es_privada ? 'fa-crown' : 'fa-chair' ?>"></i>
                            </div>
                            <div class="mesa-info">
                                <h3><?= htmlspecialchars($mesa['nombre']) ?></h3>
                                <p><i class="fa-solid fa-users"></i> Capacidad: <?= $mesa['sillas'] ?></p>
                                <span class="tag-sala <?= $es_privada ? 'tag-privada' : 'tag-normal' ?>">
                                    <?= htmlspecialchars($mesa['sala_nombre']) ?>
                                </span>
                                <p style="margin-top: 10px; font-size: 0.9em; color: #27ae60;">
                                    <i class="fa-regular fa-clock"></i> <?= $tipo_reserva ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- SECCIÓN LISTADO DE RESERVAS EXISTENTES -->
        <div class="reservas-list-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><i class="fa-solid fa-list"></i> Próximas Reservas</h2>
                <!-- Mostrar tag si hay filtro activo -->
                <?php if ($filtro_sala_id): ?>
                    <div class="filter-tag">
                        Sala: <?= htmlspecialchars($nombre_sala_filtro) ?>
                        <a href="reservas.php"><i class="fa-solid fa-times"></i></a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (empty($reservas_futuras)): ?>
                <p style="color: #7f8c8d; text-align: center; padding: 20px;">No hay reservas próximas registradas.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table-reservas">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Teléfono</th>
                                <th>Sala</th>
                                <th>Mesa</th>
                                <th>Pax</th>
                                <th>Notas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas_futuras as $reserva): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($reserva['fecha_reserva'])) ?></td>
                                    <td><?= date('H:i', strtotime($reserva['fecha_reserva'])) ?></td>
                                    <td><strong><?= htmlspecialchars($reserva['nombre_cliente']) ?></strong></td>
                                    <td><?= htmlspecialchars($reserva['telefono_cliente']) ?></td>
                                    <td><?= htmlspecialchars($reserva['sala_nombre']) ?></td>
                                    <td><?= htmlspecialchars($reserva['mesa_nombre']) ?></td>
                                    <td><?= $reserva['num_comensales'] ?></td>
                                    <td><?= htmlspecialchars($reserva['notas']) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 10px;">
                                            <!-- Botón Editar (pasa datos via data-attributes) -->
                                            <button type="button" class="btn-action btn-edit btn-editar-reserva" 
                                                data-id="<?= $reserva['id'] ?>"
                                                data-nombre="<?= htmlspecialchars($reserva['nombre_cliente'], ENT_QUOTES) ?>"
                                                data-telefono="<?= htmlspecialchars($reserva['telefono_cliente'], ENT_QUOTES) ?>"
                                                data-notas="<?= htmlspecialchars($reserva['notas'] ?? '', ENT_QUOTES) ?>"
                                                title="Editar">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            
                                            <!-- Botón Eliminar (la lógica JS lo maneja) -->
                                            <button type="button" class="btn-action btn-delete btn-eliminar-reserva" 
                                                data-id="<?= $reserva['id'] ?>"
                                                title="Eliminar">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- MODAL EDITAR RESERVA -->
    <div id="modalEditarReserva" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="modalEditarReserva">&times;</span>
            <h2>Editar Reserva</h2>
            <!-- Formulario apunta al PROCEDIMIENTO de actualización -->
            <form method="POST" action="../PROCEDIMIENTOS/actualizar_reserva.php">
                <input type="hidden" name="actualizar_reserva" value="1">
                <input type="hidden" name="id_reserva" id="edit_id_reserva">
                
                <div class="form-group">
                    <label>Nombre Cliente</label>
                    <input type="text" name="nombre_cliente" id="edit_nombre_cliente" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono_cliente" id="edit_telefono_cliente" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notas" id="edit_notas" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <!-- MODAL CONFIRMAR CREACIÓN DE RESERVA -->
    <div id="modalReserva" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="modalReserva">&times;</span>
            <h2 style="color: #2c3e50; margin-bottom: 20px;">Confirmar Reserva</h2>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p><strong>Mesa:</strong> <span id="res_mesa"></span></p>
                <p><strong>Sala:</strong> <span id="res_sala"></span></p>
                <p><strong>Horario:</strong> <span id="res_horario"></span></p>
            </div>

            <!-- Formulario apunta al PROCEDIMIENTO de creación -->
            <form method="POST" action="../PROCEDIMIENTOS/crear_reserva.php">
                <input type="hidden" name="fecha" value="<?= $fecha ?>">
                <input type="hidden" name="hora" value="<?= $hora ?>">
                <input type="hidden" name="comensales" value="<?= $comensales ?>">
                
                <input type="hidden" name="mesa_id" id="input_mesa_id">
                <input type="hidden" name="fecha_reserva_final" id="input_fecha_inicio">
                <input type="hidden" name="fecha_fin_reserva_final" id="input_fecha_fin">

                <div class="form-group">
                    <label>Nombre Cliente *</label>
                    <input type="text" name="cliente_nombre" class="form-control" required placeholder="Ej: Juan Pérez">
                </div>
                <div class="form-group">
                    <label>Teléfono *</label>
                    <input type="tel" name="cliente_telefono" class="form-control" required placeholder="Ej: 666777888">
                </div>
                <div class="form-group">
                    <label>Notas (Opcional)</label>
                    <textarea name="notas" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" name="confirmar_reserva" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                    Confirmar Reserva
                </button>
            </form>
        </div>
    </div>

    <!-- Scripts externos y propios -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../JS/reservas.js"></script>
</body>
</html>
