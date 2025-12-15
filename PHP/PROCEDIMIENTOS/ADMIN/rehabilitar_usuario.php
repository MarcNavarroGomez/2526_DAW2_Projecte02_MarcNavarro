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

try {
    // Iniciar transacción
    $conn->beginTransaction();
    
    // Rehabilitar usuario: Poner fecha_baja a NULL
    // Esto restaura el acceso al sistema para este usuario
    $stmt = $conn->prepare("
        UPDATE users 
        SET fecha_baja = NULL 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id_usuario]);
    
    // Confirmar cambios
    $conn->commit();
    
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?success=usuario_rehabilitado");
    exit();
    
} catch (PDOException $e) {
    // En caso de error, revertir y notificar
    $conn->rollBack();
    error_log("Error en rehabilitar_usuario.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=db_error");
    exit();
}
?>
