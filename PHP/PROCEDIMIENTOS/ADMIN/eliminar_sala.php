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

// Recoger datos
$id_sala = intval($_POST['id_sala']);

// Validar ID
if ($id_sala <= 0) {
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=id_invalido");
    exit();
}

try {
    $conn->beginTransaction();
    
    // 1. Verificar si la sala tiene mesas asociadas
    // No se permite borrar una sala si aún contiene mesas
    $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM mesas WHERE id_sala = :id_sala");
    $stmt_check->execute([':id_sala' => $id_sala]);
    $result = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] > 0) {
        throw new Exception("No se puede eliminar: la sala tiene mesas asociadas. Elimina primero las mesas.");
    }
    
    // 2. Obtener nombre de imagen para borrarla del servidor (limpieza)
    $stmt_img = $conn->prepare("SELECT imagen FROM salas WHERE id = :id_sala");
    $stmt_img->execute([':id_sala' => $id_sala]);
    $sala = $stmt_img->fetch(PDO::FETCH_ASSOC);
    
    // 3. Eliminar sala de la base de datos
    $stmt = $conn->prepare("DELETE FROM salas WHERE id = :id_sala");
    $stmt->execute([':id_sala' => $id_sala]);
    
    // 4. Borrar archivo de imagen físico si existe
    if ($sala && $sala['imagen']) {
        $ruta_imagen = __DIR__ . '/../../../img/salas/' . $sala['imagen'];
        if (file_exists($ruta_imagen)) {
            unlink($ruta_imagen);
        }
    }
    
    $conn->commit();
    
    header("Location: ../../PUBLIC/ADMIN/salas.php?success=sala_eliminada");
    exit();
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error en eliminar_sala.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=db_error");
    exit();
    
} catch (Exception $e) {
    $conn->rollBack();
    header("Location: ../../PUBLIC/ADMIN/salas.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
