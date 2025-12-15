<?php
session_start();
require_once __DIR__ . '/../CONEXION/conexion.php';

    if (!isset($_SESSION['loginok'])) {
    header("Location: ../PUBLIC/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reserva'])) {
    $id_reserva = intval($_POST['id_reserva']);

    try {
        $conn->beginTransaction();

        // 1. Eliminar ocupaciones futuras vinculadas a esta reserva
        $stmt_ocup = $conn->prepare("DELETE FROM ocupaciones WHERE id_reserva = ?");
        $stmt_ocup->execute([$id_reserva]);

        // 2. Eliminar la reserva
        $stmt_res = $conn->prepare("DELETE FROM reservas WHERE id = ?");
        $stmt_res->execute([$id_reserva]);

        $conn->commit();
        header("Location: ../PUBLIC/reservas.php?success=Reserva eliminada correctamente");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: ../PUBLIC/reservas.php?error=Error al eliminar: " . $e->getMessage());
        exit();
    }
} else {
    header("Location: ../PUBLIC/reservas.php");
    exit();
}
?>
