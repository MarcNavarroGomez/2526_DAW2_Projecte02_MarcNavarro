<?php
session_start();
require_once __DIR__ . '/../CONEXION/conexion.php';

if (!isset($_SESSION['loginok'])) {
    header("Location: ../PUBLIC/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_reserva'])) {
    try {
        $id_res = $_POST['id_reserva'];
        $nombre = $_POST['nombre_cliente'];
        $telefono = $_POST['telefono_cliente'];
        $notas = $_POST['notas'];

        $stmt = $conn->prepare("UPDATE reservas SET nombre_cliente = ?, telefono_cliente = ?, notas = ? WHERE id = ?");
        $stmt->execute([$nombre, $telefono, $notas, $id_res]);
        
        header("Location: ../PUBLIC/reservas.php?success=" . urlencode("Reserva de $nombre actualizada correctamente."));
        exit();
    } catch (PDOException $e) {
        header("Location: ../PUBLIC/reservas.php?error=" . urlencode("Error al actualizar: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: ../PUBLIC/reservas.php");
    exit();
}
?>
