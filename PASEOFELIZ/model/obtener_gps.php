<?php
/**
 * obtener_gps.php
 * El cliente consulta la posición GPS en vivo del paseador que tiene asignado hoy.
 * GET ?id_paseador=3
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarSesion();

$idPaseador = intval($_GET['id_paseador'] ?? 0);
if (!$idPaseador) responder(false, [], 'id_paseador requerido.');

$idUsuario = (int)$_SESSION['usuario_id'];
$hoy = date('Y-m-d');

// Solo puede ver el GPS de un paseador con el que tiene un paseo activo hoy
$stmt = $conn->prepare(
    "SELECT 1 FROM rutas r
     JOIN ruta_paradas rp ON rp.id_ruta = r.id_ruta
     WHERE r.id_paseador = ? AND rp.id_usuario_cliente = ?
       AND r.fecha_paseo = ? AND r.id_estado IN (1,2,3)
     LIMIT 1"
);
$stmt->bind_param("iis", $idPaseador, $idUsuario, $hoy);
$stmt->execute();
$autorizado = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$autorizado) responder(false, [], 'No autorizado para ver este paseador.');

$stmt = $conn->prepare(
    "SELECT lat, lng, velocidad, precision_m, fecha_actualizacion
     FROM gps_paseadores WHERE id_paseador = ?"
);
$stmt->bind_param("i", $idPaseador);
$stmt->execute();
$gps = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$gps) responder(true, ['gps' => null], 'Aún no hay posición GPS registrada.');

responder(true, ['gps' => [
    'lat'        => (float)$gps['lat'],
    'lng'        => (float)$gps['lng'],
    'velocidad'  => (float)$gps['velocidad'],
    'precision'  => (float)$gps['precision_m'],
    'actualizado'=> $gps['fecha_actualizacion'],
]]);
?>
