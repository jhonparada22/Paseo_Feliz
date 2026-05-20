<?php
// PASEOFELIZ/model/php pagina principal/asignar_rol.php
include 'conexion.php';
session_start();

$admin_master_cc = "1092XXXXXX";

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] != $admin_master_cc) {
    echo json_encode(['status' => 'error', 'message' => 'No tienes permiso']);
    exit;
}

if (isset($_POST['cc'])) {
    $cc = $_POST['cc'];
    
    try {
        $sql = "UPDATE usuarios SET id_rol = 2 WHERE cc = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$cc])) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el rol']);
        }
    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error en Postgres: ' . $e->getMessage()]);
    }
}
?>