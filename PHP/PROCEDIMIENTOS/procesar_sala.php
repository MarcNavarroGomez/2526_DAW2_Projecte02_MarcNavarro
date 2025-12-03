<?php
// session_start();

// // Si el usuario no está logueado, fuera
// if (!isset($_SESSION['loginok']) || $_SESSION['loginok'] !== true) {
//     header("Location: ../PUBLIC/login.php");
//     exit();
// }

// // Validar que venga el ID de la sala
// if (!isset($_POST['sala']) || !is_numeric($_POST['sala'])) {
//     header("Location: ../PUBLIC/consultar_mesas.php?error=no_sala");
//     exit();
// }

// $id_sala = intval($_POST['sala']);

// // Definimos las rutas de las 9 salas
// $paginas_salas = [
//     1 => '../PUBLIC/sala1.php',
//     2 => '../PUBLIC/sala2.php',
//     3 => '../PUBLIC/sala3.php',
//     4 => '../PUBLIC/sala4.php',
//     5 => '../PUBLIC/sala5.php',
//     6 => '../PUBLIC/sala6.php',
//     7 => '../PUBLIC/sala7.php',
//     8 => '../PUBLIC/sala8.php',
//     9 => '../PUBLIC/sala9.php'
// ];

// // Si la sala existe en el array, redirige
// if (array_key_exists($id_sala, $paginas_salas)) {
//     header("Location: " . $paginas_salas[$id_sala]);
//     exit();
// } else {
//     // Si no existe, vuelve al formulario
//     header("Location: ../PUBLIC/consultar_mesas.php?error=sala_invalida");
//     exit();
// }
?>
