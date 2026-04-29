<?php
// Archivo: php/enviar_mensaje.php
include 'conexion.php'; // Tu archivo de conexión a la BD

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // En un sistema real, el emisor vendría de la $_SESSION['cc']
    $emisor = $_POST['id_emisor']; 
    $receptor = $_POST['id_receptor'];
    $contenido = $_POST['contenido'];

    if (!empty($contenido)) {
        $sql = "INSERT INTO mensajes (id_emisor, id_receptor, contenido) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$emisor, $receptor, $contenido])) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    }
}
?>