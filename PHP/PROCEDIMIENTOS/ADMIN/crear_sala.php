<?php
session_start();
require_once __DIR__ . '/../../CONEXION/conexion.php';

// Verificar que sea administrador
if (!isset($_SESSION['loginok']) || $_SESSION['rol'] != 2) {
    header("Location: ../../PUBLIC/login.php");
    exit();
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=metodo_invalido");
    exit();
}

// Recoger y sanitizar datos
$nombre = trim($_POST['nombre']);
$num_mesas = intval($_POST['num_mesas']);

// Validaciones
if (empty($nombre)) {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=nombre_vacio");
    exit();
}

try {
    // Insertar sala
    $stmt = $conn->prepare("
        INSERT INTO salas (nombre, num_mesas) 
        VALUES (:nombre, :num_mesas)
    ");
    
    $stmt->execute([
        ':nombre' => $nombre,
        ':num_mesas' => $num_mesas
    ]);
    
    header("Location: ../../PUBLIC/ADMIN/salas.php?success=sala_creada");
    exit();
    
} catch (PDOException $e) {
    error_log("Error en crear_sala.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=db_error");
    exit();
}
?>
