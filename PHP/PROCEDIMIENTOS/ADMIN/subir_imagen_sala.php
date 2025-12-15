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

$id_sala = intval($_POST['id_sala']);

// Validaciones iniciales
if ($id_sala <= 0) {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=id_invalido");
    exit();
}

// Verificar que se haya enviado el archivo correctamente
if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=archivo_no_subido");
    exit();
}

// Recoger metadatos del archivo
$archivo = $_FILES['imagen'];
$nombre_archivo = $archivo['name'];
$tmp_name = $archivo['tmp_name'];
$tamano = $archivo['size'];

// Validar extensión del archivo
$extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
$extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];

if (!in_array($extension, $extensiones_permitidas)) {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=formato_invalido");
    exit();
}

// Validar tamaño (máximo 5MB)
if ($tamano > 5 * 1024 * 1024) {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=archivo_muy_grande");
    exit();
}

try {
    // 1. Obtener imagen anterior para borrarla (limpieza)
    $stmt_old = $conn->prepare("SELECT imagen FROM salas WHERE id = :id_sala");
    $stmt_old->execute([':id_sala' => $id_sala]);
    $sala_old = $stmt_old->fetch(PDO::FETCH_ASSOC);

    // 2. Generar nuevo nombre único y ruta
    $nombre_unico = 'sala_' . $id_sala . '_' . time() . '.' . $extension;
    $ruta_destino = __DIR__ . '/../../../img/salas/' . $nombre_unico;
    
    // Asegurar directorio existe
    $directorio = __DIR__ . '/../../../img/salas/';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }
    
    // 3. Mover archivo
    if (!move_uploaded_file($tmp_name, $ruta_destino)) {
        throw new Exception("Error al mover el archivo al servidor");
    }

    // 4. Actualizar base de datos
    $stmt = $conn->prepare("UPDATE salas SET imagen = :imagen WHERE id = :id_sala");
    $stmt->execute([
        ':imagen' => $nombre_unico,
        ':id_sala' => $id_sala
    ]);
    
    // 5. Borrar imagen antigua si existe y es diferente
    if ($sala_old && $sala_old['imagen'] && file_exists($directorio . $sala_old['imagen'])) {
        unlink($directorio . $sala_old['imagen']);
    }

    header("Location: ../../PUBLIC/ADMIN/salas.php?success=imagen_subida");
    exit();
    
} catch (PDOException $e) {
    error_log("Error en subir_imagen_sala.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=db_error");
    exit();
    
} catch (Exception $e) {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
