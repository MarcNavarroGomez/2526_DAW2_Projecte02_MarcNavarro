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
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=metodo_invalido");
    exit();
}

$id_usuario = intval($_POST['id_usuario']);

// Validar que no se elimine a sí mismo (Protección crítica)
if ($id_usuario == $_SESSION['id_usuario']) { // Nota: Corregido de user_id a id_usuario que es lo estándar en $_SESSION
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=no_puedes_eliminarte");
    exit();
}

try {
    $conn->beginTransaction();
    
    // 1. Verificar que el usuario existe
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE id = :id");
    $stmt_check->execute([':id' => $id_usuario]);
    
    if (!$stmt_check->fetch()) {
        throw new Exception("El usuario no existe");
    }
    
    // 2. Realizar BAJA LÓGICA (Soft Delete)
    // En lugar de DELETE, actualizamos la fecha de baja.
    // Esto mantiene la integridad del historial de ocupaciones y permite restauración.
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
