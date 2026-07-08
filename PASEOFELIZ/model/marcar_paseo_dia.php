<?php
/**
 * marcar_paseo_dia.php
 * (PASEADOR) Cambia el estado diario de un paseo (segmentos Individual /
 * Grupal del mapa del paseador).
 *
 * POST JSON, acciones soportadas:
 *   { "accion": "recogido",  "id_pedido": 5 }
 *   { "accion": "pendiente", "id_pedido": 5 }              <- deshacer
 *   { "accion": "cancelar",  "id_pedido": 5, "motivo": "Está lloviendo" }
 *   { "accion": "iniciar_grupal", "ids_pedidos": [5,8,9] } <- recogidos -> en_paseo
 *
 * Efectos secundarios:
 *   - cancelar: notificación interna al cliente con el motivo, y si ya
 *     existe la ruta de hoy, sus paradas de esa mascota pasan a "omitida".
 *   - recogido: si existe la parada de recogida de hoy, se marca completada.
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

$idPaseador = obtenerIdPaseadorSesion($conn);
asegurarTablaPaseosDia($conn);

$data   = leerJsonBody();
$accion = $data['accion'] ?? '';
$hoy    = date('Y-m-d');

// ── Acción por lote: iniciar paseo grupal ─────────────────────────────
if ($accion === 'iniciar_grupal') {
    $ids = array_values(array_filter(array_map('intval', $data['ids_pedidos'] ?? [])));
    if (!$ids) responder(false, [], 'Selecciona al menos un perro recogido.');

    $marcados = 0;
    $stmt = $conn->prepare(
        "UPDATE paseos_dia SET estado = 'en_paseo'
         WHERE fecha = ? AND id_pedido = ? AND id_paseador = ? AND estado = 'recogido'"
    );
    foreach ($ids as $idPedido) {
        $stmt->bind_param("sii", $hoy, $idPedido, $idPaseador);
        $stmt->execute();
        $marcados += $stmt->affected_rows;
    }
    $stmt->close();

    if (!$marcados) responder(false, [], 'Ninguno de los perros seleccionados está marcado como recogido.');
    responder(true, ['marcados' => $marcados], "Paseo grupal iniciado con $marcados perro(s).");
}

// ── Acciones individuales ─────────────────────────────────────────────
$idPedido = intval($data['id_pedido'] ?? 0);
if (!$idPedido) responder(false, [], 'id_pedido requerido.');

// El pedido debe estar en el cronograma de ESTE paseador para hoy
$diaSemana = (int)date('N');
$stmt = $conn->prepare(
    "SELECT pp.id_usuario, pp.id_mascota, mu.nombre_mascota
     FROM cronograma_paseos c
     JOIN pedidos_paseo pp ON pp.id_pedido = c.id_pedido
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = pp.id_mascota
     WHERE c.id_pedido = ? AND c.id_paseador = ? AND c.dia_semana = ?"
);
$stmt->bind_param("iii", $idPedido, $idPaseador, $diaSemana);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) responder(false, [], 'Ese paseo no está en tu cronograma de hoy.');

$idCliente = (int)$pedido['id_usuario'];
$idMascota = (int)$pedido['id_mascota'];
$mascota   = $pedido['nombre_mascota'] ?: 'tu mascota';

// Ruta de hoy de este paseador (si ya fue generada), para sincronizar paradas
function rutaDeHoy($conn, $idPaseador, $hoy) {
    $stmt = $conn->prepare(
        "SELECT id_ruta FROM rutas
         WHERE id_paseador = ? AND fecha_paseo = ? AND id_estado IN (1,2,3)
         ORDER BY hora_inicio ASC LIMIT 1"
    );
    $stmt->bind_param("is", $idPaseador, $hoy);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ? (int)$r['id_ruta'] : null;
}

if ($accion === 'recogido') {
    $stmt = $conn->prepare(
        "INSERT INTO paseos_dia (fecha, id_pedido, id_paseador, estado, hora_recogida)
         VALUES (?, ?, ?, 'recogido', NOW())
         ON DUPLICATE KEY UPDATE estado = 'recogido', hora_recogida = NOW(),
                                 motivo_cancelacion = NULL, hora_cancelacion = NULL"
    );
    $stmt->bind_param("sii", $hoy, $idPedido, $idPaseador);
    $stmt->execute();
    $stmt->close();

    // Sincronizar la parada de recogida de la ruta de hoy (si existe)
    $idRuta = rutaDeHoy($conn, $idPaseador, $hoy);
    if ($idRuta && $idMascota) {
        $u = $conn->prepare(
            "UPDATE ruta_paradas
             SET id_estado = 3, hora_llegada = COALESCE(hora_llegada, NOW()), hora_completado = NOW()
             WHERE id_ruta = ? AND id_mascota = ? AND tipo = 'recogida' AND id_estado IN (1,2)"
        );
        $u->bind_param("ii", $idRuta, $idMascota);
        $u->execute();
        $u->close();
    }

    crearNotificacionInterna($conn, $idCliente, $idRuta,
        'llegada_parada', "El paseador recogió a $mascota. ¡El paseo está por comenzar!");

    responder(true, ['estado' => 'recogido'], "$mascota marcado como recogido.");

} elseif ($accion === 'pendiente') {
    // Deshacer (por si el paseador se equivocó)
    $stmt = $conn->prepare(
        "INSERT INTO paseos_dia (fecha, id_pedido, id_paseador, estado)
         VALUES (?, ?, ?, 'pendiente')
         ON DUPLICATE KEY UPDATE estado = 'pendiente', hora_recogida = NULL,
                                 motivo_cancelacion = NULL, hora_cancelacion = NULL"
    );
    $stmt->bind_param("sii", $hoy, $idPedido, $idPaseador);
    $stmt->execute();
    $stmt->close();

    responder(true, ['estado' => 'pendiente'], 'Paseo devuelto a pendiente.');

} elseif ($accion === 'cancelar') {
    $motivo = trim(substr($data['motivo'] ?? '', 0, 120));
    if ($motivo === '') {
        responder(false, [], 'Debes indicar el motivo de la cancelación.');
    }

    $stmt = $conn->prepare(
        "INSERT INTO paseos_dia (fecha, id_pedido, id_paseador, estado, motivo_cancelacion, hora_cancelacion)
         VALUES (?, ?, ?, 'cancelado', ?, NOW())
         ON DUPLICATE KEY UPDATE estado = 'cancelado', motivo_cancelacion = VALUES(motivo_cancelacion),
                                 hora_cancelacion = NOW(), hora_recogida = NULL"
    );
    $stmt->bind_param("siis", $hoy, $idPedido, $idPaseador, $motivo);
    $stmt->execute();
    $stmt->close();

    // Si la ruta de hoy ya existe, omitir las paradas de esa mascota
    $idRuta = rutaDeHoy($conn, $idPaseador, $hoy);
    if ($idRuta && $idMascota) {
        $u = $conn->prepare(
            "UPDATE ruta_paradas SET id_estado = 4
             WHERE id_ruta = ? AND id_mascota = ? AND id_estado IN (1,2)"
        );
        $u->bind_param("ii", $idRuta, $idMascota);
        $u->execute();
        $u->close();
    }

    crearNotificacionInterna($conn, $idCliente, $idRuta,
        'sistema', "El paseo de hoy de $mascota fue cancelado. Motivo: $motivo. El paseador puede darte más detalles por el chat.");

    responder(true, ['estado' => 'cancelado', 'motivo' => $motivo], 'Paseo cancelado y cliente notificado.');

} else {
    responder(false, [], 'Acción no soportada.');
}
?>
