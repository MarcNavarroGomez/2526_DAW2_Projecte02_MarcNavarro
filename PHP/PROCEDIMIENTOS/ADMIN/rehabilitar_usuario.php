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

try {
    $conn->beginTransaction();
    
    // Rehabilitar usuario (fecha_baja = NULL)
    $stmt = $conn->prepare("
        UPDATE users 
        SET fecha_baja = NULL 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id_usuario]);
    
    $conn->commit();
    
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?success=usuario_rehabilitado");
    exit();
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error en rehabilitar_usuario.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=db_error");
    exit();
}
?>
