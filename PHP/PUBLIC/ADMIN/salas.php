<?php
session_start();
require_once __DIR__ . '/../../CONEXION/conexion.php';

if (!isset($_SESSION['loginok']) || $_SESSION['rol'] != 2) {
    header("Location: ../login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);

// Obtener todas las salas
try {
    $sql = "
        SELECT 
            s.id,
            s.nombre,
            s.num_mesas,
            s.imagen,
            COUNT(m.id) AS mesas_reales
        FROM salas s
        LEFT JOIN mesas m ON s.id = m.id_sala
        GROUP BY s.id
        ORDER BY s.nombre
    ";
    $stmt = $conn->query($sql);
    $salas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error al obtener salas: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Salas - Admin</title>
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
            <a href="./salas.php" class="nav-link active">
                <i class="fa-solid fa-door-open"></i> Salas
            </a>
        </div>

        <form method="post" action="../../PROCEDIMIENTOS/logout.php">
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </button>
        </form>
    </nav>

    <div class="container">
        <h1 class="page-title">Gestión de Salas</h1>

        <button class="btn btn-primary" id="btn-nueva-sala">
            <i class="fa-solid fa-plus"></i> Nueva Sala
        </button>

        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Mesas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salas as $sala): ?>
                            <tr>
                                <td><?= $sala['id'] ?></td>
                                <td>
                                    <?php if ($sala['imagen']): ?>
                                        <img src="../../../img/salas/<?= htmlspecialchars($sala['imagen']) ?>" 
                                             alt="Sala" class="img-thumbnail" style="width: 80px; height: 60px;">
                                    <?php else: ?>
                                        <span class="text-muted">Sin imagen</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($sala['nombre']) ?></td>
                                <td><?= $sala['mesas_reales'] ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning btn-cambiar-imagen" 
                                            data-id="<?= $sala['id'] ?>">
                                        <i class="fa-solid fa-image"></i> Imagen
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-eliminar-sala" 
                                            data-id="<?= $sala['id'] ?>" 
                                            data-nombre="<?= htmlspecialchars($sala['nombre']) ?>">
                                        <i class="fa-solid fa-trash"></i> Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para crear sala -->
    <div id="modalCrear" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="modalCrear">&times;</span>
            <h2>Nueva Sala</h2>
            <form method="POST" action="../../PROCEDIMIENTOS/ADMIN/crear_sala.php">
                <div class="form-group">
                    <label>Nombre de la Sala *</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Número de Mesas</label>
                    <input type="number" name="num_mesas" class="form-control" min="0" value="0">
                </div>
                <button type="submit" class="btn btn-primary">Crear</button>
            </form>
        </div>
    </div>

    <!-- Modal para cambiar imagen -->
    <div id="modalImagen" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="modalImagen">&times;</span>
            <h2>Cambiar Imagen de Sala</h2>
            <form method="POST" action="../../PROCEDIMIENTOS/ADMIN/subir_imagen_sala.php" enctype="multipart/form-data">
                <input type="hidden" name="id_sala" id="imagen_id_sala">
                <div class="form-group">
                    <label>Seleccionar Imagen *</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*" required>
                    <small class="text-muted">Formatos permitidos: JPG, PNG, GIF. Máximo 5MB</small>
                </div>
                <button type="submit" class="btn btn-primary">Subir Imagen</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../../JS/admin_salas.js"></script>
</body>
</html>
