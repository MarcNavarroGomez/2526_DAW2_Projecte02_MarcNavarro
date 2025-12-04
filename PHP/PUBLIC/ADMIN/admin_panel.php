<?php
session_start();
require_once __DIR__ . '/../../CONEXION/conexion.php';

// Verificar que sea administrador
if (!isset($_SESSION['loginok']) || $_SESSION['rol'] != 2) {
    header("Location: ../login.php");
    exit();
}

$nombre = htmlspecialchars($_SESSION['nombre']);
$username = htmlspecialchars($_SESSION['username']);

// Obtener estadísticas
try {
    // Total de usuarios
    $stmt_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE fecha_baja IS NULL");
    $total_users = $stmt_users->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de salas
    $stmt_salas = $conn->query("SELECT COUNT(*) as total FROM salas");
    $total_salas = $stmt_salas->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de mesas
    $stmt_mesas = $conn->query("SELECT COUNT(*) as total FROM mesas");
    $total_mesas = $stmt_mesas->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total de reservas activas
    $stmt_reservas = $conn->query("SELECT COUNT(*) as total FROM reservas WHERE estado IN (1,2)");
    $total_reservas = $stmt_reservas->fetch(PDO::FETCH_ASSOC)['total'];
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Casa GMS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../../css/admin.css">
    <link rel="icon" type="image/png" href="../../../img/icono.png">
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
            <a href="./admin_panel.php" class="nav-link active">
                <i class="fa-solid fa-gear"></i> Admin
            </a>
        </div>

        <form method="post" action="../../PROCEDIMIENTOS/logout.php">
            <button type="submit" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </button>
        </form>
    </nav>

    <div class="container">
        <h1 class="page-title">Panel de Administración</h1>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-value"><?= $total_users ?></div>
                <div class="stat-label">Usuarios Activos</div>
                <i class="stat-icon fa-solid fa-users"></i>
            </div>
            
            <div class="stat-card success">
                <div class="stat-value"><?= $total_salas ?></div>
                <div class="stat-label">Salas</div>
                <i class="stat-icon fa-solid fa-door-open"></i>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-value"><?= $total_mesas ?></div>
                <div class="stat-label">Mesas</div>
                <i class="stat-icon fa-solid fa-table"></i>
            </div>
            
            <div class="stat-card info">
                <div class="stat-value"><?= $total_reservas ?></div>
                <div class="stat-label">Reservas Activas</div>
                <i class="stat-icon fa-solid fa-calendar-check"></i>
            </div>
        </div>

        <!-- Menú de administración -->
        <div class="admin-menu">
            <a href="./usuarios.php" class="admin-card">
                <i class="fa-solid fa-users"></i>
                <h3>Gestión de Usuarios</h3>
                <p>Crear, editar y eliminar usuarios del sistema</p>
            </a>
            
            <a href="./salas.php" class="admin-card">
                <i class="fa-solid fa-door-open"></i>
                <h3>Gestión de Salas</h3>
                <p>Administrar salas y sus imágenes</p>
            </a>
            
            <a href="./mesas.php" class="admin-card">
                <i class="fa-solid fa-table"></i>
                <h3>Gestión de Mesas</h3>
                <p>Crear, editar y eliminar mesas</p>
            </a>
        </div>
    </div>
</body>
</html>
