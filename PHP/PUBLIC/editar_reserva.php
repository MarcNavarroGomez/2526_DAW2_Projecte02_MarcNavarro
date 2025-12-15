<?php
session_start();
require_once __DIR__ . '/../CONEXION/conexion.php';

if (!isset($_SESSION['loginok'])) {
    header("Location: login.php");
    exit();
}

$id_reserva = isset($_GET['id']) ? intval($_GET['id']) : 0;
$mensaje = $_GET['error'] ?? '';

// Obtener datos actuales
try {
    $stmt = $conn->prepare("
        SELECT r.*, m.nombre as mesa_nombre, s.nombre as sala_nombre 
        FROM reservas r
        JOIN ocupaciones o ON r.id = o.id_reserva
        JOIN mesas m ON o.id_mesa = m.id
        JOIN salas s ON m.id_sala = s.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id_reserva]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        header("Location: reservas.php?error=Reserva no encontrada");
        exit();
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Reserva - Casa GMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/editar_reserva.css">
</head>
<body>
    <nav class="main-header">
        <div class="header-logo">
            <img src="../../img/basic_logo_blanco.png" alt="Logo GMS">
            <div class="logo-text"><span class="gms-title">EDITAR RESERVA</span></div>
        </div>
    </nav>

    <div class="edit-container">
        <h2 style="margin-bottom: 20px; color: #2c3e50;">Editar Datos de Reserva</h2>
        
        <?php if ($mensaje): ?>
            <div style="background: #f8dbdb; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <div class="info-group">
            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])) ?></p>
            <p><strong>Mesa:</strong> <?= htmlspecialchars($reserva['mesa_nombre']) ?> (<?= htmlspecialchars($reserva['sala_nombre']) ?>)</p>
            <p><strong>Comensales:</strong> <?= $reserva['num_comensales'] ?></p>
            <small style="color: #7f8c8d;">* Para cambiar fecha o mesa, cancele y cree una nueva reserva.</small>
        </div>

        <form method="POST" action="../PROCEDIMIENTOS/procesar_editar_reserva.php">
            <input type="hidden" name="id_reserva" value="<?= $reserva['id'] ?>">
            
            <div class="form-group">
                <label>Nombre Cliente</label>
                <input type="text" name="nombre_cliente" class="form-control" value="<?= htmlspecialchars($reserva['nombre_cliente']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono_cliente" class="form-control" value="<?= htmlspecialchars($reserva['telefono_cliente']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Notas</label>
                <textarea name="notas" class="form-control" rows="4"><?= htmlspecialchars($reserva['notas']) ?></textarea>
            </div>

            <button type="submit" class="btn-save"><i class="fa-solid fa-save"></i> Guardar Cambios</button>
            <a href="reservas.php" class="btn-back">Volver al listado</a>
        </form>
    </div>
</body>
</html>
