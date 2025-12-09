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
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=metodo_invalido");
    exit();
}

$id_mesa = intval($_POST['id_mesa']);

if ($id_mesa <= 0) {
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=id_invalido");
    exit();
}

try {
    $conn->beginTransaction();
    
    // Verificar si la mesa tiene ocupaciones activas
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
    
    // Eliminar mesa
    $stmt = $conn->prepare("DELETE FROM mesas WHERE id = :id_mesa");
    $stmt->execute([':id_mesa' => $id_mesa]);
    
    $conn->commit();
    
    header("Location: ../../PUBLIC/ADMIN/mesas.php?success=mesa_eliminada");
    exit();
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error en eliminar_mesa.php: " . $e->getMessage());
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=db_error");
    exit();
    
} catch (Exception $e) {
    $conn->rollBack();
    header("Location: ../../PUBLIC/ADMIN/mesas.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
