<?php
/**
 * obtener_mascotas.php
 * Devuelve las mascotas registradas con su dueño.
 * GET                    -> todas las mascotas (para el panel admin)
 * GET ?id_usuario=8      -> solo las mascotas de ese usuario
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include_once 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$idUsuario = intval($_GET['id_usuario'] ?? 0);

if ($idUsuario) {
    $stmt = $conn->prepare(
        "SELECT id_mascota, id_usuario, nombre_mascota, raza, edad, avatar_mascota,
                biografia_canina, enfermedades_discapacidades
         FROM mascota_usuario WHERE id_usuario = ? ORDER BY nombre_mascota ASC"
    );
    $stmt->bind_param("i", $idUsuario);
} else {
    $stmt = $conn->prepare(
        "SELECT id_mascota, id_usuario, nombre_mascota, raza, edad, avatar_mascota,
                biografia_canina, enfermedades_discapacidades
         FROM mascota_usuario ORDER BY nombre_mascota ASC"
    );
}
$stmt->execute();
$res = $stmt->get_result();

$mascotas = [];
while ($row = $res->fetch_assoc()) {
    $mascotas[] = [
        'id_mascota' => (int)$row['id_mascota'],
        'id_usuario' => (int)$row['id_usuario'],
        'nombre'     => $row['nombre_mascota'],
        'raza'       => $row['raza'] ?? '',
        'edad'       => $row['edad'] !== null ? (int)$row['edad'] : null,
        'avatar'     => !empty($row['avatar_mascota'])
            ? 'assets/' . ltrim(preg_replace('#^(\.\./)*assets/#', '', $row['avatar_mascota']), '/')
            : '',
        'biografia'  => $row['biografia_canina'] ?? '',
        'notas'      => $row['enfermedades_discapacidades'] ?? '',
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'mascotas' => $mascotas]);
$conn->close();
?>
