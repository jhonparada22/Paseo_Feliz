<?php
/**
 * marcar_parada.php
 * El paseador marca manualmente una parada como completada (botón "Marcar completada").
 * POST JSON: { "id_parada": 5, "accion": "completar" }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

$idPaseador = obtenerIdPaseadorSesion($conn);
$data = leerJsonBody();

$idParada = intval($data['id_parada'] ?? 0);
$accion   = $data['accion'] ?? 'completar';

if (!$idParada) responder(false, [], 'id_parada requerido.');

// Verificar que la parada pertenece a una ruta de ESTE paseador
$stmt = $conn->prepare(
    "SELECT rp.id_parada, rp.id_estado, rp.tipo, rp.id_usuario_cliente, rp.id_ruta
     FROM ruta_paradas rp
     JOIN rutas r ON r.id_ruta = rp.id_ruta
     WHERE rp.id_parada = ? AND r.id_paseador = ?"
);
$stmt->bind_param("ii", $idParada, $idPaseador);
$stmt->execute();
$parada = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$parada) responder(false, [], 'Parada no encontrada o no pertenece a tus rutas.');

if ($accion === 'completar') {
    // Si aún no se había marcado "llegada", se registra también hora_llegada ahora
    if ((int)$parada['id_estado'] === 1) {
        $u = $conn->prepare("UPDATE ruta_paradas SET id_estado = 3, hora_llegada = NOW(), hora_completado = NOW() WHERE id_parada = ?");
    } else {
        $u = $conn->prepare("UPDATE ruta_paradas SET id_estado = 3, hora_completado = NOW() WHERE id_parada = ?");
    }
    $u->bind_param("i", $idParada);
    $u->execute();
    $u->close();

    if ($parada['id_usuario_cliente']) {
        $msg = $parada['tipo'] === 'entrega'
            ? 'El paseador está próximo a entregar a tu mascota.'
            : 'Se completó la parada de tu mascota.';
        $tipoNotif = $parada['tipo'] === 'entrega' ? 'proximidad_entrega' : 'llegada_parada';
        crearNotificacionInterna($conn, $parada['id_usuario_cliente'], $parada['id_ruta'], $tipoNotif, $msg);
    }

    responder(true, [], 'Parada marcada como completada.');
} else {
    responder(false, [], 'Acción no soportada.');
}
?>
