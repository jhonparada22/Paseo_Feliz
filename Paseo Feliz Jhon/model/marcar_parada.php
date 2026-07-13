<?php
/**
 * marcar_parada.php
 * El paseador marca manualmente una parada como completada (botón "Marcar
 * completada" del panel de paradas del mapa).
 *
 * Usa la MISMA semántica de confirmación que marcar_paseo_dia.php:
 *   - parada de recogida -> escribe hora_recogida (la mascota está con el paseador)
 *   - parada de entrega  -> exige recogida confirmada y escribe hora_entrega
 *   - parada de paseo    -> punto de zona sin cliente: solo se marca completada
 * Antes este endpoint solo ponía id_estado=3 sin timestamps de negocio, con lo
 * que el dashboard del cliente no reflejaba la fase y el paseo no contaba
 * como usado; eran dos máquinas de estado distintas para la misma parada.
 *
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
if ($accion !== 'completar') responder(false, [], 'Acción no soportada.');

// Verificar que la parada pertenece a una ruta de ESTE paseador
$stmt = $conn->prepare(
    "SELECT rp.id_parada, rp.id_estado, rp.tipo, rp.id_usuario_cliente, rp.id_ruta,
            rp.id_pedido, rp.hora_recogida, rp.hora_entrega, rp.hora_cancelacion,
            mu.nombre_mascota
     FROM ruta_paradas rp
     JOIN rutas r ON r.id_ruta = rp.id_ruta
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = rp.id_mascota
     WHERE rp.id_parada = ? AND r.id_paseador = ?"
);
$stmt->bind_param("ii", $idParada, $idPaseador);
$stmt->execute();
$parada = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$parada) responder(false, [], 'Parada no encontrada o no pertenece a tus rutas.');
if ($parada['hora_cancelacion']) responder(false, [], 'Esta parada fue cancelada.');

$idRuta    = (int)$parada['id_ruta'];
$idPedido  = (int)$parada['id_pedido'];
$idCliente = (int)$parada['id_usuario_cliente'];
$mascota   = $parada['nombre_mascota'] ?: 'tu mascota';

if ($parada['tipo'] === 'recogida') {
    if ($parada['hora_recogida']) responder(false, [], 'Esta recogida ya fue confirmada.');

    $u = $conn->prepare(
        "UPDATE ruta_paradas
         SET hora_recogida = NOW(), id_estado = 3,
             hora_llegada = COALESCE(hora_llegada, NOW()), hora_completado = NOW()
         WHERE id_parada = ? AND hora_recogida IS NULL AND hora_cancelacion IS NULL"
    );
    $u->bind_param("i", $idParada);
    $u->execute();
    $ok = $u->affected_rows > 0;
    $u->close();
    if (!$ok) responder(false, [], 'No se pudo confirmar la recogida.');

    if ($idCliente) {
        crearNotificacionInterna($conn, $idCliente, $idRuta,
            'llegada_parada', "El paseador recogió a $mascota. ¡El paseo está por comenzar!");
    }
    responder(true, ['estado' => 'recogido'], "$mascota marcado como recogido.");

} elseif ($parada['tipo'] === 'entrega') {
    if ($parada['hora_entrega']) responder(false, [], 'Esta entrega ya fue confirmada.');

    // La entrega exige que la recogida de ESTE pedido ya esté confirmada
    // (mismo patrón EXISTS que marcar_paseo_dia.php)
    $u = $conn->prepare(
        "UPDATE ruta_paradas rp
         JOIN rutas r ON r.id_ruta = rp.id_ruta
         SET rp.hora_entrega = NOW(), rp.id_estado = 3,
             rp.hora_llegada = COALESCE(rp.hora_llegada, NOW()), rp.hora_completado = NOW()
         WHERE rp.id_parada = ? AND rp.hora_entrega IS NULL AND rp.hora_cancelacion IS NULL
           AND EXISTS (
             SELECT 1 FROM ruta_paradas rp2
             WHERE rp2.id_ruta = rp.id_ruta AND rp2.id_pedido = rp.id_pedido
               AND rp2.tipo = 'recogida' AND rp2.hora_recogida IS NOT NULL
           )"
    );
    $u->bind_param("i", $idParada);
    $u->execute();
    $ok = $u->affected_rows > 0;
    $u->close();
    if (!$ok) responder(false, [], 'No se puede entregar: primero confirma la recogida de esta mascota.');

    finalizarRutaSiCompleta($conn, $idRuta);

    if ($idCliente) {
        crearNotificacionInterna($conn, $idCliente, $idRuta,
            'sistema', "$mascota fue entregado. ¡Gracias por confiar en Paseo Feliz!");
    }
    responder(true, ['estado' => 'entregado'], "$mascota marcado como entregado.");

} else {
    // Parada de zona de paseo (sin cliente): solo se marca completada
    $u = $conn->prepare(
        "UPDATE ruta_paradas
         SET id_estado = 3, hora_llegada = COALESCE(hora_llegada, NOW()), hora_completado = NOW()
         WHERE id_parada = ?"
    );
    $u->bind_param("i", $idParada);
    $u->execute();
    $u->close();

    finalizarRutaSiCompleta($conn, $idRuta);

    responder(true, ['estado' => 'completado'], 'Parada marcada como completada.');
}
?>
