<?php
session_start();
require_once __DIR__ . '/../CONEXION/conexion.php';

// Verificar sesión
if (!isset($_SESSION['loginok'])) {
    header("Location: login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$rol = $_SESSION['rol'] ?? 1;

// Variables para el formulario
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$hora = $_POST['hora'] ?? date('H:00', strtotime('+1 hour'));
$comensales = $_POST['comensales'] ?? 2;
$mesas_disponibles = [];
$busqueda_realizada = false;
$error = '';
$success = '';

// Procesar Reserva (Insertar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_reserva'])) {
    try {
        $conn->beginTransaction();

        $mesa_id = $_POST['mesa_id'];
        $cliente_nombre = $_POST['cliente_nombre'];
        $cliente_telefono = $_POST['cliente_telefono'];
        $notas = $_POST['notas'];
        
        // Recalcular fechas para seguridad
        $fecha_inicio = $_POST['fecha_reserva_final']; // Viene del hidden calculado
        $fecha_fin = $_POST['fecha_fin_reserva_final']; // Viene del hidden calculado
        
        // 1. Insertar Reserva
        $stmt = $conn->prepare("
            INSERT INTO reservas (fecha_reserva, fecha_fin_reserva, num_comensales, nombre_cliente, telefono_cliente, notas, estado)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$fecha_inicio, $fecha_fin, $comensales, $cliente_nombre, $cliente_telefono, $notas]);
        $id_reserva = $conn->lastInsertId();

        // 2. Insertar Ocupación Futura
        // Necesitamos el ID del camarero actual
        $stmt_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_user->execute([$username]);
        $id_camarero = $stmt_user->fetchColumn();

        $stmt_ocup = $conn->prepare("
            INSERT INTO ocupaciones (id_camarero, id_mesa, inicio_ocupacion, final_ocupacion, num_comensales, id_reserva)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_ocup->execute([$id_camarero, $mesa_id, $fecha_inicio, $fecha_fin, $comensales, $id_reserva]);

        $conn->commit();
        $success = "Reserva confirmada para " . htmlspecialchars($cliente_nombre);
        
        // Limpiar búsqueda
        $busqueda_realizada = false;

    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error al crear la reserva: " . $e->getMessage();
    }
}

// Procesar Búsqueda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_mesas'])) {
    $busqueda_realizada = true;
    $fecha_inicio_str = "$fecha $hora";
    $timestamp_inicio = strtotime($fecha_inicio_str);
    
    // Definir rango según tipo de sala (se hará en la query o post-procesado)
    // Estrategia: Calcular el fin para mesas normales (1.5h) y privadas (Turno)
    
    // Horarios Privadas
    $es_comida = ($hora >= '13:00' && $hora < '17:00');
    $es_cena = ($hora >= '20:00');
    
    // Fin por defecto (Normales)
    $timestamp_fin_normal = strtotime('+90 minutes', $timestamp_inicio);
    $fecha_fin_normal = date('Y-m-d H:i:s', $timestamp_fin_normal);

    // Fin Privadas
    if ($es_comida) {
        $fecha_fin_privada = "$fecha 17:00:00";
        $fecha_inicio_privada = "$fecha 13:00:00"; // Forzamos inicio de turno si es privada? O dejamos la hora elegida?
        // Mejor respetamos la hora elegida por el cliente como inicio, pero el fin es fijo.
    } else {
        $fecha_fin_privada = "$fecha 23:59:59"; // O cierre
    }
    
    // Query para buscar mesas
    // Una mesa está disponible si NO existe ninguna ocupación que se solape con el rango deseado.
    // Solapamiento: (InicioA < FinB) AND (FinA > InicioB)
    
    $sql = "
        SELECT m.id, m.nombre, m.sillas, s.nombre as sala_nombre, s.id as sala_id
        FROM mesas m
        JOIN salas s ON m.id_sala = s.id
        WHERE m.sillas >= :comensales
        AND m.id NOT IN (
            SELECT o.id_mesa 
            FROM ocupaciones o
            WHERE (
                -- Lógica de solapamiento
                -- Para mesas normales usamos el rango normal
                (s.nombre NOT LIKE 'Privada%' AND :inicio < o.final_ocupacion AND :fin_normal > o.inicio_ocupacion)
                OR
                -- Para privadas usamos el rango de turno
                (s.nombre LIKE 'Privada%' AND :inicio < o.final_ocupacion AND :fin_privada > o.inicio_ocupacion)
            )
            AND o.final_ocupacion IS NOT NULL -- Solo ocupaciones con fin definido (reservas/histórico)
            -- Nota: Las ocupaciones activas actuales (final IS NULL) también deberían bloquear si es para HOY AHORA.
            -- Pero como son reservas futuras, asumimos que las ocupaciones activas actuales terminan pronto.
            -- Si la reserva es para YA, deberíamos mirar las activas.
            OR (o.final_ocupacion IS NULL AND DATE(o.inicio_ocupacion) = DATE(:inicio)) -- Bloqueo simple si está ocupada hoy
        )
        ORDER BY m.sillas ASC, s.nombre ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':comensales' => $comensales,
        ':inicio' => $fecha_inicio_str,
        ':fin_normal' => $fecha_fin_normal,
        ':fin_privada' => $fecha_fin_privada ?? $fecha_fin_normal // Fallback
    ]);
    
    $mesas_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reservas - Casa GMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css"> <!-- Reutilizamos estilos -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .reservas-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }
        .search-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .mesas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .mesa-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
            border: 1px solid #eee;
            cursor: pointer;
        }
        .mesa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        .mesa-icon {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        .mesa-info h3 { margin: 0 0 10px 0; color: #34495e; }
        .mesa-info p { margin: 5px 0; color: #7f8c8d; }
        .tag-sala {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 10px;
        }
        .tag-privada { background: #e8f6f3; color: #1abc9c; }
        .tag-normal { background: #f4f6f7; color: #7f8c8d; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background-color: #fff; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; position: relative; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <nav class="main-header">
        <div class="header-logo">
            <img src="../img/basic_logo_blanco.png" alt="Logo GMS">
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
        
        <!-- Buscador -->
        <div class="search-box">
            <h2 style="margin-bottom: 20px;"><i class="fa-solid fa-magnifying-glass"></i> Buscar Disponibilidad</h2>
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

        <!-- Resultados -->
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
                            $es_privada = strpos($mesa['sala_nombre'], 'Privada') !== false;
                            
                            // Calcular fin para mostrar y para el form
                            $inicio_str = "$fecha $hora";
                            if ($es_privada) {
                                $fin_str = ($hora < '17:00') ? "$fecha 17:00" : "$fecha 23:59";
                                $tipo_reserva = "Turno Completo";
                            } else {
                                $fin_str = date('Y-m-d H:i', strtotime('+90 minutes', strtotime($inicio_str)));
                                $tipo_reserva = "1h 30m";
                            }
                        ?>
                        <div class="mesa-card" 
                             data-id="<?= $mesa['id'] ?>"
                             data-nombre="<?= htmlspecialchars($mesa['nombre']) ?>"
                             data-sala="<?= htmlspecialchars($mesa['sala_nombre']) ?>"
                             data-inicio="<?= $inicio_str ?>"
                             data-fin="<?= $fin_str ?>"
                             onclick="abrirModalReserva(this)">
                            
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
    </div>

    <!-- Modal Confirmar Reserva -->
    <div id="modalReserva" class="modal">
        <div class="modal-content">
            <span class="close" id="closeModal">&times;</span>
            <h2 style="color: #2c3e50; margin-bottom: 20px;">Confirmar Reserva</h2>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <p><strong>Mesa:</strong> <span id="res_mesa"></span></p>
                <p><strong>Sala:</strong> <span id="res_sala"></span></p>
                <p><strong>Horario:</strong> <span id="res_horario"></span></p>
            </div>

            <form method="POST">
                <!-- Mantener datos de búsqueda -->
                <input type="hidden" name="fecha" value="<?= $fecha ?>">
                <input type="hidden" name="hora" value="<?= $hora ?>">
                <input type="hidden" name="comensales" value="<?= $comensales ?>">
                
                <!-- Datos calculados -->
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // DOM Clásico
        var modal = document.getElementById('modalReserva');
        var closeBtn = document.getElementById('closeModal');

        if (closeBtn) {
            closeBtn.onclick = function() {
                modal.style.display = 'none';
            }
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        function abrirModalReserva(elemento) {
            var id = elemento.getAttribute('data-id');
            var nombre = elemento.getAttribute('data-nombre');
            var sala = elemento.getAttribute('data-sala');
            var inicio = elemento.getAttribute('data-inicio');
            var fin = elemento.getAttribute('data-fin');

            // Formatear hora para mostrar
            var horaInicio = inicio.split(' ')[1].substring(0, 5);
            var horaFin = fin.split(' ')[1].substring(0, 5);

            document.getElementById('res_mesa').innerText = nombre;
            document.getElementById('res_sala').innerText = sala;
            document.getElementById('res_horario').innerText = horaInicio + ' - ' + horaFin;

            document.getElementById('input_mesa_id').value = id;
            document.getElementById('input_fecha_inicio').value = inicio;
            document.getElementById('input_fecha_fin').value = fin;

            modal.style.display = 'flex';
        }

        // Mensajes PHP
        <?php if ($success): ?>
            Swal.fire('¡Reserva Creada!', '<?= $success ?>', 'success');
        <?php endif; ?>
        <?php if ($error): ?>
            Swal.fire('Error', '<?= $error ?>', 'error');
        <?php endif; ?>
    </script>
</body>
</html>
