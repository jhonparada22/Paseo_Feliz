<?php
$host = "sql113.byethost17.com";   // MySQL hostname
$user = "b17_42313426";           // MySQL username
$pass = "1092941961";      // clave real de VistaPanel
$db   = "b17_42313426_paseofeliztest"; // El nombre EXACTO de tu base de datos

$conn = new mysqli($host, $user, $pass, $db);

// Comprobación de la conexión
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Error de conexión a la base de datos: " . $conn->connect_error]));
}

// Codificación en UTF-8 para soportar eñes y acentos de los usuarios
$conn->set_charset("utf8");
?>