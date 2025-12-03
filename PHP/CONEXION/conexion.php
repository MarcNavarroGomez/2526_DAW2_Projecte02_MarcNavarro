<?php 
 $servername = "localhost:3306"; // Nombre del servidor 
 $dbusername = "root"; // Nombre de usuario 
 $dbpassword = "MarcNavarroGomez7."; // Contraseña 
 $dbname = "restaurante_db"; // Nombre de la base de datos 
 
 // ---------------------------------------------------------------------- 
 // PDO: ESTABLECER CONEXIÓN CONTROLANDO ERRORES 
 // En el bloque "try" ponemos el código que puede generar la excepción 
 // En el bloque "catch" se maneja la excepció creada en el bloque "try". 
 // La variable $e es una variable que contiene información sobre la excepción 
 // capturada, como el tipo de excepción y el mensaje de error. 
 // Exception: Esto indica el tipo de excepción que estás capturando. 

 try { 
     // Se crea una instancia de la clase PDO para establecer la conexión 
     $conn = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword); 
  
     // Se establece el modo de errores de PDO para lanzar excepciones en lugar de advertencias 
     $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
      
 } catch (PDOException $e) { 

    // Se captura la excepción y mostrar el mensaje de error 
    echo "Error en la conexión a la base de datos: " . $e->getMessage(); 
    die(); 

 }