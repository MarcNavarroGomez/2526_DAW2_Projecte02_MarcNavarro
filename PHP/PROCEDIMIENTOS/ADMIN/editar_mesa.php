<?php
// Inicia sesión
session_start();
// Requiere conexión a base de datos
require_once __DIR__ . '/../../CONEXION/conexion.php';

// --- Verificación de sesión de Administrador ---
if (!isset($_SESSION['loginok']) || $_SESSION['rol'] != 2) {
    header("Location: ../../PUBLIC/login.php");
    exit();
}

// Verificar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=metodo_invalido");
    exit();
}

// Recoger y sanitizar datos
$id_mesa = intval($_POST['id_mesa']);
$nombre = trim($_POST['nombre']);
$id_sala = intval($_POST['id_sala']);
$sillas = intval($_POST['sillas']);

// Validaciones
if ($id_mesa <= 0 || empty($nombre) || $id_sala <= 0 || $sillas <= 0) {
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=campos_invalidos");
    exit();
}

try {
    // 1. Verificar existencia de la sala destino
    $stmt_check = $conn->prepare("SELECT id FROM salas WHERE id = :id_sala");
    $stmt_check->execute([':id_sala' => $id_sala]);
    
    if (!$stmt_check->fetch()) {
        throw new Exception("La sala seleccionada no existe");
    }
    
    // 2. Actualizar datos de la mesa
    $stmt = $conn->prepare("
        UPDATE mesas 
        SET nombre = :nombre, 
            id_sala = :id_sala, 
            sillas = :sillas 
        WHERE id = :id_mesa
    ");
    
    $stmt->execute([
        ':nombre' => $nombre,
        ':id_sala' => $id_sala,
        ':sillas' => $sillas,
        ':id_mesa' => $id_mesa
    ]);
    
    header("Location: ../../PUBLIC/ADMIN/mesas.php?success=mesa_editada");
    exit();
    
} catch (PDOException $e) {
    // Error de BD
    error_log("Error en editar_mesa.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=db_error");
    exit();
    
} catch (Exception $e) {
    // Error lógico
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
