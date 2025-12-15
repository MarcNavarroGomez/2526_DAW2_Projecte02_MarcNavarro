<?php
session_start();
require_once __DIR__ . '/../CONEXION/conexion.php';

if (!isset($_SESSION['loginok'])) {
    header("Location: ../PUBLIC/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre_cliente'];
    $telefono = $_POST['telefono_cliente'];
    $notas = $_POST['notas'];
    $id_reserva = intval($_POST['id_reserva']);

    try {
        $stmt = $conn->prepare("UPDATE reservas SET nombre_cliente = ?, telefono_cliente = ?, notas = ? WHERE id = ?");
        $stmt->execute([$nombre, $telefono, $notas, $id_reserva]);
        header("Location: ../PUBLIC/reservas.php?success=Reserva actualizada correctamente");
        exit();
    } catch (PDOException $e) {
         header("Location: ../PUBLIC/editar_reserva.php?id=$id_reserva&error=" . urlencode($e->getMessage()));
         exit();
    }
} else {
    header("Location: ../PUBLIC/reservas.php");
    exit();
}
?>
