<?php
// Inicia sesión
session_start();
// Conexión
require_once __DIR__ . '/../CONEXION/conexion.php';

// --- Verificación de sesión ---
if (!isset($_SESSION['loginok'])) {
    header("Location: ../PUBLIC/login.php");
    exit();
}

// --- Lógica de eliminación (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reserva'])) {
    $id_reserva = intval($_POST['id_reserva']);

    try {
        // Iniciar Transacción
        // Es IMPORTANTE eliminar tanto la reserva como la ocupación futura vinculada
        $conn->beginTransaction();

        // 1. Eliminar ocupaciones futuras vinculadas a esta reserva
        // Las ocupaciones tienen FK a reserva, pero por seguridad las borramos explícitamente primero o dejamos que el ON DELETE Cascade actúe si existe (aquí lo hacemos manual)
        $stmt_ocup = $conn->prepare("DELETE FROM ocupaciones WHERE id_reserva = ?");
        $stmt_ocup->execute([$id_reserva]);

        // 2. Eliminar la reserva de la tabla principal
        $stmt_res = $conn->prepare("DELETE FROM reservas WHERE id = ?");
        $stmt_res->execute([$id_reserva]);

        // Confirmar cambios
        $conn->commit();

        // Redirigir
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
