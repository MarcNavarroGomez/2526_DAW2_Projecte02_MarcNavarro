<?php
session_start();
require_once __DIR__ . '/../../CONEXION/conexion.php';

if (!isset($_SESSION['loginok']) || $_SESSION['rol'] != 2) {
    header("Location: ../login.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);

// Obtener todos los usuarios activos
try {
    $sql = "
        SELECT 
            id, username, nombre, apellido, email, rol, fecha_alta
        FROM users
        WHERE fecha_baja IS NULL
        ORDER BY fecha_alta DESC
    ";
    $stmt = $conn->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener usuarios inactivos (baja lógica)
    $sql_inactivos = "
        SELECT 
            id, username, nombre, apellido, email, rol, fecha_baja
        FROM users
        WHERE fecha_baja IS NOT NULL
        ORDER BY fecha_baja DESC
    ";
    $stmt_inactivos = $conn->query($sql_inactivos);
    $usuarios_inactivos = $stmt_inactivos->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function getNombreRol($rol) {
    switch ($rol) {
        case 1: return 'Camarero';
        case 2: return 'Administrador';
        case 3: return 'Mantenimiento';
        default: return 'Desconocido';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios - Admin</title>
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
            <a href="./usuarios.php" class="nav-link active">
                <i class="fa-solid fa-users"></i> Usuarios
            </a>
        </div>

        <form method="post" action="../../PROCEDIMIENTOS/logout.php">
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </button>
        </form>
    </nav>

    <div class="container">
        <h1 class="page-title">Gestión de Usuarios</h1>

        <button class="btn btn-primary" id="btn-nuevo-usuario">
            <i class="fa-solid fa-plus"></i> Nuevo Usuario
        </button>
        <button class="btn btn-primary" id="btn-ver-inactivos" style="margin-left: 10px;">
            <i class="fa-solid fa-user-slash"></i> Usuarios Inactivos
        </button>

        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Fecha Alta</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= getNombreRol($user['rol']) ?></td>
                                <td><?= date('d/m/Y', strtotime($user['fecha_alta'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info btn-editar-usuario" 
                                            data-id="<?= $user['id'] ?>"
                                            data-username="<?= htmlspecialchars($user['username']) ?>"
                                            data-nombre="<?= htmlspecialchars($user['nombre']) ?>"
                                            data-apellido="<?= htmlspecialchars($user['apellido']) ?>"
                                            data-email="<?= htmlspecialchars($user['email']) ?>"
                                            data-rol="<?= $user['rol'] ?>">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['id_usuario']): ?>
                                        <button class="btn btn-sm btn-danger btn-eliminar-usuario" 
                                                data-id="<?= $user['id'] ?>" 
                                                data-username="<?= htmlspecialchars($user['username']) ?>">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para crear usuario -->
    <div id="modalCrear" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="modalCrear">&times;</span>
            <h2>Nuevo Usuario</h2>
            <form method="POST" action="../../PROCEDIMIENTOS/ADMIN/crear_usuario.php">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Apellido</label>
                    <input type="text" name="apellido" class="form-control">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="password" class="form-control" required minlength="5">
                </div>
                <div class="form-group">
                    <label>Rol *</label>
                    <select name="rol" class="form-control" required>
                        <option value="1">Camarero</option>
                        <option value="2">Administrador</option>
                        <option value="3">Mantenimiento</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Crear Usuario</button>
            </form>
        </div>
    </div>

    <!-- Modal para editar usuario -->
    <div id="modalEditar" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" data-modal="modalEditar">&times;</span>
            <h2>Editar Usuario</h2>
            <form method="POST" action="../../PROCEDIMIENTOS/ADMIN/editar_usuario.php">
                <input type="hidden" name="id_usuario" id="edit_id">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="edit_username" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Apellido</label>
                    <input type="text" name="apellido" id="edit_apellido" class="form-control">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Rol *</label>
                    <select name="rol" id="edit_rol" class="form-control" required>
                        <option value="1">Camarero</option>
                        <option value="2">Administrador</option>
                        <option value="3">Mantenimiento</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </form>
        </div>
    </div>

    <!-- Modal para usuarios inactivos -->
    <div id="modalInactivos" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close" data-modal="modalInactivos">&times;</span>
            <h2>Usuarios Inactivos</h2>
            
            <?php if (empty($usuarios_inactivos)): ?>
                <p class="text-center">No hay usuarios inactivos.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Nombre</th>
                            <th>Fecha Baja</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios_inactivos as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($user['fecha_baja'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-success btn-rehabilitar-usuario" 
                                            data-id="<?= $user['id'] ?>" 
                                            data-username="<?= htmlspecialchars($user['username']) ?>"
                                            title="Rehabilitar">
                                        <i class="fa-solid fa-trash-restore"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-borrar-permanente" 
                                            data-id="<?= $user['id'] ?>" 
                                            data-username="<?= htmlspecialchars($user['username']) ?>"
                                            title="Eliminar permanentemente">
                                        <i class="fa-solid fa-ban"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../../JS/admin_usuarios.js"></script>
</body>
</html>
