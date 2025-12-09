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
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=metodo_invalido");
    exit();
}

// Recoger y sanitizar datos
$nombre = trim($_POST['nombre']);
$id_sala = intval($_POST['id_sala']);
$sillas = intval($_POST['sillas']);

// Validaciones
if (empty($nombre) || $id_sala <= 0 || $sillas <= 0) {
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=campos_invalidos");
    exit();
}

try {
    // Verificar que la sala existe
    $stmt_check = $conn->prepare("SELECT id FROM salas WHERE id = :id_sala");
    $stmt_check->execute([':id_sala' => $id_sala]);
    
    if (!$stmt_check->fetch()) {
        throw new Exception("La sala seleccionada no existe");
    }
    
    // Insertar mesa
    $stmt = $conn->prepare("
        INSERT INTO mesas (nombre, id_sala, sillas, estado) 
        VALUES (:nombre, :id_sala, :sillas, 1)
    ");
    
    $stmt->execute([
        ':nombre' => $nombre,
        ':id_sala' => $id_sala,
        ':sillas' => $sillas
    ]);
    
    header("Location: ../../PUBLIC/ADMIN/mesas.php?success=mesa_creada");
    exit();
    
} catch (PDOException $e) {
    error_log("Error en crear_mesa.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=db_error");
    exit();
    
} catch (Exception $e) {
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
