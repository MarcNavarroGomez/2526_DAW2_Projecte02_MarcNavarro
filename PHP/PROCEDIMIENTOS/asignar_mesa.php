<?php
// Inicia o reanuda la sesión para mantener el estado del usuario
session_start();
// Requiere el archivo de conexión a la base de datos (ruta relativa ajustada)
require_once './../CONEXION/conexion.php';

// --- Verificación de sesión ---
// Comprueba si la sesión 'loginok' existe y es verdadera
if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
    // Si no es válida, redirige al login
    header("Location: ../PUBLIC/login.php");
    exit(); // Detiene la ejecución del script
}

// Obtener nombre de usuario de la sesión
$username = $_SESSION['username'] ?? null;
if (!$username) {
    // Seguridad adicional: si no hay usuario, destruir y salir
    session_destroy(); 
    header("Location: ../PUBLIC/login.php"); 
    exit();
}

// --- Consultar ID del camarero ---
// Obtiene el ID numérico del usuario actual basándose en su nombre de usuario
// Esto es necesario para registrar quién realiza la asignación (asignado_por y ocupaciones)
$stmt_camarero = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
$stmt_camarero->execute([':username' => $username]);
$camarero = $stmt_camarero->fetch(PDO::FETCH_ASSOC);

if (!$camarero) {
    // Si no se encuentra el usuario en BD (inconsistencia), cerrar sesión
    session_destroy(); header("Location: ../PUBLIC/login.php"); exit();
}
$id_camarero = $camarero['id'];

// --- Procesar formulario POST ---
// Este archivo solo debe procesar datos enviados por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recuperar datos del formulario
    $id_mesa = $_POST['id_mesa'] ?? null;
    $num_comensales = isset($_POST['num_comensales']) ? (int)$_POST['num_comensales'] : 0;

    // Validación básica del ID de la mesa
    if (!$id_mesa) {
        header("Location: ../PUBLIC/index.php?error=no_id");
        exit();
    }

    // Obtener info de la mesa para saber a qué sala redirigir al finalizar
    $stmt_sala = $conn->prepare("SELECT id_sala FROM mesas WHERE id = ?");
    $stmt_sala->execute([$id_mesa]);
    $mesa_info = $stmt_sala->fetch(PDO::FETCH_ASSOC);
    $id_sala = $mesa_info['id_sala'] ?? 1; // Fallback a sala 1 si falla

    // Validar número de comensales positivo
    if ($num_comensales > 0) {
        // Inicia una transacción para asegurar integridad referencial
        // Si falla el update o el insert, no se guarda nada
        $conn->beginTransaction();
        try {
            // 1. Actualiza el estado de la mesa a 'Ocupada' (2)
            // y registra el ID del camarero que la asignó
            $update = $conn->prepare("UPDATE mesas SET estado=2, asignado_por=? WHERE id=?");
            $update->execute([$id_camarero, $id_mesa]);

            // 2. Insertar un nuevo registro histórico en la tabla 'ocupaciones'
            // Esto guarda quién ocupó la mesa, cuándo, y cuántos comensales
            $insert = $conn->prepare("
                INSERT INTO ocupaciones (id_camarero, id_mesa, inicio_ocupacion, num_comensales)
                VALUES (?, ?, NOW(), ?)
            ");
            $insert->execute([$id_camarero, $id_mesa, $num_comensales]);

            // Si ambas operaciones son exitosas, confirma la transacción
            $conn->commit();
            
            // Redirige de vuelta a la sala correspondiente
            header("Location: ../PUBLIC/sala.php?id=" . $id_sala); 
            exit();
        } catch (Exception $e) {
            // Si algo falla, revierte todos los cambios pendientes
            $conn->rollBack();
            // Redirige al formulario con un mensaje de error
            header("Location: ../PUBLIC/asignar_mesa.php?id_mesa=$id_mesa&error=db_error");
            exit();
        }
    } else {
         // Si el número de comensales no es válido
         header("Location: ../PUBLIC/asignar_mesa.php?id_mesa=$id_mesa&error=invalid_comensales");
         exit();
    }
} else {
    // Si se intenta acceder directamente sin POST, redirigir al inicio
    header("Location: ../PUBLIC/index.php");
    exit();
}
?>