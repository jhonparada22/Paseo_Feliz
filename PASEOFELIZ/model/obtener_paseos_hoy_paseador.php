<?php
/**
 * obtener_paseos_hoy_paseador.php
 * (PASEADOR) Lista los paseos de HOY del paseador logueado, separados por
 * modalidad (individual / grupal), con el estado diario de cada mascota
 * (pendiente, recogido, en_paseo, entregado, cancelado + motivo).
 *
 * Sale del cronograma semanal (cronograma_paseos) cruzado con
 * pedidos_paseo y con la tabla paseos_dia (estado del día).
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
asegurarTablaPaseosDia($conn);

$hoy       = date('Y-m-d');
$diaSemana = (int)date('N'); // 1=lunes ... 7=domingo

$stmt = $conn->prepare(
    "SELECT pp.id_pedido, pp.id_usuario, pp.id_mascota,
            pp.direccion, pp.barrio, pp.lat, pp.lng,
            pp.modalidad, pp.franja_horaria, pp.duracion_min, pp.comportamiento,
            u.nombre AS cliente, iu.telefono,
            mu.nombre_mascota, mu.avatar_mascota,
            pd.estado AS estado_dia, pd.motivo_cancelacion
     FROM cronograma_paseos c
     JOIN pedidos_paseo pp  ON pp.id_pedido = c.id_pedido
     JOIN usuarios u        ON u.id = pp.id_usuario
     LEFT JOIN info_usuario iu    ON iu.id_usuario = pp.id_usuario
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = pp.id_mascota
     LEFT JOIN paseos_dia pd      ON pd.id_pedido = pp.id_pedido AND pd.fecha = ?
     WHERE c.id_paseador = ? AND c.dia_semana = ?
       AND pp.estado NOT IN ('cancelado','pendiente_pago','pago_fallido')
     ORDER BY pp.franja_horaria ASC, u.nombre ASC"
);
$stmt->bind_param("sii", $hoy, $idPaseador, $diaSemana);
$stmt->execute();
$res = $stmt->get_result();

$paseos = [];
while ($row = $res->fetch_assoc()) {
    $paseos[] = [
        'id_pedido'   => (int)$row['id_pedido'],
        'id_cliente'  => (int)$row['id_usuario'],
        'cliente'     => $row['cliente'],
        'telefono'    => $row['telefono'],
        'id_mascota'  => $row['id_mascota'] ? (int)$row['id_mascota'] : null,
        'mascota'     => $row['nombre_mascota'] ?: 'Mascota',
        'avatar_mascota' => $row['avatar_mascota'],
        'direccion'   => $row['direccion'],
        'barrio'      => $row['barrio'],
        'lat'         => (float)$row['lat'],
        'lng'         => (float)$row['lng'],
        'modalidad'   => $row['modalidad'],
        'franja'      => $row['franja_horaria'],
        'hora_inicio' => horaInicioDeFranja($row['franja_horaria']),
        'duracion_min'   => (int)$row['duracion_min'],
        'comportamiento' => $row['comportamiento'],
        'estado'      => $row['estado_dia'] ?: 'pendiente',
        'motivo_cancelacion' => $row['motivo_cancelacion'],
    ];
}
$stmt->close();

// Orden final por hora de inicio real de la franja (las sin hora, al final)
usort($paseos, function ($a, $b) {
    if ($a['hora_inicio'] === $b['hora_inicio']) return strcmp($a['cliente'], $b['cliente']);
    if ($a['hora_inicio'] === null) return 1;
    if ($b['hora_inicio'] === null) return -1;
    return strcmp($a['hora_inicio'], $b['hora_inicio']);
});

responder(true, ['fecha' => $hoy, 'paseos' => $paseos]);
?>
