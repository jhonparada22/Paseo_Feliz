<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_usuario = intval($_GET['id_usuario'] ?? 0);

if (!$id_usuario) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario requerido']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id_mascota, nombre_mascota, avatar_mascota
    FROM mascota_usuario
    WHERE id_usuario = ?
    ORDER BY nombre_mascota ASC
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$res = $stmt->get_result();

$mascotas = [];
while ($row = $res->fetch_assoc()) {
    $mascotas[] = [
        'id_mascota'     => (int)$row['id_mascota'],
        'nombre_mascota' => $row['nombre_mascota'],
        'avatar_url'     => $row['avatar_mascota'] ?? '',
    ];
}
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'mascotas' => $mascotas]);
?>
