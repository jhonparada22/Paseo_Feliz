<?php
// Pagina_principal/php/asignar_rol.php
include 'conexion.php';
session_start();

// Solo el Admin Master puede ejecutar este cambio
$admin_master_cc = "1092XXXXXX"; 

if ($_SESSION['usuario_id'] != $admin_master_cc) {
    echo json_encode(['status' => 'error', 'message' => 'No tienes permiso']);
    exit;
}

if (isset($_POST['cc'])) {
    $cc = $_POST['cc'];
    $sql = "UPDATE usuarios SET id_rol = 2 WHERE cc = ?";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$cc])) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos']);
    }
}
?>