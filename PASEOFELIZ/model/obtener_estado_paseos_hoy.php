<?php
/**
 * obtener_estado_paseos_hoy.php
 * Estado EN VIVO de la ruta de hoy del PASEADOR logueado, por cliente.
 * Se combina en el front con obtener_cronograma.php (que trae mascota,
 * dueño, horario, zona, comportamiento) para pintar "Mis Paseos > Hoy".
 *
 * GET sin parámetros (todo sale de la sesión).
 * Respuesta:
 *   { success, ruta: {id_ruta, estado} | null,
 *     clientes: [{id_usuario, id_mascota, estado, id_parada_recogida,
 *                 id_parada_entrega, hora_recogida, hora_entrega}] }
 *
 * estado por cliente:
 *   'pendiente'  -> la recogida aún no se completó
 *   'en_curso'   -> recogida completada, entrega pendiente
 *   'completado' -> entrega completada
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

$idPaseador = obtenerIdPaseadorSesion($conn);
$hoy = date('Y-m-d');

$stmt = $conn->prepare(
    "SELECT r.id_ruta, r.id_estado, er.nombre AS estado
     FROM rutas r
     JOIN estados_ruta er ON er.id_estado = r.id_estado
     WHERE r.id_paseador = ? AND r.fecha_paseo = ?
     ORDER BY r.hora_inicio ASC LIMIT 1"
);
$stmt->bind_param("is", $idPaseador, $hoy);
$stmt->execute();
$ruta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ruta) {
    responder(true, ['ruta' => null, 'clientes' => []]);
}

$idRuta = (int)$ruta['id_ruta'];

$stmt = $conn->prepare(
    "SELECT rp.id_parada, rp.tipo, rp.id_estado, rp.id_usuario_cliente, rp.id_mascota,
            rp.hora_llegada, rp.hora_completado
     FROM ruta_paradas rp
     WHERE rp.id_ruta = ? AND rp.id_usuario_cliente IS NOT NULL
     ORDER BY rp.orden ASC"
);
$stmt->bind_param("i", $idRuta);
$stmt->execute();
$res = $stmt->get_result();

// Agrupar por cliente+mascota: cada uno tiene una parada de recogida y una de entrega
$porCliente = [];
while ($p = $res->fetch_assoc()) {
    $clave = $p['id_usuario_cliente'] . '_' . $p['id_mascota'];
    if (!isset($porCliente[$clave])) {
        $porCliente[$clave] = [
            'id_usuario' => (int)$p['id_usuario_cliente'],
            'id_mascota' => (int)$p['id_mascota'],
            'recogida'   => null,
            'entrega'    => null,
        ];
    }
    $completada = (int)$p['id_estado'] === 3;
    $dato = ['id_parada' => (int)$p['id_parada'], 'completada' => $completada, 'hora' => $p['hora_completado']];
    $porCliente[$clave][$p['tipo']] = $dato;
}
$stmt->close();

$clientes = [];
foreach ($porCliente as $c) {
    $recogida = $c['recogida'];
    $entrega  = $c['entrega'];

    if ($entrega && $entrega['completada']) {
        $estado = 'completado';
    } elseif ($recogida && $recogida['completada']) {
        $estado = 'en_curso';
    } else {
        $estado = 'pendiente';
    }

    $clientes[] = [
        'id_usuario'         => $c['id_usuario'],
        'id_mascota'         => $c['id_mascota'],
        'estado'             => $estado,
        'id_parada_recogida' => $recogida ? $recogida['id_parada'] : null,
        'id_parada_entrega'  => $entrega ? $entrega['id_parada'] : null,
        'hora_recogida'      => $recogida ? $recogida['hora'] : null,
        'hora_entrega'       => $entrega ? $entrega['hora'] : null,
    ];
}

responder(true, [
    'ruta' => ['id_ruta' => $idRuta, 'estado' => $ruta['estado']],
    'clientes' => $clientes,
]);
?>
