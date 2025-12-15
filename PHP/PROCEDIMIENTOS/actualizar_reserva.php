<?php
// Inicia sesión
session_start();
// Conexión a BD
require_once __DIR__ . '/../CONEXION/conexion.php';

// --- Verificación de sesión ---
if (!isset($_SESSION['loginok'])) {
    header("Location: ../PUBLIC/login.php");
    exit();
}

// --- Lógica de actualización (Solo POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_reserva'])) {
    try {
        // Recoger datos
        $id_res = $_POST['id_reserva'];
        $nombre = $_POST['nombre_cliente'];
        $telefono = $_POST['telefono_cliente'];
        $notas = $_POST['notas'];

        // Consulta de actualización simple
        // No requiere transacción compleja ya que solo toca una tabla
        // (Aunque podría incluirse en transacción si fuera crítico)
        $stmt = $conn->prepare("UPDATE reservas SET nombre_cliente = ?, telefono_cliente = ?, notas = ? WHERE id = ?");
        $stmt->execute([$nombre, $telefono, $notas, $id_res]);
        
        // Redirigir con éxito
        header("Location: ../PUBLIC/reservas.php?success=" . urlencode("Reserva de $nombre actualizada correctamente."));
        exit();
    } catch (PDOException $e) {
        // Error
        header("Location: ../PUBLIC/reservas.php?error=" . urlencode("Error al actualizar: " . $e->getMessage()));
        exit();
    }
} else {
    // Si no es POST válido
    header("Location: ../PUBLIC/reservas.php");
    exit();
}
?>
