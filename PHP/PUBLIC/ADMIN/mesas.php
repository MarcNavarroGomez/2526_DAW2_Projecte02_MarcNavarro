<?php
session_start();
require_once __DIR__ . '/../../CONEXION/conexion.php';

if (!isset($_SESSION['loginok']) || $_SESSION['rol'] != 2) {
    header("Location: ../login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);

// Obtener todas las salas para el selector
try {
    $sql_salas = "SELECT id, nombre FROM salas ORDER BY nombre";
    $stmt_salas = $conn->query($sql_salas);
    $salas = $stmt_salas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al obtener salas: " . $e->getMessage());
}

// Obtener todas las mesas con información de sala
try {
    $sql = "
        SELECT 
            m.id,
            m.nombre,
            m.sillas,
            m.estado,
            s.nombre AS sala_nombre,
            m.id_sala
        FROM mesas m
        JOIN salas s ON m.id_sala = s.id
        ORDER BY s.nombre, m.nombre
    ";
    $stmt = $conn->query($sql);
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error al obtener mesas: " . $e->getMessage());
}

function getEstadoMesa($estado) {
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
    <title>Gestión de Mesas - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../../css/admin.css">
    <link rel="icon" type="image/png" href="../../../img/icono.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body>
    <nav class="main-header">
        <div class="header-logo">
            <img src="../../../img/basic_logo_blanco.png" alt="Logo GMS">
            <div class="logo-text">
                <span class="gms-title">CASA GMS - ADMIN</span>
            </div>
        </div>

        <div class="header-greeting">
            Hola <span class="username-tag"><?= $username ?></span>
        </div>

        <div class="header-menu">
            <a href="../index.php" class="nav-link">
                <i class="fa-solid fa-house"></i> Inicio
            </a>
            <a href="./admin_panel.php" class="nav-link">
                <i class="fa-solid fa-gear"></i> Admin
            </a>
            <a href="./usuarios.php" class="nav-link">
                <i class="fa-solid fa-users"></i> Usuarios
            </a>
            <a href="./salas.php" class="nav-link">
                <i class="fa-solid fa-door-open"></i> Salas
            </a>
            <a href="./mesas.php" class="nav-link active">
                <i class="fa-solid fa-chair"></i> Mesas
            </a>
        </div>

        <form method="post" action="../../PROCEDIMIENTOS/logout.php">
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </button>
        </form>
    </nav>

    <div class="container">
        <h1 class="page-title">Gestión de Mesas</h1>

        <button class="btn btn-primary" id="btn-nueva-mesa">
            <i class="fa-solid fa-plus"></i> Nueva Mesa
        </button>

        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Sala</th>
                            <th>Sillas</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mesas as $mesa): ?>
                            <tr>
                                <td><?= $mesa['id'] ?></td>
                                <td><?= htmlspecialchars($mesa['nombre']) ?></td>
                                <td><?= htmlspecialchars($mesa['sala_nombre']) ?></td>
                                <td><?= $mesa['sillas'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $mesa['estado'] == 1 ? 'success' : ($mesa['estado'] == 2 ? 'danger' : 'warning') ?>">
                                        <?= getEstadoMesa($mesa['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info btn-editar-mesa" 
                                            data-id="<?= $mesa['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($mesa['nombre']) ?>"
                                            data-sala="<?= $mesa['id_sala'] ?>"
                                            data-sillas="<?= $mesa['sillas'] ?>">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-eliminar-mesa" 
                                            data-id="<?= $mesa['id'] ?>" 
                                            data-nombre="<?= htmlspecialchars($mesa['nombre']) ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para crear mesa -->
    <div id="modalCrear" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="modalCrear">&times;</span>
            <h2>Nueva Mesa</h2>
            <form method="POST" action="../../PROCEDIMIENTOS/ADMIN/crear_mesa.php">
                <div class="form-group">
                    <label>Nombre de la Mesa *</label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Ej: T1-1">
                </div>
                <div class="form-group">
                    <label>Sala *</label>
                    <select name="id_sala" class="form-control" required>
                        <option value="">Seleccionar sala...</option>
                        <?php foreach ($salas as $sala): ?>
                            <option value="<?= $sala['id'] ?>"><?= htmlspecialchars($sala['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Número de Sillas *</label>
                    <input type="number" name="sillas" class="form-control" required min="1" max="30" value="4">
                </div>
                <button type="submit" class="btn btn-primary">Crear Mesa</button>
            </form>
        </div>
    </div>

    <!-- Modal para editar mesa -->
    <div id="modalEditar" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="modalEditar">&times;</span>
            <h2>Editar Mesa</h2>
            <form method="POST" action="../../PROCEDIMIENTOS/ADMIN/editar_mesa.php">
                <input type="hidden" name="id_mesa" id="edit_id">
                <div class="form-group">
                    <label>Nombre de la Mesa *</label>
                    <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Sala *</label>
                    <select name="id_sala" id="edit_sala" class="form-control" required>
                        <?php foreach ($salas as $sala): ?>
                            <option value="<?= $sala['id'] ?>"><?= htmlspecialchars($sala['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Número de Sillas *</label>
                    <input type="number" name="sillas" id="edit_sillas" class="form-control" required min="1" max="30">
                </div>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../../JS/admin_mesas.js"></script>
</body>
</html>
