<?php
// Archivo: php/obtener_mensajes.php
include 'conexion.php';

$emisor = $_GET['emisor'];
$receptor = $_GET['receptor'];

// Traemos los mensajes donde ambos participan, ordenados por fecha
$sql = "SELECT * FROM mensajes 
        WHERE (id_emisor = ? AND id_receptor = ?) 
        OR (id_emisor = ? AND id_receptor = ?) 
        ORDER BY fecha_envio ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$emisor, $receptor, $receptor, $emisor]);
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($mensajes);
?>