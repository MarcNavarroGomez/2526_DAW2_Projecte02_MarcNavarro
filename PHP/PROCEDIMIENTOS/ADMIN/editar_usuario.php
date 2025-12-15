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

// Recoger datos del formulario
$id_usuario = intval($_POST['id_usuario']);
$nombre = trim($_POST['nombre']);
$apellido = trim($_POST['apellido']);
$email = trim($_POST['email']);
$rol = intval($_POST['rol']);

// --- Validaciones ---
if (empty($nombre)) {
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=nombre_vacio");
    exit();
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=email_invalido");
    exit();
}

try {
    // 1. Verificar que el usuario existe
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE id = :id");
    $stmt_check->execute([':id' => $id_usuario]);
    
    if (!$stmt_check->fetch()) {
        throw new Exception("El usuario no existe");
    }
    
    // 2. Actualizar el registro del usuario
    $stmt = $conn->prepare("
        UPDATE users 
        SET nombre = :nombre, 
            apellido = :apellido, 
            email = :email, 
            rol = :rol 
        WHERE id = :id
    ");
    
    $stmt->execute([
        ':nombre' => $nombre,
        ':apellido' => $apellido,
        ':email' => $email,
        ':rol' => $rol,
        ':id' => $id_usuario
    ]);
    
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?success=usuario_editado");
    exit();
    
} catch (PDOException $e) {
    error_log("Error en editar_usuario.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=db_error");
    exit();
    
} catch (Exception $e) {
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
