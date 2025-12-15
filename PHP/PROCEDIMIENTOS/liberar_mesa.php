<?php
// Inicia o reanuda la sesión del usuario
session_start();
// Requiere el archivo de conexión a la base de datos
require_once './../CONEXION/conexion.php';

// --- Verificación de sesión ---
if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
    header("Location: ../PUBLIC/login.php");
    exit();
}

$username = $_SESSION['username'] ?? null;
if (!$username) {
    session_destroy(); header("Location: ../PUBLIC/login.php"); exit();
}

// --- Consultar ID del camarero ---
$stmt_camarero = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
$stmt_camarero->execute([':username' => $username]);
$camarero = $stmt_camarero->fetch(PDO::FETCH_ASSOC);

if (!$camarero) {
    session_destroy(); header("Location: ../PUBLIC/login.php"); exit();
}

$id_camarero = $camarero['id'];
$rol = $_SESSION['rol'] ?? 1; 


// --- Lógica de Liberación (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mesa = $_POST['id_mesa'] ?? null;
    
    if (!$id_mesa) {
        header("Location: ../PUBLIC/index.php");
        exit();
    }

    // Obtener info mesa
    $stmt_mesa = $conn->prepare("SELECT * FROM mesas WHERE id = ?");
    $stmt_mesa->execute([$id_mesa]);
    $mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

    if (!$mesa) {
        header("Location: ../PUBLIC/index.php");
        exit();
    }
    
    $id_sala = $mesa['id_sala'];

    // Inicia una transacción
    $conn->beginTransaction();
    try {
        // --- CONTROL DE PERMISOS (Servidor) ---
        // Comprueba si el camarero que intenta liberar NO es quien la asignó Y NO es admin (rol 2)
        if ($mesa['asignado_por'] != $id_camarero && $rol != 2) {
             // Redirige con error
             $conn->rollBack();
             header("Location: ../PUBLIC/liberar_mesa.php?id_mesa=$id_mesa&error=no_permission");
             exit();
        } else {
            // 1. Actualiza la mesa: estado=1 (libre), asignado_por=NULL
            $conn->prepare("UPDATE mesas SET estado=1, asignado_por=NULL WHERE id=?")->execute([$id_mesa]);
            
            // 2. Actualiza la ocupación
            $conn->prepare("
                UPDATE ocupaciones SET final_ocupacion=NOW()
                WHERE id_mesa=? AND final_ocupacion IS NULL
                ORDER BY inicio_ocupacion DESC LIMIT 1
            ")->execute([$id_mesa]);

            $conn->commit();
            header("Location: ../PUBLIC/sala.php?id=" . $id_sala); 
            exit();
        }
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: ../PUBLIC/index.php");
    exit();
}
?>