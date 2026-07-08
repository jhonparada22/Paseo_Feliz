<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$id_paseador  = isset($data['id_paseador'])  ? (int)$data['id_paseador']        : 0;
$zona         = isset($data['zona_trabajo'])  ? trim($data['zona_trabajo'])       : '';
$hora_inicio  = isset($data['hora_inicio'])   ? trim($data['hora_inicio'])        : null;
$hora_fin     = isset($data['hora_fin'])      ? trim($data['hora_fin'])           : null;
// puntuacion ya no se edita a mano: se recalcula automáticamente en
// calificar_paseo.php a partir de las calificaciones de los clientes.

if (!$id_paseador) {
    echo json_encode(['success' => false, 'message' => 'ID de paseador requerido']);
    exit;
}

// Construir la consulta dinámicamente según qué campos se envían
$campos = [];
$tipos  = '';
$vals   = [];

if ($zona !== '') {
    $campos[] = 'zona_trabajo = ?';
    $tipos   .= 's';
    $vals[]   = $zona;
}
if ($hora_inicio !== null) {
    $campos[] = 'hora_inicio = ?';
    $tipos   .= 's';
    $vals[]   = $hora_inicio ?: null;
}
if ($hora_fin !== null) {
    $campos[] = 'hora_fin = ?';
    $tipos   .= 's';
    $vals[]   = $hora_fin ?: null;
}
if (empty($campos)) {
    echo json_encode(['success' => false, 'message' => 'Nada que actualizar']);
    exit;
}

$tipos .= 'i';
$vals[] = $id_paseador;

$sql  = "UPDATE paseadores SET " . implode(', ', $campos) . " WHERE id_paseador = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($tipos, ...$vals);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Información actualizada']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
