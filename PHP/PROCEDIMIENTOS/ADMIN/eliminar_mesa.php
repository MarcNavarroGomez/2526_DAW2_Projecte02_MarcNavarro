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
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=metodo_invalido");
    exit();
}

// Recoger datos
$id_mesa = intval($_POST['id_mesa']);

// Validar ID
if ($id_mesa <= 0) {
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=id_invalido");
    exit();
}

try {
    // Iniciar transacción (Importante para mantener integridad)
    $conn->beginTransaction();
    
    // 1. Verificar mesas ocupadas
    // No queremos eliminar una mesa que actualmente tiene gente comiendo (ocupaciones abiertas)
    $stmt_check = $conn->prepare("
        SELECT COUNT(*) as activas 
        FROM ocupaciones 
        WHERE id_mesa = :id_mesa AND final_ocupacion IS NULL
    ");
    $stmt_check->execute([':id_mesa' => $id_mesa]);
    $result = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($result['activas'] > 0) {
        throw new Exception("No se puede eliminar: la mesa tiene ocupaciones activas");
    }
    
    // 2. Eliminar referencias históricas (Opcional, pero recomendado si no hay ON DELETE CASCADE)
    // En este caso, si eliminamos la mesa, el historial de ocupaciones quedaría huérfano de mesa.
    // Podríamos mantenerlo poniendo id_mesa a NULL o borrarlo.
    // Dado que el requerimiento no especifica, asumiremos que si la BBDD tiene FK con restricción, fallará.
    // Si tiene Cascade, se borrarán solas.
    // Intencionalmente PROBAMOS borrar la mesa. Si falla por FK, el catch lo atrapará.
    
    // Nota: Para mantener integridad histórica, lo ideal sería NO borrar la mesa, sino marcarla como "activa=0".
    // Pero como el enunciado pide borrar:
    $stmt = $conn->prepare("DELETE FROM mesas WHERE id = :id_mesa");
    $stmt->execute([':id_mesa' => $id_mesa]);
    
    $conn->commit();
    
    header("Location: ../../PUBLIC/ADMIN/mesas.php?success=mesa_eliminada");
    exit();
    
} catch (PDOException $e) {
    $conn->rollBack();
    // Error 23000 suele ser violación de integridad referencial (FK)
    if ($e->getCode() == '23000') {
         header("Location: ../../PUBLIC/ADMIN/mesas.php?error=mesa_con_historial");
    } else {
        error_log("Error en eliminar_mesa.php: " . $e->getMessage());
        header("Location: ../../PUBLIC/ADMIN/mesas.php?error=db_error");
    }
    exit();
    
} catch (Exception $e) {
    $conn->rollBack();
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
