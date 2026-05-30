<?php
$host = "sql300.byethost17.com";   // MySQL hostname
$user = "b17_41964877";           // MySQL username
$pass = "12PARAda12";      // clave real de VistaPanel
$db   = "b17_41964877_registro_paseofeliz"; // El nombre EXACTO de tu base de datos

$conn = new mysqli($host, $user, $pass, $db);

// Comprobación de la conexión
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Error de conexión a la base de datos: " . $conn->connect_error]));
}

// Codificación en UTF-8 para soportar eñes y acentos de los usuarios
$conn->set_charset("utf8");
?>