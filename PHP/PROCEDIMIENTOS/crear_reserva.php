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

// Obtener usuario actual
$username = $_SESSION['username'] ?? '';

// --- Lógica de creación de reserva (Solo POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_reserva'])) {
    try {
        // Iniciar transacción: Crear reserva y ocupación debe ser atómico
        $conn->beginTransaction();

        // Recoger datos del formulario
        $mesa_id = $_POST['mesa_id'];
        $cliente_nombre = $_POST['cliente_nombre'];
        $cliente_telefono = $_POST['cliente_telefono'];
        $notas = $_POST['notas'];
        $comensales = $_POST['comensales'];
        
        // Fechas calculadas en el frontend (reservas.php)
        $fecha_inicio = $_POST['fecha_reserva_final']; 
        $fecha_fin = $_POST['fecha_fin_reserva_final'];
        
        // 1. Insertar Registro en tabla 'reservas' (Datos del cliente)
        $stmt = $conn->prepare("
            INSERT INTO reservas (fecha_reserva, fecha_fin_reserva, num_comensales, nombre_cliente, telefono_cliente, notas, estado)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$fecha_inicio, $fecha_fin, $comensales, $cliente_nombre, $cliente_telefono, $notas]);
        // Guardar el ID de la reserva generada
        $id_reserva = $conn->lastInsertId();

        // 2. Insertar Ocupación Futura en tabla 'ocupaciones'
        // Obtener ID del usuario actual para asociarlo
        $stmt_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_user->execute([$username]);
        $id_camarero = $stmt_user->fetchColumn();

        // Ocupación futura vinculada a la reserva
        $stmt_ocup = $conn->prepare("
            INSERT INTO ocupaciones (id_camarero, id_mesa, inicio_ocupacion, final_ocupacion, num_comensales, id_reserva)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_ocup->execute([$id_camarero, $mesa_id, $fecha_inicio, $fecha_fin, $comensales, $id_reserva]);

        // Confirmar transacción
        $conn->commit();
        
        // Redirigir con éxito
        header("Location: ../PUBLIC/reservas.php?success=" . urlencode("Reserva confirmada para " . $cliente_nombre));
        exit();
        
    } catch (Exception $e) {
        // Error: Deshacer cambios
        $conn->rollBack();
        header("Location: ../PUBLIC/reservas.php?error=" . urlencode("Error al crear reserva: " . $e->getMessage()));
        exit();
    }
} else {
    // Si no es POST válido
    header("Location: ../PUBLIC/reservas.php");
    exit();
}
?>
