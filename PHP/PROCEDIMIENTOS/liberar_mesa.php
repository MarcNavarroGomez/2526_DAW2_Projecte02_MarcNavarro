<?php
// Inicia o reanuda la sesión del usuario
session_start();
// Requiere el archivo de conexión a la base de datos
require_once './../CONEXION/conexion.php';

// --- Verificación de sesión ---
// Comprueba si el usuario tiene una sesión activa válida
if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
    header("Location: ../PUBLIC/login.php");
    exit();
}

// Verifica el usuario en sesión
$username = $_SESSION['username'] ?? null;
if (!$username) {
    session_destroy(); header("Location: ../PUBLIC/login.php"); exit();
}

// --- Consultar ID del camarero actual ---
// Necesario para verificar si tiene permisos de liberación
$stmt_camarero = $conn->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
$stmt_camarero->execute([':username' => $username]);
$camarero = $stmt_camarero->fetch(PDO::FETCH_ASSOC);

if (!$camarero) {
    session_destroy(); header("Location: ../PUBLIC/login.php"); exit();
}

$id_camarero = $camarero['id'];
$rol = $_SESSION['rol'] ?? 1; // Obtener rol (1=Camarero, 2=Admin)


// --- Lógica de Liberación (Solo admite POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_mesa = $_POST['id_mesa'] ?? null;
    
    // Validar ID de mesa
    if (!$id_mesa) {
        header("Location: ../PUBLIC/index.php");
        exit();
    }

    // Obtener información actual de la mesa antes de modificar nada
    $stmt_mesa = $conn->prepare("SELECT * FROM mesas WHERE id = ?");
    $stmt_mesa->execute([$id_mesa]);
    $mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

    if (!$mesa) {
        header("Location: ../PUBLIC/index.php");
        exit();
    }
    
    $id_sala = $mesa['id_sala'];

    // Inicia una transacción para asegurar consistencia
    $conn->beginTransaction();
    try {
        // --- CONTROL DE PERMISOS (Validación Servidor) ---
        // Regla de Negocio: Solo puede liberar la mesa el mismo camarero que la asignó
        // EXCEPCIÓN: Los administradores (rol 2) pueden liberar cualquier mesa
        if ($mesa['asignado_por'] != $id_camarero && $rol != 2) {
             // Si no cumple permisos, deshacer (rollback) y volver con error
             $conn->rollBack();
             header("Location: ../PUBLIC/liberar_mesa.php?id_mesa=$id_mesa&error=no_permission");
             exit();
        } else {
            // 1. Actualiza la mesa: Pone estado en 1 (Libre) y limpia el campo asignado_por
            $conn->prepare("UPDATE mesas SET estado=1, asignado_por=NULL WHERE id=?")->execute([$id_mesa]);
            
            // 2. Actualiza el registro histórico de ocupación
            // Busca la ocupación activa (donde final_ocupacion es NULL) más reciente y la cierra con NOW()
            $conn->prepare("
                UPDATE ocupaciones SET final_ocupacion=NOW()
                WHERE id_mesa=? AND final_ocupacion IS NULL
                ORDER BY inicio_ocupacion DESC LIMIT 1
            ")->execute([$id_mesa]);

            // Confirma la transacción
            $conn->commit();
            
            // Redirige a la vista de la sala
            header("Location: ../PUBLIC/sala.php?id=" . $id_sala); 
            exit();
        }
    } catch (Exception $e) {
        // En caso de error técnico, rollback y mensale
        $conn->rollBack();
        die("Error: " . $e->getMessage());
    }
} else {
    // Si no es petición POST, redirigir
    header("Location: ../PUBLIC/index.php");
    exit();
}
?>