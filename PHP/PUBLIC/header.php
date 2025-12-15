<?php
// Comprobar la hora actual del servidor para generar un saludo dinámico
// date('H') devuelve la hora en formato 00-23
$hora = date('H');

// Lógica condicional para definir la variable $saludo
if ($hora >= 6 && $hora < 12) {
    // Si es entre las 6:00 AM y las 11:59 AM
    $saludo = "Buenos días"; 
} elseif ($hora >= 12 && $hora < 20) {
    // Si es entre las 12:00 PM y las 7:59 PM
    $saludo = "Buenas tardes"; 
} else {
    // Para el resto de horas (Noche/Madrugada)
    $saludo = "Buenas noches"; 
}
?>

<!-- Barra de navegación principal (Header) -->
<nav class="main-header">
    <!-- Sección izquierda con el Logo -->
    <div class="header-logo">
        <!-- El logo lleva al index (ruta relativa adaptada según el contexto de uso) -->
        <!-- Nota: Esta ruta asume que header.php se incluye desde dentro de una carpeta hija de PUBLIC (como SALAS) o se ajustará manual -->
        <a href="../index.php">
            <img src="../../../img/basic_logo_blanco.png" alt="Logo GMS">
        </a>
        <div class="logo-text">
            <span class="gms-title">CASA GMS</span>
        </div>
    </div>

    <!-- Sección central con el saludo personalizado -->
    <div class="header-greeting">
        <!-- Imprime el saludo calculado en PHP -->
        <?= $saludo ?> 
        <!-- Imprime el nombre de usuario (se asume que $username está definido en el archivo padre que incluye este header) -->
        <span class="username-tag"><?= $username ?></span>
    </div>

    <!-- Sección derecha con el menú de navegación -->
    <div class="header-menu">
        <a href="../index.php" class="nav-link">
            <i class="fa-solid fa-house"></i> Inicio
        </a>
        <a href="../historico.php" class="nav-link">
            <i class="fa-solid fa-chart-bar"></i> Histórico
        </a>
        
    </div>

    <!-- Botón de Logout -->
    <!-- Se usa un formulario POST por seguridad para cerrar sesión -->
    <form method="post" action="../../PROCEDIMIENTOS/logout.php">
        <button type="submit" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
        </button>
    </form>
</nav>