<?php
/**
 * iniciar_paseo.php
 * El paseador inicia, reanuda o pausa el paseo de una ruta.
 * POST JSON: { "id_ruta": 5 }              -> iniciar o reanudar
 * POST JSON: { "id_ruta": 5, "accion": "pausar" } -> pausar
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

$idPaseador = obtenerIdPaseadorSesion($conn);
$data = leerJsonBody();

$idRuta = intval($data['id_ruta'] ?? 0);
$accion = $data['accion'] ?? 'iniciar';

if (!$idRuta) responder(false, [], 'id_ruta requerido.');

// Verificar que la ruta pertenece a ESTE paseador
$stmt = $conn->prepare("SELECT id_estado FROM rutas WHERE id_ruta = ? AND id_paseador = ?");
$stmt->bind_param("ii", $idRuta, $idPaseador);
$stmt->execute();
$ruta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ruta) responder(false, [], 'Ruta no encontrada o no te pertenece.');

$estadoActual = (int)$ruta['id_estado'];

if ($accion === 'pausar') {
    if ($estadoActual !== 2) responder(false, [], 'Solo se puede pausar una ruta en curso.');
    $u = $conn->prepare("UPDATE rutas SET id_estado = 3 WHERE id_ruta = ?");
    $u->bind_param("i", $idRuta);
    $u->execute();
    $u->close();
    responder(true, [], 'Paseo pausado.');
} else {
    if ($estadoActual === 1) {
        // Primer inicio
        $u = $conn->prepare("UPDATE rutas SET id_estado = 2, fecha_inicio_real = NOW() WHERE id_ruta = ?");
    } elseif ($estadoActual === 3) {
        // Reanudar tras pausa (no se toca fecha_inicio_real)
        $u = $conn->prepare("UPDATE rutas SET id_estado = 2 WHERE id_ruta = ?");
    } elseif ($estadoActual === 2) {
        // Ya está en curso: idempotente
        responder(true, [], 'El paseo ya estaba en curso.');
        exit;
    } else {
        responder(false, [], 'La ruta ya fue finalizada o cancelada.');
        exit;
    }
    $u->bind_param("i", $idRuta);
    $u->execute();
    $u->close();
    responder(true, [], 'Paseo iniciado.');
}
?>
