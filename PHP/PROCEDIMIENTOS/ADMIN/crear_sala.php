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
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=metodo_invalido");
    exit();
}

// Recoger y sanitizar datos
$nombre = trim($_POST['nombre']);
$num_mesas = intval($_POST['num_mesas']); // Campo informativo, no afecta directamente a la tabla mesas

// Validar nombre
if (empty($nombre)) {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=nombre_vacio");
    exit();
}

try {
    // Iniciar transacción (útil si hay operaciones complejas o múltiples inserciones, aquí es simple pero buena práctica)
    $conn->beginTransaction();
    
    // --- Lógica de Subida de Imagen ---
    $nombre_imagen = null;
    
    // Comprobar si se ha subido un archivo sin errores
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['imagen'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Validar extensión
        if (in_array($extension, $extensiones_permitidas)) {
            // Validar tamaño (Max 5MB)
            if ($archivo['size'] <= 5 * 1024 * 1024) {
                // Generar nombre único para evitar colisiones
                $nombre_imagen = 'sala_' . time() . '_' . uniqid() . '.' . $extension;
                // Ruta absoluta de destino
                $ruta_destino = __DIR__ . '/../../../img/salas/' . $nombre_imagen;
                
                // Asegurar que el directorio existe
                $directorio = __DIR__ . '/../../../img/salas/';
                if (!is_dir($directorio)) {
                    mkdir($directorio, 0755, true);
                }
                
                // Mover el archivo subido a la carpeta final
                if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                    $nombre_imagen = null; // Si falla la movida, no guardar referencia en BS
                }
            }
        }
    }
    
    // --- Inserción en Base de Datos ---
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
    
    // Redirección exitosa
    header("Location: ../../PUBLIC/ADMIN/salas.php?success=sala_creada");
    exit();
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en crear_sala.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=db_error");
    exit();
}
?>
