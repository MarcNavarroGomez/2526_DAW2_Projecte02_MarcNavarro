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
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=metodo_invalido");
    exit();
}

$id_usuario = intval($_POST['id_usuario']);

// Validar que no se elimine a sí mismo
if ($id_usuario == $_SESSION['user_id']) {
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=no_puedes_eliminarte");
    exit();
}

try {
    $conn->beginTransaction();
    
    // Verificar que el usuario existe
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE id = :id");
    $stmt_check->execute([':id' => $id_usuario]);
    
    if (!$stmt_check->fetch()) {
        throw new Exception("El usuario no existe");
    }
    
    // Soft delete (fecha_baja)
    $stmt = $conn->prepare("
        UPDATE users 
        SET fecha_baja = NOW() 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id_usuario]);
    
    $conn->commit();
    
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?success=usuario_eliminado");
    exit();
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error en eliminar_usuario.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=db_error");
    exit();
    
} catch (Exception $e) {
    $conn->rollBack();
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
