<?php
    // Inicia o reanuda la sesión
    session_start();
    // Requiere el archivo de conexión
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

    // --- Procesar formulario POST ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $id_mesa = $_POST['id_mesa'] ?? null;
        $num_comensales = isset($_POST['num_comensales']) ? (int)$_POST['num_comensales'] : 0;

        if (!$id_mesa) {
            header("Location: ../PUBLIC/index.php?error=no_id");
            exit();
        }

        // Obtener info de la mesa para redirigir correctamente a la sala
        $stmt_sala = $conn->prepare("SELECT id_sala FROM mesas WHERE id = ?");
        $stmt_sala->execute([$id_mesa]);
        $mesa_info = $stmt_sala->fetch(PDO::FETCH_ASSOC);
        $id_sala = $mesa_info['id_sala'] ?? 1;

        if ($num_comensales > 0) {
            // Inicia una transacción
            $conn->beginTransaction();
            try {
                // 1. Actualiza la mesa: estado=2 (ocupada), asignado_por=ID del camarero
                $update = $conn->prepare("UPDATE mesas SET estado=2, asignado_por=? WHERE id=?");
                $update->execute([$id_camarero, $id_mesa]);

                // 2. Inserta un nuevo registro en la tabla 'ocupaciones'
                $insert = $conn->prepare("
                    INSERT INTO ocupaciones (id_camarero, id_mesa, inicio_ocupacion, num_comensales)
                    VALUES (?, ?, NOW(), ?)
                ");
                $insert->execute([$id_camarero, $id_mesa, $num_comensales]);

                // Si todo va bien, confirma los cambios
                $conn->commit();
                
                // Redirige de vuelta a la sala
                header("Location: ../PUBLIC/sala.php?id=" . $id_sala); 
                exit();
            } catch (Exception $e) {
                // Si algo falla, revierte los cambios
                $conn->rollBack();
                // Podríamos redirigir con error
                header("Location: ../PUBLIC/asignar_mesa.php?id_mesa=$id_mesa&error=db_error");
                exit();
            }
        } else {
             header("Location: ../PUBLIC/asignar_mesa.php?id_mesa=$id_mesa&error=invalid_comensales");
             exit();
        }
    } else {
        // Si no es POST, redirigir
        header("Location: ../PUBLIC/index.php");
        exit();
    }
?>