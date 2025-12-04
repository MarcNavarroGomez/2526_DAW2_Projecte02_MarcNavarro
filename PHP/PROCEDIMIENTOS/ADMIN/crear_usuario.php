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

// Recoger y sanitizar datos
$username = trim($_POST['username']);
$nombre = trim($_POST['nombre']);
$apellido = trim($_POST['apellido']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$rol = intval($_POST['rol']);

// Validaciones
if (empty($username) || empty($nombre) || empty($password)) {
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=campos_vacios");
    exit();
}

if (strlen($password) < 5) {
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=password_corta");
    exit();
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=email_invalido");
    exit();
}

try {
    // Verificar que el username no exista
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = :username");
    $stmt_check->execute([':username' => $username]);
    
    if ($stmt_check->fetch()) {
        throw new Exception("El nombre de usuario ya existe");
    }
    
    // Hashear la contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insertar usuario
    $stmt = $conn->prepare("
        INSERT INTO users 
        (username, nombre, apellido, email, password_hash, rol) 
        VALUES 
        (:username, :nombre, :apellido, :email, :password_hash, :rol)
    ");
    
    $stmt->execute([
        ':username' => $username,
        ':nombre' => $nombre,
        ':apellido' => $apellido,
        ':email' => $email,
        ':password_hash' => $password_hash,
        ':rol' => $rol
    ]);
    
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?success=usuario_creado");
    exit();
    
} catch (PDOException $e) {
    error_log("Error en crear_usuario.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=db_error");
    exit();
    
} catch (Exception $e) {
    header("Location: ../../PUBLIC/ADMIN/usuarios.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
