<?php
/**
 * obtener_paseos_hoy_paseador.php
 * (PASEADOR) Lista los paseos de HOY del paseador logueado, separados por
 * modalidad (individual / grupal), con el estado de cada mascota derivado
 * de los timestamps de su parada en la ruta activa de hoy (ruta_paradas) —
 * ya no depende de la tabla paseos_dia (retirada).
 *
 * GET sin parámetros. Respuesta:
 * { success, fecha, paseos: [ { id_pedido, id_cliente, cliente, telefono,
 *   id_mascota, mascota, avatar_mascota, direccion, barrio, lat, lng,
 *   modalidad, franja, hora_inicio, duracion_min, comportamiento,
 *   estado, motivo_cancelacion } ] }
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

$idPaseador = obtenerIdPaseadorSesion($conn);
$hoy = date('Y-m-d');

$idRuta = obtenerRutaActivaHoy($conn, $idPaseador, $hoy);
if (!$idRuta) responder(true, ['fecha' => $hoy, 'paseos' => []]);

$stmt = $conn->prepare(
    "SELECT rp.id_pedido, rp.tipo, rp.id_usuario_cliente, rp.id_mascota,
            rp.hora_estimada, rp.hora_recogida, rp.hora_entrega, rp.hora_cancelacion, rp.motivo_cancelacion,
            pp.modalidad, pp.franja_horaria, pp.duracion_min, pp.comportamiento,
            pp.direccion, pp.barrio, pp.lat, pp.lng,
            u.nombre AS cliente, iu.telefono,
            mu.nombre_mascota, mu.avatar_mascota
     FROM ruta_paradas rp
     LEFT JOIN pedidos_paseo pp   ON pp.id_pedido = rp.id_pedido
     LEFT JOIN usuarios u         ON u.id = rp.id_usuario_cliente
     LEFT JOIN info_usuario iu    ON iu.id_usuario = rp.id_usuario_cliente
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = rp.id_mascota
     WHERE rp.id_ruta = ? AND rp.tipo IN ('recogida','entrega') AND rp.id_pedido IS NOT NULL
     ORDER BY rp.orden ASC"
);
$stmt->bind_param("i", $idRuta);
$stmt->execute();
$res = $stmt->get_result();

// Cada pedido trae 2 filas (recogida + entrega) -> se colapsan en 1 "paseo"
$porPedido = [];
while ($row = $res->fetch_assoc()) {
    $id = (int)$row['id_pedido'];
    if (!isset($porPedido[$id])) {
        $porPedido[$id] = [
            'id_pedido'      => $id,
            'id_cliente'     => (int)$row['id_usuario_cliente'],
            'cliente'        => $row['cliente'],
            'telefono'       => $row['telefono'],
            'id_mascota'     => $row['id_mascota'] ? (int)$row['id_mascota'] : null,
            'mascota'        => $row['nombre_mascota'] ?: 'Mascota',
            'avatar_mascota' => $row['avatar_mascota'],
            'direccion'      => $row['direccion'],
            'barrio'         => $row['barrio'],
            'lat'            => (float)$row['lat'],
            'lng'            => (float)$row['lng'],
            'modalidad'      => $row['modalidad'],
            'franja'         => $row['franja_horaria'],
            'hora_inicio'    => horaInicioDeFranja($row['franja_horaria']),
            'duracion_min'   => (int)$row['duracion_min'],
            'comportamiento' => $row['comportamiento'],
            '_horaRecogida'    => null,
            '_horaEntrega'     => null,
            '_horaCancelacion' => null,
            '_motivo'          => null,
        ];
    }
    if ($row['tipo'] === 'recogida') {
        // hora_estimada (calculada al reordenar la ruta) es más precisa que
        // la franja en texto libre; se usa como fallback si aún no existe.
        if ($row['hora_estimada']) $porPedido[$id]['hora_inicio'] = substr($row['hora_estimada'], 0, 5);
        $porPedido[$id]['_horaRecogida'] = $row['hora_recogida'];
    } else {
        $porPedido[$id]['_horaEntrega'] = $row['hora_entrega'];
    }
    if ($row['hora_cancelacion']) {
        $porPedido[$id]['_horaCancelacion'] = $row['hora_cancelacion'];
        $porPedido[$id]['_motivo']          = $row['motivo_cancelacion'];
    }
}
$stmt->close();

$paseos = [];
foreach ($porPedido as $p) {
    $p['estado'] = estadoDerivadoPedido($p['_horaRecogida'], $p['_horaEntrega'], $p['_horaCancelacion']);
    $p['motivo_cancelacion'] = $p['_motivo'];
    unset($p['_horaRecogida'], $p['_horaEntrega'], $p['_horaCancelacion'], $p['_motivo']);
    $paseos[] = $p;
}

// Orden final por hora de inicio (las sin hora, al final)
usort($paseos, function ($a, $b) {
    if ($a['hora_inicio'] === $b['hora_inicio']) return strcmp($a['cliente'], $b['cliente']);
    if ($a['hora_inicio'] === null) return 1;
    if ($b['hora_inicio'] === null) return -1;
    return strcmp($a['hora_inicio'], $b['hora_inicio']);
});

responder(true, ['fecha' => $hoy, 'paseos' => $paseos]);
?>
