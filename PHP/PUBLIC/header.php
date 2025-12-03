<?php
// Determina la hora actual del servidor (formato 24h)
$hora = date('H');

// Comprueba la franja horaria para definir un saludo personalizado
if ($hora >= 6 && $hora < 12) {
    $saludo = "Buenos días"; // Mañana
} elseif ($hora >= 12 && $hora < 20) {
    $saludo = "Buenas tardes"; // Tarde
} else {
    $saludo = "Buenas noches"; // Noche
}
?>

<nav class="main-header">
    <div class="header-logo">
        <a href="../index.php">
            <img src="../../../img/basic_logo_blanco.png" alt="Logo GMS">
        </a>
        <div class="logo-text">
            <span class="gms-title">CASA GMS</span>
           
        </div>
    </div>

    <div class="header-greeting">
        <?= $saludo ?> 
        <span class="username-tag"><?= $username ?></span>
    </div>

    <div class="header-menu">
        <a href="../index.php" class="nav-link">
            <i class="fa-solid fa-house"></i> Inicio
        </a>
        <a href="../historico.php" class="nav-link">
            <i class="fa-solid fa-chart-bar"></i> Histórico
        </a>
        
    </div>

    <form method="post" action="../../PROCEDIMIENTOS/logout.php">
        <button type="submit" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
        </button>
    </form>
</nav>