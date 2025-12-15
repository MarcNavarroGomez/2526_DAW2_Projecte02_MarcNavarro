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
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=metodo_invalido");
    exit();
}

// Recoger y sanitizar datos
$nombre = trim($_POST['nombre']);
$num_mesas = intval($_POST['num_mesas']);

// Validaciones
if (empty($nombre)) {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=nombre_vacio");
    exit();
}

try {
    $conn->beginTransaction();
    
    // Manejar la imagen si se subió
    $nombre_imagen = null;
    
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['imagen'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Validar tipo de archivo
        if (in_array($extension, $extensiones_permitidas)) {
            // Validar tamaño (máximo 5MB)
            if ($archivo['size'] <= 5 * 1024 * 1024) {
                // Generar nombre único
                $nombre_imagen = 'sala_' . time() . '_' . uniqid() . '.' . $extension;
                $ruta_destino = __DIR__ . '/../../../img/salas/' . $nombre_imagen;
                
                // Crear directorio si no existe
                $directorio = __DIR__ . '/../../../img/salas/';
                if (!is_dir($directorio)) {
                    mkdir($directorio, 0755, true);
                }
                
                // Mover archivo
                if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                    $nombre_imagen = null; // Si falla, continuar sin imagen
                }
            }
        }
    }
    
    // Insertar sala
    $stmt = $conn->prepare("
        INSERT INTO salas (nombre, num_mesas, imagen) 
        VALUES (:nombre, :num_mesas, :imagen)
    ");
    
    $stmt->execute([
        ':nombre' => $nombre,
        ':num_mesas' => $num_mesas,
        ':imagen' => $nombre_imagen
    ]);
    
    $conn->commit();
    
    header("Location: ../../PUBLIC/ADMIN/salas.php?success=sala_creada");
    exit();
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error en crear_sala.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=db_error");
    exit();
}
?>
