<?php
// Inicia sesión
session_start();
// Requiere conexión a base de datos
require_once __DIR__ . '/../../CONEXION/conexion.php';

// --- Verificación de sesión de Administrador ---
if (!isset($_SESSION['loginok']) || $_SESSION['rol'] != 2) {
    // Si no es admin, redirige al login
    header("Location: ../../PUBLIC/login.php");
    exit();
}

// Verificar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=metodo_invalido");
    exit();
}

// Recoger y sanitizar datos del formulario
$nombre = trim($_POST['nombre']);
$id_sala = intval($_POST['id_sala']);
$sillas = intval($_POST['sillas']);

// Validaciones básicas de entrada
if (empty($nombre) || $id_sala <= 0 || $sillas <= 0) {
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=campos_invalidos");
    exit();
}

try {
    // 1. Verificar existencia de la sala
    $stmt_check = $conn->prepare("SELECT id FROM salas WHERE id = :id_sala");
    $stmt_check->execute([':id_sala' => $id_sala]);
    
    if (!$stmt_check->fetch()) {
        throw new Exception("La sala seleccionada no existe");
    }
    
    // 2. Insertar la nueva mesa
    // El estado inicial es 1 (Libre)
    $stmt = $conn->prepare("
        INSERT INTO mesas (nombre, id_sala, sillas, estado) 
        VALUES (:nombre, :id_sala, :sillas, 1)
    ");
    
    $stmt->execute([
        ':nombre' => $nombre,
        ':id_sala' => $id_sala,
        ':sillas' => $sillas
    ]);
    
    // Redirigir con éxito
    header("Location: ../../PUBLIC/ADMIN/mesas.php?success=mesa_creada");
    exit();
    
} catch (PDOException $e) {
    // Registrar error y redirigir
    error_log("Error en crear_mesa.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=db_error");
    exit();
    
} catch (Exception $e) {
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
