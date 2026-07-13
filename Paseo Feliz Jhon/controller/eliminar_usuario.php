<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once '../model/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_usuario = intval($_POST['id_usuario'] ?? 0);

if (!$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']);
    exit;
}

// No permitir que el admin se elimine a sí mismo
if ($id_usuario === (int)$_SESSION['usuario_id']) {
    echo json_encode(['success' => false, 'message' => 'No puedes eliminarte a ti mismo']);
    exit;
}

// Eliminar de tablas relacionadas primero
$tablas = ['paseadores', 'admin', 'info_usuario', 'mascota_usuario'];
foreach ($tablas as $tabla) {
    $s = $conn->prepare("DELETE FROM $tabla WHERE id_usuario = ?");
    if ($s) { $s->bind_param("i", $id_usuario); $s->execute(); $s->close(); }
}

// Eliminar usuario principal
$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_usuario);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
