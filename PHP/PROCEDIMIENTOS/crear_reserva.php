<?php
session_start();
require_once __DIR__ . '/../CONEXION/conexion.php';

if (!isset($_SESSION['loginok'])) {
    header("Location: ../PUBLIC/login.php");
    exit();
}

$username = $_SESSION['username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_reserva'])) {
    try {
        $conn->beginTransaction();

        $mesa_id = $_POST['mesa_id'];
        $cliente_nombre = $_POST['cliente_nombre'];
        $cliente_telefono = $_POST['cliente_telefono'];
        $notas = $_POST['notas'];
        $comensales = $_POST['comensales'];
        
        $fecha_inicio = $_POST['fecha_reserva_final']; 
        $fecha_fin = $_POST['fecha_fin_reserva_final'];
        
        // 1. Insertar Reserva
        $stmt = $conn->prepare("
            INSERT INTO reservas (fecha_reserva, fecha_fin_reserva, num_comensales, nombre_cliente, telefono_cliente, notas, estado)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$fecha_inicio, $fecha_fin, $comensales, $cliente_nombre, $cliente_telefono, $notas]);
        $id_reserva = $conn->lastInsertId();

        // 2. Insertar Ocupación Futura
        $stmt_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_user->execute([$username]);
        $id_camarero = $stmt_user->fetchColumn();

        $stmt_ocup = $conn->prepare("
            INSERT INTO ocupaciones (id_camarero, id_mesa, inicio_ocupacion, final_ocupacion, num_comensales, id_reserva)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_ocup->execute([$id_camarero, $mesa_id, $fecha_inicio, $fecha_fin, $comensales, $id_reserva]);

        $conn->commit();
        header("Location: ../PUBLIC/reservas.php?success=" . urlencode("Reserva confirmada para " . $cliente_nombre));
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: ../PUBLIC/reservas.php?error=" . urlencode("Error al crear reserva: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: ../PUBLIC/reservas.php");
    exit();
}
?>
