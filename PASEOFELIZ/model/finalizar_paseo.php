<?php
/**
 * finalizar_paseo.php
 * El paseador finaliza el paseo una vez completadas todas las paradas.
 * POST JSON: { "id_ruta": 5 }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

$idPaseador = obtenerIdPaseadorSesion($conn);
$data = leerJsonBody();

$idRuta = intval($data['id_ruta'] ?? 0);
if (!$idRuta) responder(false, [], 'id_ruta requerido.');

// Verificar que la ruta pertenece a ESTE paseador
$stmt = $conn->prepare("SELECT id_estado FROM rutas WHERE id_ruta = ? AND id_paseador = ?");
$stmt->bind_param("ii", $idRuta, $idPaseador);
$stmt->execute();
$ruta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ruta) responder(false, [], 'Ruta no encontrada o no te pertenece.');
if (in_array((int)$ruta['id_estado'], [4, 5])) {
    responder(true, [], 'El paseo ya estaba finalizado.');
}

$u = $conn->prepare("UPDATE rutas SET id_estado = 4, fecha_fin_real = NOW() WHERE id_ruta = ?");
$u->bind_param("i", $idRuta);
$ok = $u->execute();
$u->close();

responder($ok, [], $ok ? 'Paseo finalizado correctamente.' : 'No se pudo finalizar el paseo.');
?>
