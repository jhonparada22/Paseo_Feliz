<?php
/**
 * obtener_ruta.php
 * Devuelve el detalle de UNA ruta con todas sus paradas y datos del cliente/mascota.
 * Uso típico (paseador): GET ?modo=hoy   -> trae la ruta de HOY del paseador logueado
 * Uso típico (admin):    GET ?id_ruta=5  -> trae el detalle de esa ruta puntual
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarSesion();

$idRuta = intval($_GET['id_ruta'] ?? 0);
$modo   = $_GET['modo'] ?? '';

if ($modo === 'hoy') {
    // El propio paseador pide su ruta activa de hoy
    $idPaseador = obtenerIdPaseadorSesion($conn);
    $hoy = date('Y-m-d');
    $stmt = $conn->prepare(
        "SELECT id_ruta FROM rutas
         WHERE id_paseador = ? AND fecha_paseo = ? AND id_estado IN (1,2,3)
         ORDER BY hora_inicio ASC LIMIT 1"
    );
    $stmt->bind_param("is", $idPaseador, $hoy);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) responder(true, ['ruta' => null], 'No tienes rutas asignadas para hoy.');
    $idRuta = (int)$r['id_ruta'];
} elseif ($modo === 'cliente') {
    // El propio cliente pide la ruta donde aparece como parada hoy
    $idUsuario = (int)$_SESSION['usuario_id'];
    $hoy = date('Y-m-d');
    $stmt = $conn->prepare(
        "SELECT DISTINCT r.id_ruta FROM rutas r
         JOIN ruta_paradas rp ON rp.id_ruta = r.id_ruta
         WHERE rp.id_usuario_cliente = ? AND r.fecha_paseo = ? AND r.id_estado IN (1,2,3)
         ORDER BY r.hora_inicio ASC LIMIT 1"
    );
    $stmt->bind_param("is", $idUsuario, $hoy);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$r) responder(true, ['ruta' => null], 'No tienes ningún paseo programado para hoy.');
    $idRuta = (int)$r['id_ruta'];
}

if (!$idRuta) responder(false, [], 'id_ruta requerido.');

// Cabecera de la ruta
$stmt = $conn->prepare(
    "SELECT r.id_ruta, r.id_paseador, r.fecha_paseo, r.hora_inicio,
            r.distancia_estimada_km, r.duracion_estimada_min,
            r.fecha_inicio_real, r.fecha_fin_real,
            er.nombre AS estado, p.id_usuario, u.nombre AS nombre_paseador
     FROM rutas r
     JOIN estados_ruta er ON er.id_estado = r.id_estado
     JOIN paseadores p ON p.id_paseador = r.id_paseador
     JOIN usuarios u ON u.id = p.id_usuario
     WHERE r.id_ruta = ?"
);
$stmt->bind_param("i", $idRuta);
$stmt->execute();
$ruta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ruta) responder(false, [], 'Ruta no encontrada.');

// Paradas + datos de cliente/mascota
$stmt = $conn->prepare(
    "SELECT rp.id_parada, rp.orden, rp.etiqueta, rp.tipo, rp.direccion, rp.lat, rp.lng,
            rp.id_usuario_cliente, rp.id_mascota, rp.id_pedido, rp.hora_estimada,
            rp.hora_recogida, rp.hora_entrega, rp.hora_cancelacion, rp.motivo_cancelacion,
            ep.nombre AS estado_parada, rp.hora_llegada, rp.hora_completado,
            u.nombre AS cliente_nombre, iu.telefono AS cliente_telefono,
            mu.nombre_mascota, mu.avatar_mascota,
            mu.biografia_canina, mu.enfermedades_discapacidades
     FROM ruta_paradas rp
     JOIN estados_parada ep ON ep.id_estado = rp.id_estado
     LEFT JOIN usuarios u ON u.id = rp.id_usuario_cliente
     LEFT JOIN info_usuario iu ON iu.id_usuario = rp.id_usuario_cliente
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = rp.id_mascota
     WHERE rp.id_ruta = ?
     ORDER BY rp.orden ASC"
);
$stmt->bind_param("i", $idRuta);
$stmt->execute();
$res = $stmt->get_result();
$paradas = [];
while ($row = $res->fetch_assoc()) {
    $paradas[] = [
        'id'        => (int)$row['id_parada'],
        'orden'     => (int)$row['orden'],
        'label'     => $row['etiqueta'],
        'tipo'      => $row['tipo'],
        'addr'      => $row['direccion'],
        'lat'       => (float)$row['lat'],
        'lng'       => (float)$row['lng'],
        'estado'    => $row['estado_parada'],
        'hora_llegada'    => $row['hora_llegada'],
        'hora_completado' => $row['hora_completado'],
        'hora_estimada'      => $row['hora_estimada'],
        'hora_recogida'      => $row['hora_recogida'],
        'hora_entrega'       => $row['hora_entrega'],
        'hora_cancelacion'   => $row['hora_cancelacion'],
        'motivo_cancelacion' => $row['motivo_cancelacion'],
        'id_usuario_cliente' => $row['id_usuario_cliente'] ? (int)$row['id_usuario_cliente'] : null,
        'id_mascota'         => $row['id_mascota'] ? (int)$row['id_mascota'] : null,
        'id_pedido'          => $row['id_pedido'] ? (int)$row['id_pedido'] : null,
        'cliente'   => $row['cliente_nombre'] ? [
            'nombre'      => $row['cliente_nombre'],
            'telefono'    => $row['cliente_telefono'],
            'mascota'     => $row['nombre_mascota'],
            'avatar'      => $row['avatar_mascota'],
            'biografia'   => $row['biografia_canina'],
            'notas'       => $row['enfermedades_discapacidades'],
        ] : null,
    ];
}
$stmt->close();

responder(true, [
    'yo' => (int)($_SESSION['usuario_id'] ?? 0),
    'ruta' => [
        'id_ruta'     => (int)$ruta['id_ruta'],
        'id_paseador' => (int)$ruta['id_paseador'],
        'paseador'    => $ruta['nombre_paseador'],
        'fecha'       => $ruta['fecha_paseo'],
        'hora_inicio' => $ruta['hora_inicio'],
        'estado'      => $ruta['estado'],
        'distancia_estimada_km' => (float)$ruta['distancia_estimada_km'],
        'duracion_estimada_min' => (int)$ruta['duracion_estimada_min'],
        'paradas'     => $paradas,
    ],
]);
?>