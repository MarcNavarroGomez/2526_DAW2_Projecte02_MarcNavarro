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

// Validar que no se elimine a sí mismo (por seguridad extra, aunque debería estar inactivo)
if ($id_usuario == $_SESSION['id_usuario']) {
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=no_puedes_eliminarte");
    exit();
}

try {
    $conn->beginTransaction();
    
    // 1. Desvincular mesas asignadas por este usuario (poner a NULL)
    // Aunque no es FK estricta, es bueno limpiar la referencia
    $stmt_mesas = $conn->prepare("UPDATE mesas SET asignado_por = NULL WHERE asignado_por = :id");
    $stmt_mesas->execute([':id' => $id_usuario]);

    // 2. Eliminar ocupaciones asociadas (HISTORIAL)
    // Como no usamos ON DELETE CASCADE en la BBDD, debemos hacerlo manualmente
    $stmt_ocupaciones = $conn->prepare("DELETE FROM ocupaciones WHERE id_camarero = :id");
    $stmt_ocupaciones->execute([':id' => $id_usuario]);

    // 3. Borrado físico del usuario
    $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $id_usuario]);
    
    $conn->commit();
    
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?success=usuario_borrado_total");
    exit();
    
} catch (PDOException $e) {
    $conn->rollBack();
    // Si falla por FK (ej: tiene ocupaciones), avisamos
    if ($e->getCode() == '23000') {
        header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=usuario_con_historial");
    } else {
        error_log("Error en borrar_usuario_permanente.php: " . $e->getMessage());
        header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=db_error");
    }
    exit();
}
?>
