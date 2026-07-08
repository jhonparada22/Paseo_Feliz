<?php
/**
 * marcar_paseo_dia.php
 * (PASEADOR) Cambia el estado de un paseo (segmentos Individual/Grupal del
 * mapa del paseador), operando directamente sobre ruta_paradas — ya no usa
 * la tabla paseos_dia (retirada, ver Fase 3 del plan de consolidación de
 * "ruta del día").
 *
 * POST JSON, acciones soportadas:
 *   { "accion": "recogido",  "id_pedido": 5 }
 *   { "accion": "pendiente", "id_pedido": 5 }              <- deshacer
 *   { "accion": "entregar",  "id_pedido": 5 }              <- solo si ya está recogido
 *   { "accion": "cancelar",  "id_pedido": 5, "motivo": "Está lloviendo" }
 *   { "accion": "iniciar_grupal", "ids_pedidos": [5,8,9] } <- no-op de
 *     compatibilidad: con el modelo de timestamps, "en curso" ya se deriva
 *     automáticamente de hora_recogida sin hora_entrega.
 *   { "accion": "entregar_grupal", "ids_pedidos": [5,8,9] } <- solo si TODAS
 *     las recogidas del grupo seleccionado ya están confirmadas.
 *
 * Gating (server-side, no solo cosmético en el frontend):
 *   recogido           requiere hora_recogida NULL y hora_cancelacion NULL
 *   pendiente (deshacer) requiere hora_entrega NULL
 *   entregar            requiere hora_recogida NOT NULL y hora_entrega/hora_cancelacion NULL
 *   cancelar            requiere hora_entrega NULL
 *   entregar_grupal     requiere que TODAS las recogidas del grupo estén confirmadas
 *
 * Efectos secundarios:
 *   - cancelar: notificación interna al cliente con el motivo, cancela
 *     tanto la parada de recogida como la de entrega de esa mascota.
 *   - recogido: marca la parada de recogida de hoy como completada.
 *   - entregar: notificación interna al cliente confirmando la entrega.
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

$idPaseador = obtenerIdPaseadorSesion($conn);

$data   = leerJsonBody();
$accion = $data['accion'] ?? '';
$hoy    = date('Y-m-d');

// ── Acción de compatibilidad: "iniciar paseo grupal" ya no cambia nada,
// el estado "en curso" se deriva solo de hora_recogida/hora_entrega.
if ($accion === 'iniciar_grupal') {
    responder(true, ['marcados' => 0], 'Los perros recogidos ya se muestran en curso automáticamente.');
}

// ── Acción por lote: entregar a todo el grupo (gating: todas las recogidas
// del grupo seleccionado deben estar confirmadas antes de habilitarla) ────
if ($accion === 'entregar_grupal') {
    $ids = array_values(array_filter(array_map('intval', $data['ids_pedidos'] ?? [])));
    if (!$ids) responder(false, [], 'Selecciona al menos un perro del grupo.');

    $idRuta = obtenerRutaActivaHoy($conn, $idPaseador, $hoy);
    if (!$idRuta) responder(false, [], 'No tienes una ruta activa hoy.');

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN hora_recogida IS NOT NULL THEN 1 ELSE 0 END) AS recogidos
         FROM ruta_paradas
         WHERE id_ruta = ? AND tipo = 'recogida' AND id_pedido IN ($placeholders)
           AND hora_cancelacion IS NULL"
    );
    $tipos = 'i' . str_repeat('i', count($ids));
    $params = array_merge([$idRuta], $ids);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $chk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$chk['total'] || (int)$chk['recogidos'] < (int)$chk['total']) {
        responder(false, [], 'No todos los perros del grupo están recogidos todavía.');
    }

    $stmt = $conn->prepare(
        "UPDATE ruta_paradas
         SET hora_entrega = NOW(), id_estado = 3, hora_completado = NOW()
         WHERE id_ruta = ? AND tipo = 'entrega' AND id_pedido IN ($placeholders)
           AND hora_entrega IS NULL AND hora_cancelacion IS NULL"
    );
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $marcados = $stmt->affected_rows;
    $stmt->close();

    responder(true, ['marcados' => $marcados], "Grupo entregado: $marcados perro(s).");
}

// ── Acciones individuales ─────────────────────────────────────────────
$idPedido = intval($data['id_pedido'] ?? 0);
if (!$idPedido) responder(false, [], 'id_pedido requerido.');

$idRuta = obtenerRutaActivaHoy($conn, $idPaseador, $hoy);
if (!$idRuta) responder(false, [], 'No tienes una ruta activa hoy.');

// El pedido debe pertenecer a la ruta activa de ESTE paseador
$stmt = $conn->prepare(
    "SELECT rp.id_usuario_cliente, rp.id_mascota, mu.nombre_mascota
     FROM ruta_paradas rp
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = rp.id_mascota
     WHERE rp.id_ruta = ? AND rp.id_pedido = ? LIMIT 1"
);
$stmt->bind_param("ii", $idRuta, $idPedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) responder(false, [], 'Ese paseo no está en tu ruta de hoy.');

$idCliente = (int)$pedido['id_usuario_cliente'];
$mascota   = $pedido['nombre_mascota'] ?: 'tu mascota';

if ($accion === 'recogido') {
    $stmt = $conn->prepare(
        "UPDATE ruta_paradas
         SET hora_recogida = NOW(), id_estado = 3,
             hora_llegada = COALESCE(hora_llegada, NOW()), hora_completado = NOW()
         WHERE id_ruta = ? AND id_pedido = ? AND tipo = 'recogida'
           AND hora_recogida IS NULL AND hora_cancelacion IS NULL"
    );
    $stmt->bind_param("ii", $idRuta, $idPedido);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$ok) responder(false, [], 'No se pudo marcar como recogido (ya estaba recogido o cancelado).');

    crearNotificacionInterna($conn, $idCliente, $idRuta,
        'llegada_parada', "El paseador recogió a $mascota. ¡El paseo está por comenzar!");

    responder(true, ['estado' => 'recogido'], "$mascota marcado como recogido.");

} elseif ($accion === 'entregar') {
    $stmt = $conn->prepare(
        "UPDATE ruta_paradas rp
         JOIN rutas r ON r.id_ruta = rp.id_ruta
         SET rp.hora_entrega = NOW(), rp.id_estado = 3,
             rp.hora_llegada = COALESCE(rp.hora_llegada, NOW()), rp.hora_completado = NOW()
         WHERE rp.id_ruta = ? AND rp.id_pedido = ? AND rp.tipo = 'entrega'
           AND rp.hora_entrega IS NULL AND rp.hora_cancelacion IS NULL
           AND EXISTS (
             SELECT 1 FROM ruta_paradas rp2
             WHERE rp2.id_ruta = rp.id_ruta AND rp2.id_pedido = rp.id_pedido
               AND rp2.tipo = 'recogida' AND rp2.hora_recogida IS NOT NULL
           )"
    );
    $stmt->bind_param("ii", $idRuta, $idPedido);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$ok) responder(false, [], 'No se puede entregar: falta confirmar la recogida, o ya fue entregado/cancelado.');

    crearNotificacionInterna($conn, $idCliente, $idRuta,
        'sistema', "$mascota fue entregado. ¡Gracias por confiar en Paseo Feliz!");

    responder(true, ['estado' => 'entregado'], "$mascota marcado como entregado.");

} elseif ($accion === 'pendiente') {
    // Deshacer (por si el paseador se equivocó) — no se puede si ya se entregó
    $stmt = $conn->prepare(
        "UPDATE ruta_paradas
         SET hora_recogida = NULL, id_estado = 1, hora_llegada = NULL, hora_completado = NULL
         WHERE id_ruta = ? AND id_pedido = ? AND tipo = 'recogida' AND hora_entrega IS NULL"
    );
    $stmt->bind_param("ii", $idRuta, $idPedido);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$ok) responder(false, [], 'No se puede deshacer: el paseo ya fue entregado.');

    responder(true, ['estado' => 'pendiente'], 'Paseo devuelto a pendiente.');

} elseif ($accion === 'cancelar') {
    $motivo = trim(substr($data['motivo'] ?? '', 0, 120));
    if ($motivo === '') {
        responder(false, [], 'Debes indicar el motivo de la cancelación.');
    }

    // Cancela ambas paradas del pedido (recogida y entrega), solo si aún no se entregó
    $stmt = $conn->prepare(
        "UPDATE ruta_paradas
         SET hora_cancelacion = NOW(), motivo_cancelacion = ?, id_estado = 4
         WHERE id_ruta = ? AND id_pedido = ? AND tipo IN ('recogida','entrega')
           AND hora_entrega IS NULL AND hora_cancelacion IS NULL"
    );
    $stmt->bind_param("sii", $motivo, $idRuta, $idPedido);
    $stmt->execute();
    $afectadas = $stmt->affected_rows;
    $stmt->close();

    if (!$afectadas) responder(false, [], 'No se puede cancelar: el paseo ya fue entregado o ya estaba cancelado.');

    crearNotificacionInterna($conn, $idCliente, $idRuta,
        'sistema', "El paseo de hoy de $mascota fue cancelado. Motivo: $motivo. El paseador puede darte más detalles por el chat.");

    responder(true, ['estado' => 'cancelado', 'motivo' => $motivo], 'Paseo cancelado y cliente notificado.');

} else {
    responder(false, [], 'Acción no soportada.');
}
?>
